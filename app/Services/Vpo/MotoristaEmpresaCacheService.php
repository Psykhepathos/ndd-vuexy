<?php

namespace App\Services\Vpo;

use App\Models\MotoristaEmpresaCache;
use App\Services\ProgressService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

/**
 * Service para gerenciar cache de motoristas de empresas (CNPJ)
 *
 * Responsável por:
 * 1. Buscar motoristas do Progress (trnmot)
 * 2. Mesclar com dados do cache SQLite
 * 3. Salvar/atualizar dados complementares
 * 4. Fornecer dados para emissão de VPO
 */
class MotoristaEmpresaCacheService
{
    protected ProgressService $progressService;

    public function __construct(ProgressService $progressService)
    {
        $this->progressService = $progressService;
    }

    /**
     * Lista motoristas de uma empresa, mesclando Progress + Cache
     *
     * @param int $codtrn Código do transportador
     * @return array Lista de motoristas com dados mesclados
     */
    public function listarMotoristasEmpresa(int $codtrn): array
    {
        try {
            // 1. Buscar motoristas do Progress
            $motoristasProgress = $this->buscarMotoristasProgress($codtrn);

            if (empty($motoristasProgress)) {
                return [
                    'success' => true,
                    'data' => [],
                    'message' => 'Transportador não possui motoristas cadastrados no Progress'
                ];
            }

            // 2. Buscar dados do cache
            $motoristasCache = MotoristaEmpresaCache::getByTransportador($codtrn)
                ->keyBy('codmot');

            // 3. Mesclar dados
            $motoristas = [];
            foreach ($motoristasProgress as $mp) {
                $codmot = (int) $mp['codmot'];
                $cache = $motoristasCache->get($codmot);

                $motorista = [
                    'codtrn' => $codtrn,
                    'codmot' => $codmot,

                    // Dados do Progress
                    'nommot' => $mp['nommot'] ?? '',
                    'numrg' => $mp['numrg'] ?? '',
                    'nompai' => $mp['nompai'] ?? '',
                    'nommae' => $mp['nommae'] ?? '',
                    'codrntrc_progress' => $mp['codrntrc'] ?? '',

                    // Dados do Cache (sobrescreve Progress se existir)
                    'cpf' => $cache?->cpf ?? '',
                    'rntrc' => $cache?->rntrc ?? $mp['codrntrc'] ?? '',
                    'data_nascimento' => $cache?->data_nascimento?->format('Y-m-d') ?? '',
                    'cnh' => $cache?->cnh ?? '',
                    'categoria_cnh' => $cache?->categoria_cnh ?? '',
                    'validade_cnh' => $cache?->validade_cnh?->format('Y-m-d') ?? '',

                    // Endereço
                    'endereco_logradouro' => $cache?->endereco_logradouro ?? '',
                    'endereco_numero' => $cache?->endereco_numero ?? '',
                    'endereco_bairro' => $cache?->endereco_bairro ?? '',
                    'endereco_cidade' => $cache?->endereco_cidade ?? '',
                    'endereco_uf' => $cache?->endereco_uf ?? '',
                    'endereco_cep' => $cache?->endereco_cep ?? '',

                    // Status (será atualizado abaixo)
                    'tem_cache' => $cache !== null,
                    'dados_completos' => false,
                    'campos_faltantes' => [],
                ];

                // Calcular campos faltantes considerando dados do Progress + Cache
                $camposFaltantes = $this->calcularCamposFaltantes($motorista, $cache);
                $motorista['dados_completos'] = empty($camposFaltantes);
                $motorista['campos_faltantes'] = $camposFaltantes;

                $motoristas[] = $motorista;
            }

            return [
                'success' => true,
                'data' => $motoristas,
                'total' => count($motoristas),
                'completos' => count(array_filter($motoristas, fn($m) => $m['dados_completos'])),
            ];

        } catch (\Exception $e) {
            Log::error('Erro ao listar motoristas empresa', [
                'codtrn' => $codtrn,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erro ao buscar motoristas: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Busca um motorista específico
     */
    public function getMotorista(int $codtrn, int $codmot): array
    {
        try {
            // Buscar do Progress
            $sql = "SELECT codtrn, codmot, nommot, numrg, nompai, nommae, codrntrc FROM PUB.trnmot WHERE codtrn = {$codtrn} AND codmot = {$codmot}";
            $result = $this->progressService->executeCustomQuery($sql);

            if (!$result['success'] || empty($result['data']['results'])) {
                return [
                    'success' => false,
                    'error' => 'Motorista não encontrado no Progress'
                ];
            }

            $mp = $result['data']['results'][0];

            // Buscar do cache
            $cache = MotoristaEmpresaCache::findByMotorista($codtrn, $codmot);

            // Mesclar dados
            $motorista = [
                'codtrn' => $codtrn,
                'codmot' => $codmot,

                // Dados do Progress
                'nommot' => $mp['nommot'] ?? '',
                'numrg' => $mp['numrg'] ?? '',
                'nompai' => $mp['nompai'] ?? '',
                'nommae' => $cache?->nommae ?? $mp['nommae'] ?? '',
                'codrntrc_progress' => $mp['codrntrc'] ?? '',

                // Dados do Cache
                'cpf' => $cache?->cpf ?? '',
                'rntrc' => $cache?->rntrc ?? $mp['codrntrc'] ?? '',
                'data_nascimento' => $cache?->data_nascimento?->format('Y-m-d') ?? '',
                'cnh' => $cache?->cnh ?? '',
                'categoria_cnh' => $cache?->categoria_cnh ?? '',
                'validade_cnh' => $cache?->validade_cnh?->format('Y-m-d') ?? '',

                // Endereço
                'endereco_logradouro' => $cache?->endereco_logradouro ?? '',
                'endereco_numero' => $cache?->endereco_numero ?? '',
                'endereco_bairro' => $cache?->endereco_bairro ?? '',
                'endereco_cidade' => $cache?->endereco_cidade ?? '',
                'endereco_uf' => $cache?->endereco_uf ?? '',
                'endereco_cep' => $cache?->endereco_cep ?? '',

                // Status
                'tem_cache' => $cache !== null,
                'dados_completos' => $cache?->dados_completos ?? false,
                'campos_faltantes' => $cache?->getCamposFaltantes() ?? MotoristaEmpresaCache::CAMPOS_OBRIGATORIOS_VPO,
            ];

            return [
                'success' => true,
                'data' => $motorista
            ];

        } catch (\Exception $e) {
            Log::error('Erro ao buscar motorista', [
                'codtrn' => $codtrn,
                'codmot' => $codmot,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erro ao buscar motorista: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Salva/atualiza dados de um motorista
     */
    public function salvarMotorista(int $codtrn, int $codmot, array $dados): array
    {
        try {
            // Validar se motorista existe no Progress
            $motorista = $this->getMotorista($codtrn, $codmot);
            if (!$motorista['success']) {
                return $motorista;
            }

            // Buscar ou criar cache
            $cache = MotoristaEmpresaCache::findByMotorista($codtrn, $codmot);
            if (!$cache) {
                $cache = new MotoristaEmpresaCache();
                $cache->codtrn = $codtrn;
                $cache->codmot = $codmot;
                $cache->created_by = Auth::id();
            }

            // Atualizar dados do Progress (espelho)
            $cache->nommot = $motorista['data']['nommot'];
            $cache->numrg = $motorista['data']['numrg'];
            $cache->nompai = $motorista['data']['nompai'];

            // Atualizar dados do usuário
            if (isset($dados['cpf'])) {
                $cache->cpf = preg_replace('/\D/', '', $dados['cpf']);
            }
            if (isset($dados['rntrc'])) {
                $cache->rntrc = preg_replace('/\D/', '', $dados['rntrc']);
            }
            if (isset($dados['nommae'])) {
                $cache->nommae = $dados['nommae'];
            }
            if (isset($dados['data_nascimento'])) {
                $cache->data_nascimento = $dados['data_nascimento'];
            }
            if (isset($dados['cnh'])) {
                $cache->cnh = $dados['cnh'];
            }
            if (isset($dados['categoria_cnh'])) {
                $cache->categoria_cnh = $dados['categoria_cnh'];
            }
            if (isset($dados['validade_cnh'])) {
                $cache->validade_cnh = $dados['validade_cnh'];
            }

            // Endereço
            if (isset($dados['endereco_logradouro'])) {
                $cache->endereco_logradouro = $dados['endereco_logradouro'];
            }
            if (isset($dados['endereco_numero'])) {
                $cache->endereco_numero = $dados['endereco_numero'];
            }
            if (isset($dados['endereco_bairro'])) {
                $cache->endereco_bairro = $dados['endereco_bairro'];
            }
            if (isset($dados['endereco_cidade'])) {
                $cache->endereco_cidade = $dados['endereco_cidade'];
            }
            if (isset($dados['endereco_uf'])) {
                $cache->endereco_uf = strtoupper($dados['endereco_uf']);
            }
            if (isset($dados['endereco_cep'])) {
                $cache->endereco_cep = preg_replace('/\D/', '', $dados['endereco_cep']);
            }

            $cache->updated_by = Auth::id();
            $cache->save();

            Log::info('Motorista empresa salvo no cache', [
                'codtrn' => $codtrn,
                'codmot' => $codmot,
                'dados_completos' => $cache->dados_completos,
                'user_id' => Auth::id()
            ]);

            return [
                'success' => true,
                'message' => 'Motorista salvo com sucesso',
                'data' => $this->getMotorista($codtrn, $codmot)['data'] ?? [],
                'dados_completos' => $cache->dados_completos,
                'campos_faltantes' => $cache->getCamposFaltantes()
            ];

        } catch (\Exception $e) {
            Log::error('Erro ao salvar motorista', [
                'codtrn' => $codtrn,
                'codmot' => $codmot,
                'dados' => $dados,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erro ao salvar motorista: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Retorna dados do motorista para VPO
     *
     * @param int $codtrn Código do transportador
     * @param int $codmot Código do motorista
     * @return array|null Dados formatados para VPO ou null se incompleto
     */
    public function getMotoristaParaVpo(int $codtrn, int $codmot): ?array
    {
        $cache = MotoristaEmpresaCache::findByMotorista($codtrn, $codmot);

        if (!$cache || !$cache->dados_completos) {
            return null;
        }

        return $cache->toVpoData();
    }

    /**
     * Lista motoristas completos de um transportador (prontos para VPO)
     */
    public function listarMotoristasCompletosParaVpo(int $codtrn): array
    {
        $motoristas = MotoristaEmpresaCache::getCompletosDoTransportador($codtrn);

        return $motoristas->map(fn($m) => $m->toVpoData())->toArray();
    }

    /**
     * Busca motoristas do Progress
     */
    protected function buscarMotoristasProgress(int $codtrn): array
    {
        $sql = "SELECT codtrn, codmot, nommot, numrg, nompai, nommae, codrntrc FROM PUB.trnmot WHERE codtrn = {$codtrn} ORDER BY codmot";
        $result = $this->progressService->executeCustomQuery($sql);

        if (!$result['success'] || empty($result['data']['results'])) {
            return [];
        }

        return $result['data']['results'];
    }

    /**
     * Verifica se transportador é empresa (CNPJ)
     */
    public function isEmpresa(int $codtrn): bool
    {
        $sql = "SELECT codcnpjcpf FROM PUB.transporte WHERE codtrn = {$codtrn}";
        $result = $this->progressService->executeCustomQuery($sql);

        if (!$result['success'] || empty($result['data']['results'])) {
            return false;
        }

        $doc = $result['data']['results'][0]['codcnpjcpf'] ?? '';
        return strlen(preg_replace('/\D/', '', $doc)) === 14;
    }

    /**
     * Verifica se transportador tem motoristas no Progress
     */
    public function temMotoristasProgress(int $codtrn): bool
    {
        $sql = "SELECT COUNT(*) as total FROM PUB.trnmot WHERE codtrn = {$codtrn}";
        $result = $this->progressService->executeCustomQuery($sql);

        if (!$result['success'] || empty($result['data']['results'])) {
            return false;
        }

        return ($result['data']['results'][0]['total'] ?? 0) > 0;
    }

    /**
     * Calcula campos faltantes considerando dados do Progress + Cache
     *
     * Campos obrigatórios para VPO:
     * - cpf (do cache)
     * - rntrc (do cache ou Progress)
     * - nommot (do Progress - sempre presente se motorista existe)
     * - nommae (do Progress ou cache)
     * - data_nascimento (do cache)
     *
     * @param array $motorista Dados mesclados do motorista
     * @param MotoristaEmpresaCache|null $cache Dados do cache (se existir)
     * @return array Lista de campos faltantes
     */
    protected function calcularCamposFaltantes(array $motorista, ?MotoristaEmpresaCache $cache): array
    {
        $faltantes = [];

        // CPF - sempre do cache
        if (empty($motorista['cpf'])) {
            $faltantes[] = 'cpf';
        }

        // RNTRC - do cache ou Progress
        if (empty($motorista['rntrc']) && empty($motorista['codrntrc_progress'])) {
            $faltantes[] = 'rntrc';
        }

        // Nome do motorista - vem do Progress, não precisa verificar
        // (se o motorista existe em trnmot, ele tem nome)

        // Nome da mãe - do Progress ou cache
        // Se não tiver em nenhum dos dois, precisa preencher
        if (empty($motorista['nommae']) && empty($cache?->nommae)) {
            $faltantes[] = 'nommae';
        }

        // Data de nascimento - sempre do cache
        if (empty($motorista['data_nascimento'])) {
            $faltantes[] = 'data_nascimento';
        }

        return $faltantes;
    }
}
