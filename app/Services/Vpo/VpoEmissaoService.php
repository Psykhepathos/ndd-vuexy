<?php

namespace App\Services\Vpo;

use App\Models\VpoEmissao;
use App\Models\VpoTransportadorCache;
use App\Services\ProgressService;
use App\Services\NddCargo\XmlBuilders\VpoXmlBuilder;
use App\Services\NddCargo\XmlBuilders\RoteirizadorXmlBuilder;
use App\Services\NddCargo\NddCargoSoapClient;
use App\Services\NddCargo\DigitalSignature;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Servico de emissao VPO via NDD Cargo
 * Fluxo assincrono: iniciar -> polling UUID -> processar resultado
 */
class VpoEmissaoService
{
    protected ProgressService $progressService;
    protected NddCargoSoapClient $nddCargoSoapClient;
    protected VpoDataSyncService $vpoSyncService;
    protected VpoXmlBuilder $vpoXmlBuilder;
    protected RoteirizadorXmlBuilder $roteirizadorXmlBuilder;
    protected ?DigitalSignature $digitalSignature = null;

    public function __construct(
        ProgressService $progressService,
        NddCargoSoapClient $nddCargoSoapClient,
        VpoDataSyncService $vpoSyncService,
        VpoXmlBuilder $vpoXmlBuilder,
        RoteirizadorXmlBuilder $roteirizadorXmlBuilder
    ) {
        $this->progressService = $progressService;
        $this->nddCargoSoapClient = $nddCargoSoapClient;
        $this->vpoSyncService = $vpoSyncService;
        $this->vpoXmlBuilder = $vpoXmlBuilder;
        $this->roteirizadorXmlBuilder = $roteirizadorXmlBuilder;
    }

    /**
     * Iniciar emissao VPO
     */
    public function iniciarEmissao(array $params): array
    {
        try {
            $codpac = $params['codpac'];
            $rotaId = $params['rota_id'];

            Log::info("VPO Emissao: Iniciando", ['codpac' => $codpac, 'rota_id' => $rotaId]);

            // 1. Obter pacote
            $pacoteData = $this->getPacoteData($codpac);
            if (!$pacoteData['success']) {
                return ['success' => false, 'data' => null, 'error' => $pacoteData['error']];
            }

            $pacote = $pacoteData['data'];
            $codtrn = $pacote['codtrn'];

            // 2. Sincronizar VPO
            $syncResult = $this->vpoSyncService->syncTransportador($codtrn);
            if (!$syncResult['success']) {
                return ['success' => false, 'data' => null, 'error' => "Falha sync VPO: " . ($syncResult['error'] ?? '')];
            }

            $vpoCache = VpoTransportadorCache::byCodtrn($codtrn)->first();
            if (!$vpoCache) {
                return ['success' => false, 'data' => null, 'error' => "Dados VPO nao encontrados"];
            }

            // 2.5. VALIDAR campos obrigatórios (CRÍTICO!)
            // Bypass temporário para testes (remover em produção!)
            $skipValidation = $params['skip_validation'] ?? false;
            if (!$skipValidation) {
                $validacao = $this->validarCamposObrigatorios($vpoCache);
                if (!$validacao['valido']) {
                    Log::warning("VPO Emissao: Validacao falhou", [
                        'codtrn' => $codtrn,
                        'score' => $vpoCache->score_qualidade,
                        'campos_faltantes' => $validacao['campos_faltantes']
                    ]);

                    return [
                        'success' => false,
                        'data' => null,
                        'error' => $validacao['mensagem'],
                        'validation_errors' => $validacao['campos_faltantes'],
                        'score_qualidade' => $vpoCache->score_qualidade
                    ];
                }
            } else {
                Log::warning("VPO Emissao: VALIDACAO BYPASS ATIVO (skip_validation=true)");
            }

            // 3. Obter rota + waypoints
            $rotaData = $this->getRotaWithWaypoints($rotaId, $codpac);
            if (!$rotaData['success']) {
                return ['success' => false, 'data' => null, 'error' => $rotaData['error']];
            }

            $rota = $rotaData['data'];

            // 4. Criar emissao
            $emissao = VpoEmissao::create([
                'uuid' => Str::uuid()->toString(), // Temporario, sera substituido
                'codpac' => $codpac,
                'codtrn' => $codtrn,
                'codmot' => $pacote['codmot'] ?? null,
                'rota_id' => $rotaId,
                'rota_nome' => $rota['nome'],
                'waypoints' => $rota['waypoints'],
                'total_waypoints' => count($rota['waypoints']),
                'vpo_data' => $vpoCache->toVpoArray(),
                'fontes_dados' => $vpoCache->fontes_dados,
                'score_qualidade' => $vpoCache->score_qualidade,
                'status' => 'pending',
                'usuario_id' => $params['usuario_id'] ?? null,
                'ip_address' => $params['ip_address'] ?? null,
                'user_agent' => $params['user_agent'] ?? null,
            ]);

            // 5. Enviar para NDD Cargo
            $envioResult = $this->enviarParaNddCargo($emissao);

            if (!$envioResult['success']) {
                $emissao->markAsFailed($envioResult['error']);
                return ['success' => false, 'data' => $emissao, 'error' => $envioResult['error']];
            }

            // 6. Atualizar com UUID real
            $emissao->update([
                'uuid' => $envioResult['uuid'],
                'ndd_request_xml' => $envioResult['xml_enviado'],
            ]);

            $emissao->markAsProcessing();

            Log::info("VPO Emissao: Iniciada com sucesso", ['emissao_id' => $emissao->id, 'uuid' => $emissao->uuid]);

            return ['success' => true, 'data' => $emissao, 'error' => null];

        } catch (\Exception $e) {
            Log::error("VPO Emissao: Erro ao iniciar", ['error' => $e->getMessage()]);
            return ['success' => false, 'data' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Consultar resultado (polling)
     */
    public function consultarResultado(string $uuid): array
    {
        try {
            $emissao = VpoEmissao::byUuid($uuid)->first();

            if (!$emissao) {
                return ['success' => false, 'data' => null, 'status' => 'not_found', 'error' => "Nao encontrada"];
            }

            if ($emissao->isFinished()) {
                return ['success' => true, 'data' => $emissao, 'status' => $emissao->status, 'error' => $emissao->error_message];
            }

            if ($emissao->isStuck()) {
                $emissao->markAsFailed("Timeout", 'TIMEOUT');
                return ['success' => false, 'data' => $emissao, 'status' => 'failed', 'error' => 'Timeout'];
            }

            if ($emissao->hasExceededPollingLimit()) {
                $emissao->markAsFailed("Limite polling", 'POLLING_LIMIT');
                return ['success' => false, 'data' => $emissao, 'status' => 'failed', 'error' => 'Limite excedido'];
            }

            if (!$emissao->canPollAgain(5)) {
                return ['success' => true, 'data' => $emissao, 'status' => 'processing', 'error' => null, 'retry_after' => 5];
            }

            $emissao->registerPolling();

            // Consultar NDD Cargo via SOAP (passar processCode para OVP: 2019)
            $consultaResult = $this->nddCargoSoapClient->consultarResultado($emissao->uuid, 2019);

            if (!$consultaResult['success']) {
                Log::warning("VPO Emissao: Erro ao consultar NDD Cargo", ['uuid' => $uuid, 'error' => $consultaResult['error']]);
                return ['success' => true, 'data' => $emissao, 'status' => 'processing', 'error' => null, 'retry_after' => 5];
            }

            $response = $consultaResult['data'];

            // Processar resposta
            if ($this->isProcessoConcluido($response)) {
                $this->processarResultadoConcluido($emissao, $response);
                return ['success' => true, 'data' => $emissao->fresh(), 'status' => 'completed', 'error' => null];
            } elseif ($this->isProcessoComErro($response)) {
                $errorMessage = $this->extrairMensagemErro($response);
                $emissao->markAsFailed($errorMessage, 'NDD_CARGO_ERROR');
                return ['success' => false, 'data' => $emissao, 'status' => 'failed', 'error' => $errorMessage];
            }

            // Ainda processando
            return ['success' => true, 'data' => $emissao, 'status' => 'processing', 'error' => null, 'retry_after' => 5];

        } catch (\Exception $e) {
            Log::error("VPO Emissao: Erro ao consultar resultado", ['uuid' => $uuid, 'error' => $e->getMessage()]);
            return ['success' => false, 'data' => null, 'status' => 'error', 'error' => $e->getMessage()];
        }
    }

    /**
     * Cancelar emissao
     */
    public function cancelarEmissao(string $uuid): array
    {
        try {
            $emissao = VpoEmissao::byUuid($uuid)->first();

            if (!$emissao) {
                return ['success' => false, 'error' => 'Nao encontrada'];
            }

            if ($emissao->isFinished()) {
                return ['success' => false, 'error' => 'Ja finalizada'];
            }

            $emissao->markAsCancelled();

            return ['success' => true, 'data' => $emissao, 'error' => null];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // === HELPERS ===

    protected function getPacoteData(int $codpac): array
    {
        try {
            $sql = "SELECT codpac, codtrn, codmot, numpla FROM PUB.pacote WHERE codpac = {$codpac}";
            $result = $this->progressService->executeCustomQuery($sql);

            if (!$result['success'] || empty($result['data']['results'])) {
                return ['success' => false, 'data' => null, 'error' => "Pacote nao encontrado"];
            }

            return ['success' => true, 'data' => $result['data']['results'][0], 'error' => null];

        } catch (\Exception $e) {
            return ['success' => false, 'data' => null, 'error' => $e->getMessage()];
        }
    }

    protected function getRotaWithWaypoints(int $rotaId, int $codpac): array
    {
        try {
            // 1. Rota base
            $sql = "SELECT sPararRotID, desSPararRot FROM PUB.semPararRot WHERE sPararRotID = {$rotaId}";
            $result = $this->progressService->executeCustomQuery($sql);

            if (!$result['success'] || empty($result['data']['results'])) {
                return ['success' => false, 'data' => null, 'error' => "Rota nao encontrada"];
            }

            $rota = $result['data']['results'][0];

            // Progress retorna campos em lowercase
            $rotaNome = $rota['desspararrot'] ?? $rota['desSPararRot'] ?? 'Rota ' . $rotaId;

            // 2. Municipios da rota
            $rotaMunicipios = $this->progressService->getSemPararRotaWithMunicipios($rotaId);

            if (!$rotaMunicipios['success']) {
                return ['success' => false, 'data' => null, 'error' => "Erro municipios"];
            }

            $waypoints = [];

            // Adicionar municipios da rota COM cdibge (obrigatorio para NDD Cargo)
            foreach ($rotaMunicipios['data']['municipios'] ?? [] as $mun) {
                // cdibge é obrigatório para NDD Cargo pontosParada
                $cdibge = $mun['cdibge'] ?? $mun['cdIBGE'] ?? '';
                if (!empty($cdibge)) {
                    $waypoints[] = [
                        'lat' => (float) ($mun['lat'] ?? 0),
                        'lon' => (float) ($mun['lon'] ?? 0),
                        'cdibge' => $cdibge,
                        'tipo' => 'rota',
                        'nome' => trim($mun['desmun'] ?? $mun['desMun'] ?? '')
                    ];
                }
            }

            // 3. Primeira + ultima entrega (com cdibge do municipio do cliente)
            $itinerario = $this->progressService->getItinerarioPacote($codpac);

            if ($itinerario['success'] && !empty($itinerario['data']['pedidos'])) {
                $entregas = $itinerario['data']['pedidos'];

                // Primeira entrega
                // NOTA: gps_lat e gps_lon JÁ vêm processados do ProgressService (floats como -14.08)
                $primeira = $entregas[0];
                $primeiraCdibge = $primeira['cdibge'] ?? $primeira['cdIBGE'] ?? '';
                if (!empty($primeiraCdibge)) {
                    $waypoints[] = [
                        'lat' => (float) ($primeira['gps_lat'] ?? 0),
                        'lon' => (float) ($primeira['gps_lon'] ?? 0),
                        'cdibge' => $primeiraCdibge,
                        'tipo' => 'primeira_entrega',
                        'nome' => trim($primeira['razcli'] ?? '')
                    ];
                }

                // Última entrega
                $ultima = end($entregas);
                $ultimaCdibge = $ultima['cdibge'] ?? $ultima['cdIBGE'] ?? '';
                if (!empty($ultimaCdibge) && $ultimaCdibge !== $primeiraCdibge) {
                    $waypoints[] = [
                        'lat' => (float) ($ultima['gps_lat'] ?? 0),
                        'lon' => (float) ($ultima['gps_lon'] ?? 0),
                        'cdibge' => $ultimaCdibge,
                        'tipo' => 'ultima_entrega',
                        'nome' => trim($ultima['razcli'] ?? '')
                    ];
                }
            }

            return ['success' => true, 'data' => ['id' => $rotaId, 'nome' => $rotaNome, 'waypoints' => $waypoints], 'error' => null];

        } catch (\Exception $e) {
            return ['success' => false, 'data' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Enviar para NDD Cargo (construir XML + assinar + SOAP)
     *
     * FLUXO COMPLETO:
     * 1. Buscar TAG SemParar pela placa (sParargetExtra.tag)
     * 2. Consultar Roteirizador NDD para obter praças de pedágio
     * 3. Construir XML VPO com TAG + praças
     * 4. Assinar e enviar
     */
    protected function enviarParaNddCargo(VpoEmissao $emissao): array
    {
        try {
            // 0. Carregar certificado digital
            $this->loadCertificate();

            $vpoData = $emissao->getVpoData();
            $waypoints = $emissao->waypoints;
            $placa = $vpoData['placa'] ?? '';

            // 1. Buscar TAG SemParar pela placa
            $codigoTag = $this->buscarTagPorPlaca($placa);

            Log::info("VPO Emissao: TAG encontrada", [
                'placa' => $placa,
                'codigo_tag' => $codigoTag ?? 'NAO_ENCONTRADA'
            ]);

            // 2. Consultar Roteirizador NDD para obter praças de pedágio
            $pracas = [];
            if (!empty($codigoTag) && !empty($waypoints)) {
                $pracasResult = $this->consultarRoteirizadorParaPracas($waypoints, $vpoData);
                if ($pracasResult['success'] && !empty($pracasResult['pracas'])) {
                    $pracas = $pracasResult['pracas'];
                    Log::info("VPO Emissao: Praças obtidas do roteirizador", [
                        'total_pracas' => count($pracas)
                    ]);
                } else {
                    Log::warning("VPO Emissao: Roteirizador nao retornou pracas", [
                        'error' => $pracasResult['error'] ?? 'desconhecido'
                    ]);
                }
            }

            // 3. Construir XML VPO com TAG + praças
            $xmlData = $this->vpoXmlBuilder->build($vpoData, $waypoints, null, $pracas, $codigoTag);
            $xml = $xmlData['xml'];
            $uuid = $xmlData['uuid'];

            Log::info("VPO Emissao: XML construido", [
                'uuid' => $uuid,
                'size_bytes' => strlen($xml),
                'tem_tag' => !empty($codigoTag),
                'total_pracas' => count($pracas),
                'xml_preview' => substr($xml, 0, 1500)
            ]);

            // 4. Assinar XML digitalmente (CRÍTICO para NDD Cargo!)
            $xmlAssinado = $this->digitalSignature->signXml($xml, $uuid);

            Log::info("VPO Emissao: XML assinado digitalmente", [
                'uuid' => $uuid,
                'size_bytes_original' => strlen($xml),
                'size_bytes_assinado' => strlen($xmlAssinado)
            ]);

            // 5. Enviar via SOAP
            $soapResponse = $this->nddCargoSoapClient->emitirVPO($xmlAssinado, $uuid);

            if (!$soapResponse['success']) {
                return [
                    'success' => false,
                    'uuid' => null,
                    'xml_enviado' => $xmlAssinado,
                    'error' => 'Erro SOAP: ' . ($soapResponse['error'] ?? 'Desconhecido')
                ];
            }

            // 6. Validar UUID na resposta
            if (empty($soapResponse['data']['uuid'])) {
                return [
                    'success' => false,
                    'uuid' => null,
                    'xml_enviado' => $xmlAssinado,
                    'error' => 'UUID nao retornado pela NDD Cargo'
                ];
            }

            return [
                'success' => true,
                'uuid' => $soapResponse['data']['uuid'],
                'xml_enviado' => $xmlAssinado,
                'error' => null
            ];

        } catch (\Exception $e) {
            Log::error("VPO Emissao: Erro ao enviar para NDD Cargo", ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'uuid' => null,
                'xml_enviado' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Buscar código da TAG SemParar pela placa do veículo
     *
     * @param string $placa
     * @return string|null Código da TAG ou null se não encontrada
     */
    protected function buscarTagPorPlaca(string $placa): ?string
    {
        if (empty($placa)) {
            return null;
        }

        try {
            $sql = "SELECT TOP 1 tag FROM PUB.sParargetExtra WHERE placa = '{$placa}' AND tag IS NOT NULL AND tag <> '' ORDER BY dataatu DESC";
            $result = $this->progressService->executeCustomQuery($sql);

            if ($result['success'] && !empty($result['data']['results'])) {
                return trim($result['data']['results'][0]['tag'] ?? '');
            }

            return null;

        } catch (\Exception $e) {
            Log::warning("VPO Emissao: Erro ao buscar TAG", [
                'placa' => $placa,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Consultar roteirizador NDD Cargo para obter praças de pedágio
     *
     * FLUXO ASSÍNCRONO:
     * 1. Enviar request com ExchangePattern=7 → Recebe ResponseCode=202
     * 2. Polling com ExchangePattern=8 usando mesmo GUID até obter resultado
     *
     * @param array $waypoints
     * @param array $vpoData
     * @return array ['success' => bool, 'pracas' => array, 'error' => string|null]
     */
    protected function consultarRoteirizadorParaPracas(array $waypoints, array $vpoData): array
    {
        try {
            // Categoria de pedágio baseada no tipo de veículo
            $categoria = $this->getCategoriaPedagioFromTipo($vpoData['veiculo_tipo'] ?? '');

            // Construir XML do roteirizador
            $xmlData = $this->roteirizadorXmlBuilder->build($waypoints, $categoria);
            $xml = $xmlData['xml'];
            $uuid = $xmlData['uuid'];

            // Assinar XML
            $xmlAssinado = $this->digitalSignature->signXml($xml, $uuid);

            Log::info("VPO Emissao: Consultando roteirizador NDD (Passo 1 - Envio)", [
                'uuid' => $uuid,
                'total_waypoints' => count($waypoints),
                'categoria_pedagio' => $categoria
            ]);

            // Passo 1: Enviar consulta via SOAP (ExchangePattern=7)
            $soapResponse = $this->nddCargoSoapClient->consultarRoteirizador($xmlAssinado, $uuid);

            if (!$soapResponse['success']) {
                return [
                    'success' => false,
                    'pracas' => [],
                    'error' => $soapResponse['error'] ?? 'Erro ao consultar roteirizador'
                ];
            }

            // Verificar se resposta já contém praças (síncrono)
            $pracas = $this->extrairPracasDoRoteirizador($soapResponse['data'] ?? '');

            if (!empty($pracas)) {
                Log::info("VPO Emissao: Praças obtidas na resposta síncrona", [
                    'total_pracas' => count($pracas)
                ]);
                return [
                    'success' => true,
                    'pracas' => $pracas,
                    'error' => null
                ];
            }

            // Passo 2: Se resposta vazia (202), fazer polling com ExchangePattern=8
            Log::info("VPO Emissao: Resposta 202, iniciando polling (Passo 2)", [
                'uuid' => $uuid
            ]);

            $pracas = $this->pollingRoteirizadorResultado($uuid);

            return [
                'success' => !empty($pracas),
                'pracas' => $pracas,
                'error' => empty($pracas) ? 'Timeout no polling do roteirizador' : null
            ];

        } catch (\Exception $e) {
            Log::error("VPO Emissao: Erro ao consultar roteirizador", [
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'pracas' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Polling para obter resultado do roteirizador
     *
     * @param string $uuid GUID da consulta original
     * @param int $maxAttempts Número máximo de tentativas
     * @param int $intervalSeconds Intervalo entre tentativas
     * @return array Array de praças
     */
    protected function pollingRoteirizadorResultado(string $uuid, int $maxAttempts = 10, int $intervalSeconds = 2): array
    {
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            Log::info("VPO Emissao: Polling roteirizador tentativa {$attempt}/{$maxAttempts}", [
                'uuid' => $uuid
            ]);

            // Aguardar intervalo (exceto na primeira tentativa)
            if ($attempt > 1) {
                sleep($intervalSeconds);
            }

            // Consultar resultado com ExchangePattern=8 (ProcessCode 2027 = Roteirizador)
            $resultado = $this->nddCargoSoapClient->consultarResultado($uuid, 2027);

            if (!$resultado['success']) {
                Log::warning("VPO Emissao: Polling falhou", [
                    'attempt' => $attempt,
                    'error' => $resultado['error'] ?? 'Desconhecido'
                ]);
                continue;
            }

            $responseData = $resultado['data'] ?? '';

            // Verificar se ainda está processando (ResponseCode 202)
            if ($this->isAindaProcessando($responseData)) {
                Log::info("VPO Emissao: Ainda processando (202)", [
                    'attempt' => $attempt
                ]);
                continue;
            }

            // Tentar extrair praças da resposta
            $pracas = $this->extrairPracasDoRoteirizador($responseData);

            if (!empty($pracas)) {
                Log::info("VPO Emissao: Praças obtidas via polling!", [
                    'attempt' => $attempt,
                    'total_pracas' => count($pracas),
                    'codigos' => array_column($pracas, 'codigo')
                ]);
                return $pracas;
            }

            // Se chegou resposta mas sem praças, verificar se é erro
            if ($this->isRespostaComErro($responseData)) {
                $erro = $this->extrairErroRoteirizador($responseData);
                Log::warning("VPO Emissao: Roteirizador retornou erro", [
                    'attempt' => $attempt,
                    'erro' => $erro
                ]);
                break;
            }
        }

        Log::warning("VPO Emissao: Polling roteirizador timeout", [
            'uuid' => $uuid,
            'max_attempts' => $maxAttempts
        ]);

        return [];
    }

    /**
     * Verifica se resposta indica que ainda está processando (202)
     */
    protected function isAindaProcessando(string $responseData): bool
    {
        if (empty($responseData)) {
            return true;
        }

        // Verificar ResponseCode 202 na resposta
        if (preg_match('/<ResponseCode>(\d+)<\/ResponseCode>/i', $responseData, $matches)) {
            return $matches[1] === '202';
        }

        // Se CrossTalk_Body estiver vazio, ainda está processando
        if (preg_match('/<CrossTalk_Body[^>]*\/>/i', $responseData)) {
            return true;
        }

        return false;
    }

    /**
     * Verifica se resposta contém erro
     */
    protected function isRespostaComErro(string $responseData): bool
    {
        // Verificar ResponseCode diferente de 200/202
        if (preg_match('/<ResponseCode>(\d+)<\/ResponseCode>/i', $responseData, $matches)) {
            $code = (int) $matches[1];
            return $code >= 300 || $code === 0;
        }

        return false;
    }

    /**
     * Extrai mensagem de erro do roteirizador
     */
    protected function extrairErroRoteirizador(string $responseData): string
    {
        if (preg_match('/<mensagem[^>]*>([^<]+)<\/mensagem>/i', $responseData, $matches)) {
            return $matches[1];
        }

        if (preg_match('/<ResponseMessage[^>]*>([^<]+)<\/ResponseMessage>/i', $responseData, $matches)) {
            return $matches[1];
        }

        return 'Erro desconhecido no roteirizador';
    }

    /**
     * Extrair praças de pedágio da resposta do roteirizador NDD Cargo
     *
     * Estrutura esperada (após polling com ExchangePattern=8):
     * CrossTalk_Body > retornoConsultarRoteirizador > retConsultarRoteirizador > pracas > praca
     * Cada praca contém: nome, valor, localizacao, cnp (código da praça)
     *
     * @param string $xmlResponse
     * @return array Array de praças com código (cnp)
     */
    protected function extrairPracasDoRoteirizador(string $xmlResponse): array
    {
        if (empty($xmlResponse)) {
            Log::warning("VPO Emissao: Resposta do roteirizador vazia");
            return [];
        }

        $pracas = [];

        try {
            // Log para debug da resposta bruta (COMPLETA para análise!)
            Log::debug("VPO Emissao: Parsing resposta roteirizador", [
                'size_bytes' => strlen($xmlResponse),
                'resposta_completa' => $xmlResponse  // Log COMPLETO para debug
            ]);

            // A resposta vem HTML-encoded dentro do SendResult (com &lt; &gt;)
            // SEMPRE tentar regex primeiro pois é mais confiável com HTML-encoded
            if (str_contains($xmlResponse, '&lt;praca&gt;')) {
                Log::info("VPO Emissao: Detectado HTML-encoded, usando regex");
                $pracas = $this->extrairPracasViaRegex($xmlResponse);
                if (!empty($pracas)) {
                    return $pracas;
                }
            }

            // Se não encontrou via regex, tentar parsear como XML
            // Decodificar HTML entities primeiro
            $xmlDecoded = html_entity_decode($xmlResponse, ENT_QUOTES | ENT_XML1, 'UTF-8');

            // Tentar parsear como XML
            libxml_use_internal_errors(true);
            $xml = @simplexml_load_string($xmlDecoded);

            if ($xml === false) {
                // Se falhou XML, tentar regex no decoded
                $pracas = $this->extrairPracasViaRegex($xmlDecoded);
                if (!empty($pracas)) {
                    return $pracas;
                }

                $errors = libxml_get_errors();
                libxml_clear_errors();
                Log::warning("VPO Emissao: Falha ao parsear XML do roteirizador", [
                    'errors' => array_map(fn($e) => $e->message, $errors)
                ]);
                return [];
            }

            // Registrar namespaces
            $namespaces = $xml->getNamespaces(true);
            $nddNs = 'http://www.nddigital.com.br/nddcargo';
            $xml->registerXPathNamespace('ndd', $nddNs);

            // Estrutura NDD Cargo (confirmada via teste Python):
            // CrossTalk_Body > retornoConsultarRoteirizador > retConsultarRoteirizador > pracas > praca
            // Cada praca tem: nome, valor, localizacao, cnp
            $xpaths = [
                // Estrutura CORRETA (retConsultarRoteirizador dentro de retornoConsultarRoteirizador)
                '//ndd:retornoConsultarRoteirizador/ndd:retConsultarRoteirizador/ndd:pracas/ndd:praca',
                '//retornoConsultarRoteirizador/retConsultarRoteirizador/pracas/praca',
                // Alternativa: direto no retConsultarRoteirizador
                '//ndd:retConsultarRoteirizador/ndd:pracas/ndd:praca',
                '//retConsultarRoteirizador/pracas/praca',
                // Fallback genérico
                '//*[local-name()="praca"]',
            ];

            foreach ($xpaths as $xpath) {
                $results = $xml->xpath($xpath);

                if (!empty($results)) {
                    Log::debug("VPO Emissao: Praças encontradas via XPath", [
                        'xpath' => $xpath,
                        'count' => count($results)
                    ]);

                    foreach ($results as $praca) {
                        // CNP é o código da praça no NDD Cargo
                        // Também checar codigoPraca, codigo, id como fallback
                        $cnp = (string) ($praca->cnp ?? '');
                        $codigoPraca = (string) ($praca->codigoPraca ?? '');
                        $codigo = (string) ($praca->codigo ?? '');
                        $id = (string) ($praca->id ?? '');

                        // Usar o primeiro não-vazio
                        $codigoFinal = $cnp ?: $codigoPraca ?: $codigo ?: $id;

                        if (!empty($codigoFinal)) {
                            $pracas[] = [
                                'codigo' => $codigoFinal,
                                'codigoPraca' => $codigoFinal,
                                'nome' => (string) ($praca->nome ?? ''),
                                'valor' => (float) ((string) ($praca->valor ?? 0)),
                                'localizacao' => (string) ($praca->localizacao ?? '')
                            ];
                        }
                    }

                    if (!empty($pracas)) {
                        break;
                    }
                }
            }

            Log::info("VPO Emissao: Praças extraídas do roteirizador", [
                'total' => count($pracas),
                'codigos' => array_column($pracas, 'codigo'),
                'nomes' => array_column($pracas, 'nome')
            ]);

        } catch (\Exception $e) {
            Log::warning("VPO Emissao: Erro ao extrair praças", [
                'error' => $e->getMessage()
            ]);
        }

        return $pracas;
    }

    /**
     * Extrair praças via regex quando XML parsing falha
     * (útil quando resposta vem HTML-encoded)
     *
     * Estrutura esperada (HTML-encoded):
     * &lt;praca&gt;&lt;nome&gt;CORREIA PINTO&lt;/nome&gt;&lt;valor&gt;16.80&lt;/valor&gt;
     * &lt;localizacao&gt;BR-116&lt;/localizacao&gt;&lt;cnp&gt;42011162331000103&lt;/cnp&gt;&lt;/praca&gt;
     */
    protected function extrairPracasViaRegex(string $response): array
    {
        $pracas = [];

        // Padrão para extrair cada <praca> completa (HTML-encoded)
        $pracaPattern = '/&lt;praca&gt;(.*?)&lt;\/praca&gt;/s';

        if (preg_match_all($pracaPattern, $response, $pracaMatches)) {
            foreach ($pracaMatches[1] as $pracaContent) {
                $praca = [];

                // Extrair CNP (obrigatório)
                if (preg_match('/&lt;cnp&gt;([^&]+)&lt;\/cnp&gt;/', $pracaContent, $m)) {
                    $praca['codigo'] = trim($m[1]);
                    $praca['cnp'] = trim($m[1]);
                }

                // Extrair nome
                if (preg_match('/&lt;nome&gt;([^&]+)&lt;\/nome&gt;/', $pracaContent, $m)) {
                    $praca['nome'] = trim($m[1]);
                }

                // Extrair valor
                if (preg_match('/&lt;valor&gt;([^&]+)&lt;\/valor&gt;/', $pracaContent, $m)) {
                    $praca['valor'] = (float) trim($m[1]);
                }

                // Extrair localização (rodovia)
                if (preg_match('/&lt;localizacao&gt;([^&]+)&lt;\/localizacao&gt;/', $pracaContent, $m)) {
                    $praca['localizacao'] = trim($m[1]);
                }

                // Só adiciona se tiver CNP
                if (!empty($praca['codigo'])) {
                    $pracas[] = $praca;
                }
            }

            if (!empty($pracas)) {
                Log::info("VPO Emissao: Praças extraídas via regex (HTML-encoded)", [
                    'total' => count($pracas),
                    'codigos' => array_column($pracas, 'codigo')
                ]);
                return $pracas;
            }
        }

        // Fallback: XML normal (não encoded)
        $pracaPatternXml = '/<praca>(.*?)<\/praca>/s';

        if (preg_match_all($pracaPatternXml, $response, $pracaMatches)) {
            foreach ($pracaMatches[1] as $pracaContent) {
                $praca = [];

                if (preg_match('/<cnp>([^<]+)<\/cnp>/', $pracaContent, $m)) {
                    $praca['codigo'] = trim($m[1]);
                    $praca['cnp'] = trim($m[1]);
                }
                if (preg_match('/<nome>([^<]+)<\/nome>/', $pracaContent, $m)) {
                    $praca['nome'] = trim($m[1]);
                }
                if (preg_match('/<valor>([^<]+)<\/valor>/', $pracaContent, $m)) {
                    $praca['valor'] = (float) trim($m[1]);
                }
                if (preg_match('/<localizacao>([^<]+)<\/localizacao>/', $pracaContent, $m)) {
                    $praca['localizacao'] = trim($m[1]);
                }

                if (!empty($praca['codigo'])) {
                    $pracas[] = $praca;
                }
            }

            if (!empty($pracas)) {
                Log::info("VPO Emissao: Praças extraídas via regex (XML normal)", [
                    'total' => count($pracas),
                    'codigos' => array_column($pracas, 'codigo')
                ]);
            }
        }

        return $pracas;
    }

    /**
     * Retorna categoria de pedágio baseado no tipo de veículo
     */
    protected function getCategoriaPedagioFromTipo(?string $tipoVeiculo): int
    {
        $tipoLower = strtolower($tipoVeiculo ?? '');

        if (str_contains($tipoLower, 'moto')) {
            return 1;
        }
        if (str_contains($tipoLower, 'auto') || str_contains($tipoLower, 'carro')) {
            return 2;
        }
        if (str_contains($tipoLower, 'truck') || str_contains($tipoLower, '3/4')) {
            return 3;
        }
        if (str_contains($tipoLower, 'toco')) {
            return 4;
        }
        if (str_contains($tipoLower, 'carreta')) {
            return 6;
        }

        // Default: 3 (2 eixos - caminhão leve)
        return 3;
    }

    /**
     * Carrega certificado digital (cache de 1 hora)
     *
     * @throws \Exception Se certificado não puder ser carregado
     */
    protected function loadCertificate(): void
    {
        // Verificar se já está carregado
        $cacheKey = 'nddcargo_certificate_loaded';

        // Verificar se está em cache (flag)
        if (Cache::has($cacheKey) && $this->digitalSignature !== null) {
            // Certificado já carregado
            return;
        }

        // Primeira carga (com validação)
        Log::info('VPO Emissao: Carregando certificado digital NDD Cargo');

        $this->digitalSignature = new DigitalSignature();

        $certType = config('nddcargo.certificate_type', 'pfx');

        if ($certType === 'pfx') {
            $this->digitalSignature->loadFromPfx(
                config('nddcargo.certificate_pfx_path'),
                config('nddcargo.certificate_password')
            );
        } else {
            $this->digitalSignature->loadFromPem(
                config('nddcargo.certificate_cert_path'),
                config('nddcargo.certificate_key_path'),
                config('nddcargo.certificate_key_password')
            );
        }

        // Marcar como carregado (cache de 1 hora)
        Cache::put($cacheKey, true, now()->addHour());

        Log::info('VPO Emissao: Certificado carregado com sucesso');
    }

    /**
     * Verificar se processo concluiu com sucesso
     */
    protected function isProcessoConcluido($response): bool
    {
        return isset($response['status']) && $response['status'] === 'concluido';
    }

    /**
     * Verificar se processo teve erro
     */
    protected function isProcessoComErro($response): bool
    {
        return isset($response['status']) && $response['status'] === 'erro';
    }

    /**
     * Extrair mensagem de erro da resposta
     */
    protected function extrairMensagemErro($response): string
    {
        return $response['mensagem'] ?? $response['erro'] ?? 'Erro desconhecido na NDD Cargo';
    }

    /**
     * Processar resultado concluido (extrair pracas, custos, etc)
     */
    protected function processarResultadoConcluido(VpoEmissao $emissao, array $response): void
    {
        $pracas = $response['pracas'] ?? $response['pracas_pedagio'] ?? [];
        $custo = $response['valor_total'] ?? $response['custo_total'] ?? 0;
        $distancia = $response['distancia_km'] ?? 0;
        $tempo = $response['tempo_minutos'] ?? 0;

        $emissao->update([
            'pracas_pedagio' => $pracas,
            'total_pracas' => count($pracas),
            'custo_total' => $custo,
            'distancia_km' => $distancia,
            'tempo_minutos' => $tempo,
        ]);

        $emissao->markAsCompleted($response);

        Log::info("VPO Emissao: Processamento concluido", [
            'emissao_id' => $emissao->id,
            'uuid' => $emissao->uuid,
            'total_pracas' => count($pracas),
            'custo_total' => $custo
        ]);
    }

    /**
     * Valida se TODOS os campos obrigatórios estão preenchidos
     *
     * @param VpoTransportadorCache $vpoCache
     * @return array ['valido' => bool, 'mensagem' => string, 'campos_faltantes' => array]
     */
    protected function validarCamposObrigatorios(VpoTransportadorCache $vpoCache): array
    {
        $camposObrigatorios = [
            // Transportador (5 campos)
            'cpf_cnpj' => 'CPF/CNPJ do transportador',
            'antt_rntrc' => 'Código RNTRC (Registro ANTT)',
            'antt_nome' => 'Razão social (ANTT)',
            'antt_validade' => 'Data de validade do RNTRC',
            'antt_status' => 'Status do RNTRC na ANTT',

            // Veículo (3 campos)
            'placa' => 'Placa do veículo',
            'veiculo_tipo' => 'Tipo do veículo',
            'veiculo_modelo' => 'Modelo do veículo',

            // Condutor (5 campos)
            'condutor_rg' => 'RG do condutor',
            'condutor_nome' => 'Nome completo do condutor',
            'condutor_sexo' => 'Sexo do condutor',
            'condutor_nome_mae' => 'Nome da mãe do condutor',
            'condutor_data_nascimento' => 'Data de nascimento do condutor',

            // Endereço (4 campos)
            'endereco_rua' => 'Endereço (rua)',
            'endereco_bairro' => 'Bairro',
            'endereco_cidade' => 'Cidade',
            'endereco_estado' => 'Estado (UF)',

            // Contato (2 campos)
            'contato_celular' => 'Telefone celular',
            'contato_email' => 'Email de contato',
        ];

        $camposFaltantes = [];
        $vpoData = $vpoCache->toVpoArray();

        foreach ($camposObrigatorios as $campo => $descricao) {
            $valor = $vpoData[$campo] ?? null;

            // Considera vazio: null, string vazia, string só com espaços
            if ($valor === null || $valor === '' || trim((string) $valor) === '') {
                $camposFaltantes[] = [
                    'campo' => $campo,
                    'descricao' => $descricao,
                    'categoria' => $this->getCategoriaCampo($campo)
                ];
            }
        }

        // Se tem campos faltantes, retorna inválido
        if (!empty($camposFaltantes)) {
            $mensagem = $this->construirMensagemValidacao($camposFaltantes, $vpoCache);

            return [
                'valido' => false,
                'mensagem' => $mensagem,
                'campos_faltantes' => $camposFaltantes,
                'total_campos_faltantes' => count($camposFaltantes),
                'score_qualidade' => $vpoCache->score_qualidade
            ];
        }

        // Tudo OK!
        return [
            'valido' => true,
            'mensagem' => 'Todos os campos obrigatórios estão preenchidos',
            'campos_faltantes' => [],
            'total_campos_faltantes' => 0,
            'score_qualidade' => $vpoCache->score_qualidade
        ];
    }

    /**
     * Retorna categoria do campo para agrupamento na mensagem
     */
    protected function getCategoriaCampo(string $campo): string
    {
        if (str_starts_with($campo, 'antt_') || $campo === 'cpf_cnpj') {
            return 'Transportador';
        }

        if (str_starts_with($campo, 'veiculo_') || $campo === 'placa') {
            return 'Veículo';
        }

        if (str_starts_with($campo, 'condutor_')) {
            return 'Condutor';
        }

        if (str_starts_with($campo, 'endereco_')) {
            return 'Endereço';
        }

        if (str_starts_with($campo, 'contato_')) {
            return 'Contato';
        }

        return 'Outros';
    }

    /**
     * Constrói mensagem de validação amigável e detalhada
     */
    protected function construirMensagemValidacao(array $camposFaltantes, VpoTransportadorCache $vpoCache): string
    {
        $totalCampos = count($camposFaltantes);
        $score = $vpoCache->score_qualidade;

        // Agrupar por categoria
        $porCategoria = [];
        foreach ($camposFaltantes as $campo) {
            $categoria = $campo['categoria'];
            if (!isset($porCategoria[$categoria])) {
                $porCategoria[$categoria] = [];
            }
            $porCategoria[$categoria][] = $campo['descricao'];
        }

        // Construir mensagem
        $mensagem = "Não é possível emitir Vale Pedágio (VPO). ";
        $mensagem .= "Faltam {$totalCampos} campos obrigatórios (Score: {$score}/100).\n\n";
        $mensagem .= "Por favor, cadastre os seguintes dados:\n\n";

        foreach ($porCategoria as $categoria => $campos) {
            $mensagem .= "• {$categoria}:\n";
            foreach ($campos as $descricao) {
                $mensagem .= "  - {$descricao}\n";
            }
            $mensagem .= "\n";
        }

        $mensagem .= "Após cadastrar os dados, sincronize novamente e tente a emissão.";

        return $mensagem;
    }
}
