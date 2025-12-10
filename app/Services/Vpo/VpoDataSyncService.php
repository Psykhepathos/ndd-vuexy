<?php

namespace App\Services\Vpo;

use App\Models\VpoTransportadorCache;
use App\Models\MotoristaEmpresaCache;
use App\Services\ProgressService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

/**
 * Service para sincronizar dados Progress + ANTT → Cache Local
 *
 * Fluxo:
 * 1. Busca dados do transportador no Progress (condicional por flgautonomo)
 * 2. Enriquece com dados da ANTT (API pública ou fallback)
 * 3. Salva/atualiza no cache local (vpo_transportadores_cache)
 * 4. Calcula score de qualidade
 */
class VpoDataSyncService
{
    protected ProgressService $progressService;

    // URLs da ANTT (Dados Abertos)
    protected string $anttApiBase = 'https://dados.antt.gov.br/api/3/action';
    protected ?string $anttDatasetCache = null;  // Armazena resource_id do dataset RNTRC

    public function __construct(ProgressService $progressService)
    {
        $this->progressService = $progressService;
    }

    /**
     * Sincroniza UM transportador específico do Progress → Cache Local
     *
     * @param int $codtrn Código do transportador no Progress
     * @param int|null $codmot Código do motorista (apenas para empresas)
     * @param string|null $placa Placa do veículo (opcional, busca do Progress se não fornecida)
     * @param bool $forceAnttUpdate Forçar atualização da ANTT mesmo se recente
     * @return array ['success' => bool, 'data' => VpoTransportadorCache|null, 'message' => string]
     */
    public function syncTransportador(
        int $codtrn,
        ?int $codmot = null,
        ?string $placa = null,
        bool $forceAnttUpdate = false
    ): array {
        try {
            Log::info("VPO Sync: Iniciando sincronização", [
                'codtrn' => $codtrn,
                'codmot' => $codmot,
                'placa' => $placa
            ]);

            // 1. Buscar dados do Progress
            $progressData = $this->fetchFromProgress($codtrn, $codmot, $placa);

            if (!$progressData['success']) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => $progressData['error'] ?? 'Erro ao buscar dados do Progress'
                ];
            }

            $transportador = $progressData['data'];

            // 2. Verificar se já existe no cache
            $cached = VpoTransportadorCache::where('codtrn', $codtrn)->first();

            // 3. Decidir se precisa atualizar ANTT
            $needsAnttUpdate = $forceAnttUpdate ||
                               !$cached ||
                               ($cached && $cached->needsAnttUpdate());

            $anttData = [];
            $anttFonte = 'none';

            if ($needsAnttUpdate && !empty($transportador['antt_rntrc'])) {
                // Buscar dados da ANTT
                $anttResult = $this->fetchFromAntt($transportador['antt_rntrc']);

                if ($anttResult['success']) {
                    $anttData = $anttResult['data'];
                    $anttFonte = $anttResult['fonte'];
                }
            }

            // 4. Mesclar dados Progress + ANTT
            $mergedData = $this->mergeData($transportador, $anttData, $anttFonte);

            // 5. Salvar/Atualizar no cache local
            if ($cached) {
                // IMPORTANTE: Preservar campos editados ANTES de atualizar
                $mergedData = $this->preservarCamposEditados($cached, $mergedData);
                $cached->update($mergedData);
                $cached->refresh();
            } else {
                $cached = VpoTransportadorCache::create($mergedData);
            }

            // 6. Calcular score de qualidade
            $score = $cached->calculateQualityScore();

            Log::info("VPO Sync: Sincronização concluída", [
                'codtrn' => $codtrn,
                'score_qualidade' => $score,
                'antt_fonte' => $anttFonte
            ]);

            return [
                'success' => true,
                'data' => $cached->fresh(),
                'message' => "Sincronização concluída com sucesso (score: {$score}/100)"
            ];

        } catch (\Exception $e) {
            Log::error("VPO Sync: Erro na sincronização", [
                'codtrn' => $codtrn,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Erro interno: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Sincroniza MÚLTIPLOS transportadores em batch
     *
     * @param array $codtrnList Lista de códigos de transportadores
     * @param bool $forceAnttUpdate Forçar atualização ANTT
     * @return array ['total' => int, 'success' => int, 'failed' => int, 'results' => array]
     */
    public function syncBatch(array $codtrnList, bool $forceAnttUpdate = false): array
    {
        $total = count($codtrnList);
        $success = 0;
        $failed = 0;
        $results = [];

        Log::info("VPO Sync Batch: Iniciando", ['total' => $total]);

        foreach ($codtrnList as $codtrn) {
            $result = $this->syncTransportador($codtrn, null, null, $forceAnttUpdate);

            if ($result['success']) {
                $success++;
            } else {
                $failed++;
            }

            $results[] = [
                'codtrn' => $codtrn,
                'success' => $result['success'],
                'message' => $result['message']
            ];

            // Rate limiting: aguardar 100ms entre requisições
            usleep(100000);
        }

        Log::info("VPO Sync Batch: Concluído", [
            'total' => $total,
            'success' => $success,
            'failed' => $failed
        ]);

        return [
            'total' => $total,
            'success' => $success,
            'failed' => $failed,
            'results' => $results
        ];
    }

    /**
     * Busca dados do transportador no Progress (condicional por flgautonomo)
     *
     * @param int $codtrn
     * @param int|null $codmot
     * @param string|null $placa
     * @return array ['success' => bool, 'data' => array|null, 'error' => string|null]
     */
    protected function fetchFromProgress(int $codtrn, ?int $codmot, ?string $placa): array
    {
        try {
            // Buscar transportador base
            $sql = "SELECT codtrn, nomtrn, flgautonomo, codcnpjcpf, cdantt, datvldantt, " .
                   "tipcam, numpla, desvei, numrg, orgrg, NomMae, numhab, datnas, desend, numend, tiplog, codlog, " .
                   "codbai, codmun, codest, dddcel, numcel, dddtel, numtel, \"e-mail\" " .
                   "FROM PUB.transporte WHERE codtrn = {$codtrn}";

            $result = $this->progressService->executeCustomQuery($sql);

            if (!$result['success'] || empty($result['data']['results'])) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => 'Transportador não encontrado no Progress'
                ];
            }

            $transportador = $result['data']['results'][0];

            // === DETERMINAÇÃO EMPRESA vs AUTÔNOMO ===
            // IMPORTANTE: flgautonomo não é confiável! Usar tamanho do documento:
            // - CPF (11 dígitos) = Autônomo
            // - CNPJ (14 dígitos) = Empresa
            $documento = preg_replace('/\D/', '', $transportador['codcnpjcpf'] ?? '');
            $isAutonomo = strlen($documento) !== 14;

            Log::info("VPO Sync: Determinação de tipo", [
                'codtrn' => $codtrn,
                'documento_length' => strlen($documento),
                'flgautonomo_original' => $transportador['flgautonomo'] ?? null,
                'is_autonomo_calculado' => $isAutonomo
            ]);

            // Buscar JOIN com tipcam para destipcam
            $tipcam = $transportador['tipcam'] ?? null;
            $destipcam = null;

            if ($tipcam) {
                $tipcamSql = "SELECT destipcam FROM PUB.tipcam WHERE tipcam = {$tipcam}";
                $tipcamResult = $this->progressService->executeCustomQuery($tipcamSql);

                if ($tipcamResult['success'] && !empty($tipcamResult['data']['results'])) {
                    $destipcam = $tipcamResult['data']['results'][0]['destipcam'] ?? null;
                }
            }

            if ($isAutonomo) {
                // AUTÔNOMO: todos os dados vêm do transporte
                return $this->mapAutonomoData($transportador, $destipcam);
            } else {
                // EMPRESA: buscar dados do motorista e veículo
                return $this->mapEmpresaData($transportador, $destipcam, $codmot, $placa);
            }

        } catch (\Exception $e) {
            Log::error("Erro ao buscar dados do Progress", [
                'codtrn' => $codtrn,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'data' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Mapeia dados de AUTÔNOMO do Progress
     */
    protected function mapAutonomoData(array $transportador, ?string $destipcam): array
    {
        // Buscar JOINs para bairro, município, estado
        $bairroNome = $this->getBairroNome($transportador['codbai'] ?? null);
        $municipioNome = $this->getMunicipioNome($transportador['codmun'] ?? null);
        $estadoSigla = $this->getEstadoSigla($transportador['codest'] ?? null);

        return [
            'success' => true,
            'data' => [
                // Chaves Progress
                'codtrn' => $transportador['codtrn'],
                'codmot' => null,
                'numpla' => $transportador['numpla'] ?? null,
                'flgautonomo' => true,

                // 19 campos VPO
                'cpf_cnpj' => preg_replace('/\D/', '', $transportador['codcnpjcpf'] ?? ''),
                'antt_rntrc' => $transportador['cdantt'] ?? null,
                'antt_nome' => $transportador['nomtrn'] ?? null,
                'antt_validade' => $transportador['datvldantt'] ?? null,
                'antt_status' => 'Ativo', // Será atualizado pela ANTT
                'placa' => $this->formatPlaca($transportador['numpla'] ?? null),
                'veiculo_tipo' => $destipcam ?? 'Não especificado',
                'veiculo_modelo' => $transportador['desvei'] ?? null,
                'condutor_rg' => !empty($transportador['numrg']) ? $transportador['numrg'] : ($transportador['numhab'] ?? null), // Fallback para CNH
                'condutor_nome' => $transportador['nomtrn'] ?? null,
                'condutor_sexo' => 'M', // Padrão
                'condutor_nome_mae' => $transportador['nommae'] ?? null, // ← CORRIGIDO! Progress tem este campo
                'condutor_data_nascimento' => $transportador['datnas'] ?? null,
                'endereco_rua' => $this->formatEndereco(
                    $transportador['tiplog'] ?? null,
                    $transportador['codlog'] ?? null,
                    $transportador['desend'] ?? null,
                    $transportador['numend'] ?? null
                ),
                'endereco_bairro' => $bairroNome,
                'endereco_cidade' => $municipioNome,
                'endereco_estado' => $estadoSigla,
                'contato_celular' => $this->formatTelefone(
                    $transportador['dddcel'] ?? null,
                    $transportador['numcel'] ?? null
                ),
                'contato_email' => $transportador['e-mail'] ?? null,

                // Metadados
                'fontes_dados' => [
                    'progress_transporte' => true,
                    'progress_tipcam' => $destipcam !== null,
                ],
                'ultima_sync_progress' => now(),
            ],
            'error' => null
        ];
    }

    /**
     * Mapeia dados de EMPRESA do Progress
     *
     * Fluxo:
     * 1. Busca motorista do Progress (trnmot)
     * 2. Verifica se existe cache complementar (motorista_empresa_cache)
     * 3. Mescla dados Progress + Cache para VPO
     */
    protected function mapEmpresaData(array $transportador, ?string $destipcam, ?int $codmot, ?string $placa): array
    {
        $codtrn = $transportador['codtrn'];

        // Buscar motorista (se codmot fornecido ou buscar default)
        $motorista = null;

        if ($codmot) {
            $motSql = "SELECT codmot, nommot, codcpf, codrntrc, datvldrntrc, numrg, nommae, datnas, " .
                      "desend, tiplog, codlog, codbai, codmun, codest, dddtel, numtel, email " .
                      "FROM PUB.trnmot WHERE codtrn = {$codtrn} AND codmot = {$codmot}";
        } else {
            // Buscar primeiro motorista ativo
            $motSql = "SELECT TOP 1 codmot, nommot, codcpf, codrntrc, datvldrntrc, numrg, nommae, datnas, " .
                      "desend, tiplog, codlog, codbai, codmun, codest, dddtel, numtel, email " .
                      "FROM PUB.trnmot WHERE codtrn = {$codtrn}";
        }

        $motResult = $this->progressService->executeCustomQuery($motSql);

        if ($motResult['success'] && !empty($motResult['data']['results'])) {
            $motorista = $motResult['data']['results'][0];
        }

        // Se não encontrou motorista, usar dados do transportador como fallback
        // (isso é comum em empresas pequenas onde o dono é o motorista)
        $useFallbackToTransporte = false;
        if (!$motorista) {
            Log::info("VPO Sync: Motorista não encontrado para empresa, usando fallback para dados do transporte", [
                'codtrn' => $codtrn
            ]);
            $useFallbackToTransporte = true;
        }

        // === INTEGRAÇÃO COM CACHE DE MOTORISTAS ===
        // Verifica se existe dados complementares no cache SQLite
        $motoristaCache = null;
        $usouCacheMotorista = false;

        if ($motorista) {
            $motoristaCache = MotoristaEmpresaCache::findByMotorista($codtrn, (int) $motorista['codmot']);

            if ($motoristaCache && $motoristaCache->dados_completos) {
                Log::info("VPO Sync: Usando dados do cache de motoristas empresa", [
                    'codtrn' => $codtrn,
                    'codmot' => $motorista['codmot'],
                    'cache_id' => $motoristaCache->id
                ]);
                $usouCacheMotorista = true;
            }
        }

        // Buscar veículo (se placa fornecida ou buscar do transporte)
        $veiculo = null;
        $placaBusca = $placa ?? $transportador['numpla'] ?? null;

        if ($placaBusca) {
            $veiSql = "SELECT numpla, tipcam, modvei FROM PUB.trnvei " .
                      "WHERE codtrn = {$codtrn} AND numpla = '{$placaBusca}'";

            $veiResult = $this->progressService->executeCustomQuery($veiSql);

            if ($veiResult['success'] && !empty($veiResult['data']['results'])) {
                $veiculo = $veiResult['data']['results'][0];
            }
        }

        // Buscar JOINs para bairro, município, estado (fonte: motorista ou transportador)
        if ($useFallbackToTransporte) {
            $bairroNome = $this->getBairroNome($transportador['codbai'] ?? null);
            $municipioNome = $this->getMunicipioNome($transportador['codmun'] ?? null);
            $estadoSigla = $this->getEstadoSigla($transportador['codest'] ?? null);
        } else {
            $bairroNome = $this->getBairroNome($motorista['codbai'] ?? null);
            $municipioNome = $this->getMunicipioNome($motorista['codmun'] ?? null);
            $estadoSigla = $this->getEstadoSigla($motorista['codest'] ?? null);
        }

        // Determinar modelo do veículo
        $veiculoModelo = null;
        if ($veiculo && !empty($veiculo['modvei'])) {
            $veiculoModelo = $veiculo['modvei']; // Modelo específico da empresa
        } elseif (!empty($transportador['desvei'])) {
            $veiculoModelo = $transportador['desvei']; // Fallback para desvei do transporte
        }

        // Se fallback, usar dados do transportador (como se fosse autônomo)
        if ($useFallbackToTransporte) {
            return [
                'success' => true,
                'data' => [
                    // Chaves Progress
                    'codtrn' => $codtrn,
                    'codmot' => null, // Sem motorista específico
                    'numpla' => $placaBusca,
                    'flgautonomo' => false, // Ainda é empresa

                    // 19 campos VPO - usando dados do transportador
                    'cpf_cnpj' => preg_replace('/\D/', '', $transportador['codcnpjcpf'] ?? ''),
                    'antt_rntrc' => $transportador['cdantt'] ?? null,
                    'antt_nome' => $transportador['nomtrn'] ?? null,
                    'antt_validade' => $transportador['datvldantt'] ?? null,
                    'antt_status' => 'Ativo',
                    'placa' => $this->formatPlaca($placaBusca),
                    'veiculo_tipo' => $destipcam ?? 'Não especificado',
                    'veiculo_modelo' => $veiculoModelo,
                    'condutor_rg' => !empty($transportador['numrg']) ? $transportador['numrg'] : ($transportador['numhab'] ?? null),
                    'condutor_nome' => $transportador['nomtrn'] ?? null,
                    'condutor_sexo' => 'M',
                    'condutor_nome_mae' => $transportador['nommae'] ?? null,
                    'condutor_data_nascimento' => $transportador['datnas'] ?? null,
                    'endereco_rua' => $this->formatEndereco(
                        $transportador['tiplog'] ?? null,
                        $transportador['codlog'] ?? null,
                        $transportador['desend'] ?? null,
                        $transportador['numend'] ?? null
                    ),
                    'endereco_bairro' => $bairroNome,
                    'endereco_cidade' => $municipioNome,
                    'endereco_estado' => $estadoSigla,
                    'contato_celular' => $this->formatTelefone(
                        $transportador['dddcel'] ?? null,
                        $transportador['numcel'] ?? null
                    ),
                    'contato_email' => $transportador['e-mail'] ?? null,

                    // Metadados
                    'fontes_dados' => [
                        'progress_transporte' => true,
                        'progress_trnmot' => false, // Sem motorista
                        'progress_trnvei' => $veiculo !== null,
                        'progress_tipcam' => $destipcam !== null,
                        'fallback_to_transporte' => true,
                        'cache_motorista' => false,
                    ],
                    'ultima_sync_progress' => now(),
                ],
                'error' => null
            ];
        }

        // === MONTAR DADOS DO MOTORISTA ===
        // Prioridade: Cache > Progress (para campos VPO obrigatórios)

        // CPF: Cache tem prioridade (Progress normalmente não tem para empresas)
        $cpfCnpj = $usouCacheMotorista && !empty($motoristaCache->cpf)
            ? $motoristaCache->cpf
            : preg_replace('/\D/', '', $motorista['codcpf'] ?? '');

        // RNTRC: Cache > Progress trnmot > Progress transporte
        $rntrc = $usouCacheMotorista && !empty($motoristaCache->rntrc)
            ? $motoristaCache->rntrc
            : ($motorista['codrntrc'] ?? $transportador['cdantt'] ?? null);

        // Nome da mãe: Cache > Progress
        $nomeMae = $usouCacheMotorista && !empty($motoristaCache->nommae)
            ? $motoristaCache->nommae
            : ($motorista['nommae'] ?? null);

        // Data nascimento: Cache > Progress
        $dataNascimento = $usouCacheMotorista && $motoristaCache->data_nascimento
            ? $motoristaCache->data_nascimento->format('Y-m-d')
            : ($motorista['datnas'] ?? null);

        // Nome do motorista: Cache preserva nome do Progress
        $nomeMotorista = $usouCacheMotorista && !empty($motoristaCache->nommot)
            ? $motoristaCache->nommot
            : ($motorista['nommot'] ?? null);

        // RG: Progress tem prioridade
        $rgMotorista = $motorista['numrg'] ?? null;

        // Endereço: Cache > Progress (se cache completo)
        $enderecoRua = $this->formatEndereco(
            $motorista['tiplog'] ?? null,
            $motorista['codlog'] ?? null,
            $motorista['desend'] ?? null,
            null
        );
        $enderecoBairro = $bairroNome;
        $enderecoCidade = $municipioNome;
        $enderecoEstado = $estadoSigla;

        if ($usouCacheMotorista) {
            // Se cache tem endereço, usar do cache
            if (!empty($motoristaCache->endereco_logradouro)) {
                $enderecoRua = $motoristaCache->endereco_logradouro;
                if (!empty($motoristaCache->endereco_numero)) {
                    $enderecoRua .= ", {$motoristaCache->endereco_numero}";
                }
            }
            if (!empty($motoristaCache->endereco_bairro)) {
                $enderecoBairro = $motoristaCache->endereco_bairro;
            }
            if (!empty($motoristaCache->endereco_cidade)) {
                $enderecoCidade = $motoristaCache->endereco_cidade;
            }
            if (!empty($motoristaCache->endereco_uf)) {
                $enderecoEstado = $motoristaCache->endereco_uf;
            }
        }

        // Caminho normal: com motorista (mesclado com cache se disponível)
        return [
            'success' => true,
            'data' => [
                // Chaves Progress
                'codtrn' => $codtrn,
                'codmot' => $motorista['codmot'],
                'numpla' => $placaBusca,
                'flgautonomo' => false,

                // 19 campos VPO (mesclados Progress + Cache)
                'cpf_cnpj' => $cpfCnpj,
                'antt_rntrc' => $rntrc,
                'antt_nome' => $nomeMotorista,
                'antt_validade' => $motorista['datvldrntrc'] ?? null,
                'antt_status' => 'Ativo', // Será atualizado pela ANTT
                'placa' => $this->formatPlaca($placaBusca),
                'veiculo_tipo' => $destipcam ?? 'Não especificado',
                'veiculo_modelo' => $veiculoModelo,
                'condutor_rg' => $rgMotorista,
                'condutor_nome' => $nomeMotorista,
                'condutor_sexo' => 'M', // Padrão
                'condutor_nome_mae' => $nomeMae,
                'condutor_data_nascimento' => $dataNascimento,
                'endereco_rua' => $enderecoRua,
                'endereco_bairro' => $enderecoBairro,
                'endereco_cidade' => $enderecoCidade,
                'endereco_estado' => $enderecoEstado,
                'contato_celular' => $this->formatTelefone(
                    $motorista['dddtel'] ?? null,
                    $motorista['numtel'] ?? null
                ),
                'contato_email' => $motorista['email'] ?? $transportador['e-mail'] ?? null,

                // Metadados
                'fontes_dados' => [
                    'progress_transporte' => true,
                    'progress_trnmot' => true,
                    'progress_trnvei' => $veiculo !== null,
                    'progress_tipcam' => $destipcam !== null,
                    'cache_motorista' => $usouCacheMotorista,
                    'cache_motorista_id' => $motoristaCache?->id,
                ],
                'ultima_sync_progress' => now(),
            ],
            'error' => null
        ];
    }

/**
     * Busca dados da ANTT (Dados Abertos ou API Comercial)
     *
     * @param string $rntrc
     * @return array ['success' => bool, 'data' => array, 'fonte' => string]
     */
    protected function fetchFromAntt(string $rntrc): array
    {
        // Estratégia 1: Dados Abertos da ANTT (gratuito, mensal)
        $anttData = $this->fetchFromAnttOpenData($rntrc);

        if ($anttData['success']) {
            return [
                'success' => true,
                'data' => $anttData['data'],
                'fonte' => 'dados_abertos'
            ];
        }

        // Estratégia 2: Fallback - validar por data de validade
        Log::info("VPO ANTT: Usando fallback (dados abertos não disponíveis)", ['rntrc' => $rntrc]);

        return [
            'success' => true,
            'data' => [
                'antt_status' => 'Ativo', // Assume ativo como padrão
            ],
            'fonte' => 'fallback'
        ];

        // Estratégia 3: API Comercial (futuro - Infosimples/Netrin)
        // Se tiver token configurado:
        // return $this->fetchFromAnttCommercialApi($rntrc);
    }

    /**
     * Busca dados da ANTT via Dados Abertos (CKAN API)
     *
     * @param string $rntrc
     * @return array ['success' => bool, 'data' => array]
     */
    protected function fetchFromAnttOpenData(string $rntrc): array
    {
        try {
            // Cache do dataset ANTT por 24 horas
            $cacheKey = 'antt_opendata_dataset';

            if (!$this->anttDatasetCache) {
                $this->anttDatasetCache = Cache::remember($cacheKey, 86400, function () {
                    // Buscar resource_id do dataset RNTRC
                    $response = Http::timeout(30)
                        ->get("{$this->anttApiBase}/package_show", [
                            'id' => 'rntrc'
                        ]);

                    if (!$response->successful()) {
                        return null;
                    }

                    $package = $response->json()['result'] ?? null;

                    if (!$package || empty($package['resources'])) {
                        return null;
                    }

                    // Pegar o resource mais recente (CSV)
                    $latestResource = collect($package['resources'])
                        ->sortByDesc('created')
                        ->first();

                    return $latestResource['id'] ?? null;
                });
            }

            if (!$this->anttDatasetCache) {
                return ['success' => false, 'data' => []];
            }

            // Buscar transportador específico no dataset
            $response = Http::timeout(30)
                ->get("{$this->anttApiBase}/datastore_search", [
                    'resource_id' => $this->anttDatasetCache,
                    'q' => $rntrc,
                    'limit' => 1
                ]);

            if (!$response->successful()) {
                return ['success' => false, 'data' => []];
            }

            $records = $response->json()['result']['records'] ?? [];

            if (empty($records)) {
                return ['success' => false, 'data' => []];
            }

            $record = $records[0];

            // Mapear campos da ANTT para nosso formato
            return [
                'success' => true,
                'data' => [
                    'antt_status' => $record['Situacao'] ?? 'Ativo',
                    'antt_validade' => isset($record['DataValidadeCNH'])
                        ? Carbon::parse($record['DataValidadeCNH'])->format('Y-m-d')
                        : null,
                ]
            ];

        } catch (\Exception $e) {
            Log::warning("Erro ao buscar dados da ANTT Open Data", [
                'rntrc' => $rntrc,
                'error' => $e->getMessage()
            ]);

            return ['success' => false, 'data' => []];
        }
    }

    /**
     * Mescla dados do Progress + ANTT
     */
    protected function mergeData(array $progressData, array $anttData, string $anttFonte): array
    {
        // Progress é fonte primária, ANTT enriquece
        $merged = $progressData;

        // Sobrescrever campos da ANTT se disponíveis
        if (!empty($anttData)) {
            foreach ($anttData as $key => $value) {
                if ($value !== null) {
                    $merged[$key] = $value;
                }
            }
        }

        // Adicionar metadado da fonte ANTT
        $merged['antt_fonte'] = $anttFonte;
        $merged['ultima_sync_antt'] = now();

        return $merged;
    }

    // === MÉTODOS AUXILIARES ===

    /**
     * Busca nome do bairro
     */
    protected function getBairroNome(?int $codbai): ?string
    {
        if (!$codbai) return null;

        try {
            $sql = "SELECT desbai FROM PUB.bairro WHERE codbai = {$codbai}";
            $result = $this->progressService->executeCustomQuery($sql);

            return $result['data']['results'][0]['desbai'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Busca nome do município
     */
    protected function getMunicipioNome(?int $codmun): ?string
    {
        if (!$codmun) return null;

        try {
            $sql = "SELECT desmun FROM PUB.municipio WHERE codmun = {$codmun}";
            $result = $this->progressService->executeCustomQuery($sql);

            return $result['data']['results'][0]['desmun'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Busca sigla do estado
     */
    protected function getEstadoSigla(?int $codest): ?string
    {
        if (!$codest) return null;

        try {
            $sql = "SELECT sigest FROM PUB.estado WHERE codest = {$codest}";
            $result = $this->progressService->executeCustomQuery($sql);

            return $result['data']['results'][0]['sigest'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Formata placa (remove espaços e caracteres especiais)
     */
    protected function formatPlaca(?string $placa): ?string
    {
        if (!$placa) return null;

        return strtoupper(preg_replace('/[^A-Z0-9]/', '', $placa));
    }

    /**
     * Formata telefone (DDD + número, 11 dígitos)
     */
    protected function formatTelefone(?string $ddd, ?string $numero): ?string
    {
        if (!$ddd || !$numero) return null;

        $telefone = preg_replace('/\D/', '', $ddd . $numero);

        // Garantir 11 dígitos (DDD + 9 dígitos)
        if (strlen($telefone) === 10) {
            // Adicionar 9 na frente do número celular antigo
            $telefone = substr($telefone, 0, 2) . '9' . substr($telefone, 2);
        }

        return $telefone;
    }

    /**
     * Formata endereço (concatena tipo logradouro + nome)
     */
    protected function formatEndereco(?string $tiplog, ?string $codlog, ?string $desend, ?string $numend): ?string
    {
        if (!$desend) return null;

        $endereco = $desend;

        // Adicionar número se disponível
        if ($numend) {
            $endereco .= ", {$numend}";
        }

        return $endereco;
    }

    /**
     * Preserva campos que foram editados manualmente pelo usuário.
     * Quando o usuário preenche campos faltantes via frontend,
     * esses valores NÃO devem ser sobrescritos pelo sync.
     */
    protected function preservarCamposEditados(VpoTransportadorCache $cached, array $mergedData): array
    {
        // Se não foi editado manualmente, não preservar nada
        if (!$cached->editado_manualmente) {
            return $mergedData;
        }

        // Campos que o usuário pode editar manualmente
        $camposEditaveis = [
            'antt_rntrc', 'antt_validade', 'antt_status',
            'placa', 'veiculo_tipo', 'veiculo_modelo',
            'condutor_rg', 'condutor_nome', 'condutor_sexo', 'condutor_nome_mae', 'condutor_data_nascimento',
            'endereco_rua', 'endereco_numero', 'endereco_bairro', 'endereco_cidade', 'endereco_estado', 'endereco_cep',
            'contato_telefone', 'contato_celular', 'contato_email',
        ];

        // Para cada campo editável, se o valor do cache é não-vazio, preservar
        foreach ($camposEditaveis as $campo) {
            $valorCache = $cached->$campo;
            $valorNovo = $mergedData[$campo] ?? null;

            // Preservar se: cache tem valor E (novo está vazio OU cache foi editado)
            if (!empty($valorCache) && (empty($valorNovo) || $cached->editado_manualmente)) {
                $mergedData[$campo] = $valorCache;
            }
        }

        // Preservar flags de edição manual
        $mergedData['editado_manualmente'] = true;
        $mergedData['data_edicao_manual'] = $cached->data_edicao_manual;

        Log::info("VPO Sync: Campos editados manualmente preservados", [
            'codtrn' => $cached->codtrn
        ]);

        return $mergedData;
    }
}

