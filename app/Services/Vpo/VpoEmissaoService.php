<?php

namespace App\Services\Vpo;

use App\Models\VpoEmissao;
use App\Models\VpoTransportadorCache;
use App\Services\ProgressService;
use App\Services\NddCargo\XmlBuilders\VpoXmlBuilder;
use App\Services\NddCargo\NddCargoSoapClient;
use Illuminate\Support\Facades\Log;
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

    public function __construct(
        ProgressService $progressService,
        NddCargoSoapClient $nddCargoSoapClient,
        VpoDataSyncService $vpoSyncService,
        VpoXmlBuilder $vpoXmlBuilder
    ) {
        $this->progressService = $progressService;
        $this->nddCargoSoapClient = $nddCargoSoapClient;
        $this->vpoSyncService = $vpoSyncService;
        $this->vpoXmlBuilder = $vpoXmlBuilder;
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

            // Consultar NDD Cargo via SOAP (passar processCode para VPO: 2028)
            $consultaResult = $this->nddCargoSoapClient->consultarResultado($emissao->uuid, 2028);

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

            foreach ($rotaMunicipios['data']['municipios'] ?? [] as $mun) {
                if (isset($mun['latitude']) && isset($mun['longitude'])) {
                    $waypoints[] = [
                        'lat' => (float) $mun['latitude'],
                        'lon' => (float) $mun['longitude'],
                        'tipo' => 'rota',
                        'nome' => $mun['desMun'] ?? ''
                    ];
                }
            }

            // 3. Primeira + ultima entrega
            $itinerario = $this->progressService->getItinerarioPacote($codpac);

            if ($itinerario['success'] && !empty($itinerario['data']['pedidos'])) {
                $entregas = $itinerario['data']['pedidos'];

                // Primeira
                $primeira = $entregas[0];
                if (isset($primeira['gps_lat']) && isset($primeira['gps_lon'])) {
                    $waypoints[] = [
                        'lat' => $this->processGpsCoordinate($primeira['gps_lat']),
                        'lon' => $this->processGpsCoordinate($primeira['gps_lon']),
                        'tipo' => 'primeira_entrega',
                        'nome' => $primeira['razcli'] ?? ''
                    ];
                }

                // Ultima
                $ultima = end($entregas);
                if (isset($ultima['gps_lat']) && isset($ultima['gps_lon'])) {
                    $waypoints[] = [
                        'lat' => $this->processGpsCoordinate($ultima['gps_lat']),
                        'lon' => $this->processGpsCoordinate($ultima['gps_lon']),
                        'tipo' => 'ultima_entrega',
                        'nome' => $ultima['razcli'] ?? ''
                    ];
                }
            }

            return ['success' => true, 'data' => ['id' => $rotaId, 'nome' => $rotaNome, 'waypoints' => $waypoints], 'error' => null];

        } catch (\Exception $e) {
            return ['success' => false, 'data' => null, 'error' => $e->getMessage()];
        }
    }

    protected function processGpsCoordinate(?string $gpsString): ?float
    {
        if (!$gpsString || $gpsString === '0') return null;

        $value = (int) $gpsString;
        if ($value === 0) return null;

        $isNegative = ($gpsString[0] === '2' || $gpsString[0] === '3');
        $decimal = abs($value) / 10_000_000;

        return $isNegative ? -$decimal : $decimal;
    }

    /**
     * Enviar para NDD Cargo (construir XML + SOAP)
     */
    protected function enviarParaNddCargo(VpoEmissao $emissao): array
    {
        try {
            // 1. Construir XML VPO
            $vpoData = $emissao->getVpoData();
            $waypoints = $emissao->waypoints;

            $xmlData = $this->vpoXmlBuilder->build($vpoData, $waypoints);
            $xml = $xmlData['xml'];
            $uuid = $xmlData['uuid'];

            Log::debug("VPO Emissao: XML construido", ['uuid' => $uuid, 'size_bytes' => strlen($xml)]);

            // 2. Enviar via SOAP (usando metodo generico do NddCargoSoapClient)
            $soapResponse = $this->nddCargoSoapClient->emitirVPO($xml, $uuid);

            if (!$soapResponse['success']) {
                return [
                    'success' => false,
                    'uuid' => null,
                    'xml_enviado' => $xml,
                    'error' => 'Erro SOAP: ' . ($soapResponse['error'] ?? 'Desconhecido')
                ];
            }

            // 3. Validar UUID na resposta
            if (empty($soapResponse['data']['uuid'])) {
                return [
                    'success' => false,
                    'uuid' => null,
                    'xml_enviado' => $xml,
                    'error' => 'UUID nao retornado pela NDD Cargo'
                ];
            }

            return [
                'success' => true,
                'uuid' => $soapResponse['data']['uuid'],
                'xml_enviado' => $xml,
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
}
