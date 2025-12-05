<?php

namespace App\Services\NddCargo;

use App\Services\NddCargo\DTOs\ConsultarRoteirizadorRequest;
use App\Services\NddCargo\DTOs\RoteirizadorResponse;
use App\Services\NddCargo\XmlBuilders\RoteirizadorBuilder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Serviço de alto nível para integração com NDD Cargo
 *
 * Orquestra o fluxo completo:
 * 1. Carregamento de certificado digital
 * 2. Construção de XML de negócio
 * 3. Assinatura digital RSA-SHA1
 * 4. Encapsulamento em SOAP CrossTalk
 * 5. Envio via HTTP POST
 * 6. Processamento de resposta
 *
 * Este serviço é o ponto de entrada para controllers e outros serviços
 * que precisam integrar com a API NDD Cargo
 *
 * @see docs/integracoes/ndd-cargo/README.md
 * @see docs/integracoes/ndd-cargo/INDEX.md
 */
class NddCargoService
{
    /**
     * Cache TTL para certificados (1 hora)
     */
    private const CERT_CACHE_TTL = 3600;

    /**
     * @var NddCargoSoapClient Cliente SOAP de baixo nível
     */
    private NddCargoSoapClient $soapClient;

    /**
     * @var RoteirizadorBuilder Builder de XML
     */
    private RoteirizadorBuilder $xmlBuilder;

    /**
     * @var DigitalSignature|null Serviço de assinatura digital
     */
    private ?DigitalSignature $digitalSignature = null;

    public function __construct(
        NddCargoSoapClient $soapClient,
        RoteirizadorBuilder $xmlBuilder
    ) {
        $this->soapClient = $soapClient;
        $this->xmlBuilder = $xmlBuilder;
    }

    /**
     * Consulta roteirizador para calcular rota e praças de pedágio
     *
     * @param ConsultarRoteirizadorRequest $request
     * @return RoteirizadorResponse
     */
    public function consultarRoteirizador(ConsultarRoteirizadorRequest $request): RoteirizadorResponse
    {
        try {
            Log::info('Iniciando consulta de roteirizador NDD Cargo', [
                'cnpj_empresa' => $request->cnpjEmpresa,
                'pontos' => $request->pontosParada
            ]);

            // 1. Carregar certificado digital (com cache)
            $this->loadCertificate();

            // 2. Construir XML de negócio
            $xmlData = $this->xmlBuilder->build($request);
            $xml = $xmlData['xml'];
            $uuid = $xmlData['uuid'];

            Log::debug('XML de negócio construído', [
                'uuid' => $uuid,
                'size_bytes' => strlen($xml)
            ]);

            // 3. Assinar XML digitalmente
            $xmlAssinado = $this->digitalSignature->signXml($xml, $uuid);

            Log::debug('XML assinado digitalmente', [
                'uuid' => $uuid,
                'size_bytes' => strlen($xmlAssinado)
            ]);

            // 4. Enviar via SOAP
            $soapResponse = $this->soapClient->consultarRoteirizador($xmlAssinado, $uuid);

            if (!$soapResponse['success']) {
                Log::error('Erro na comunicação SOAP', [
                    'erro' => $soapResponse['error']
                ]);

                return RoteirizadorResponse::error(
                    status: -1,
                    mensagem: 'Erro na comunicação com NDD Cargo: ' . $soapResponse['error']
                );
            }

            // 5. Processar resposta
            $sendResult = $soapResponse['data'];

            if (empty($sendResult)) {
                Log::warning('SendResult vazio na resposta');

                return RoteirizadorResponse::error(
                    status: -2,
                    mensagem: 'Resposta vazia da NDD Cargo'
                );
            }

            // 6. Parse XML de resposta
            $response = RoteirizadorResponse::fromXml($sendResult);

            Log::info('Consulta de roteirizador concluída', [
                'uuid' => $uuid,
                'sucesso' => $response->sucesso,
                'quantidade_pracas' => count($response->pracasPedagio),
                'distancia_km' => $response->distanciaKm
            ]);

            return $response;

        } catch (\Exception $e) {
            Log::error('Erro ao consultar roteirizador', [
                'erro' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return RoteirizadorResponse::error(
                status: -999,
                mensagem: 'Erro interno: ' . $e->getMessage()
            );
        }
    }

    /**
     * Consulta resultado de operação assíncrona
     *
     * @param string $guid UUID da transação original
     * @return RoteirizadorResponse
     */
    public function consultarResultado(string $guid): RoteirizadorResponse
    {
        try {
            Log::info('Consultando resultado assíncrono NDD Cargo', [
                'guid' => $guid
            ]);

            // Enviar consulta via SOAP (ExchangePattern 8, rawData vazio)
            $soapResponse = $this->soapClient->consultarResultado($guid);

            if (!$soapResponse['success']) {
                Log::error('Erro na comunicação SOAP', [
                    'erro' => $soapResponse['error']
                ]);

                return RoteirizadorResponse::error(
                    status: -1,
                    mensagem: 'Erro na comunicação com NDD Cargo: ' . $soapResponse['error']
                );
            }

            // Processar resposta
            $sendResult = $soapResponse['data'];

            if (empty($sendResult)) {
                Log::warning('SendResult vazio na resposta');

                return RoteirizadorResponse::error(
                    status: -2,
                    mensagem: 'Resultado ainda não disponível ou GUID inválido'
                );
            }

            // Parse XML de resposta
            $response = RoteirizadorResponse::fromXml($sendResult);

            Log::info('Consulta de resultado concluída', [
                'guid' => $guid,
                'sucesso' => $response->sucesso
            ]);

            return $response;

        } catch (\Exception $e) {
            Log::error('Erro ao consultar resultado', [
                'guid' => $guid,
                'erro' => $e->getMessage()
            ]);

            return RoteirizadorResponse::error(
                status: -999,
                mensagem: 'Erro interno: ' . $e->getMessage()
            );
        }
    }

    /**
     * Consulta simplificada (apenas CEPs origem/destino)
     *
     * @param string $cepOrigem
     * @param string $cepDestino
     * @param int $categoriaPedagio
     * @return RoteirizadorResponse
     */
    public function consultarRotaSimples(
        string $cepOrigem,
        string $cepDestino,
        int $categoriaPedagio = 7
    ): RoteirizadorResponse {
        $request = new ConsultarRoteirizadorRequest(
            cnpjEmpresa: config('nddcargo.cnpj_empresa'),
            cnpjContratante: config('nddcargo.cnpj_empresa'),
            categoriaPedagio: $categoriaPedagio,
            pontosParada: [
                'origem' => $cepOrigem,
                'destino' => $cepDestino
            ]
        );

        return $this->consultarRoteirizador($request);
    }

    /**
     * Testa conectividade com API NDD Cargo
     *
     * Faz uma consulta simples para validar:
     * - Certificado digital
     * - Credenciais (CNPJ + Token)
     * - Conectividade com endpoint
     *
     * @return array ['success' => bool, 'message' => string, 'details' => array]
     */
    public function testConnection(): array
    {
        try {
            // Testar carregamento de certificado
            $this->loadCertificate();

            Log::info('Teste de conexão NDD Cargo: certificado OK');

            // Fazer consulta simples (São Paulo → Rio de Janeiro)
            $response = $this->consultarRotaSimples(
                cepOrigem: '01310100', // Av Paulista, SP
                cepDestino: '20040020' // Centro, Rio de Janeiro
            );

            if ($response->sucesso) {
                return [
                    'success' => true,
                    'message' => 'Conexão com NDD Cargo OK',
                    'details' => [
                        'certificado' => 'Válido',
                        'credenciais' => 'Válidas',
                        'endpoint' => config('nddcargo.endpoint_url'),
                        'distancia_teste_km' => $response->distanciaKm,
                        'quantidade_pracas_teste' => count($response->pracasPedagio)
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Erro na consulta: ' . $response->mensagem,
                    'details' => [
                        'status' => $response->status,
                        'mensagem' => $response->mensagem
                    ]
                ];
            }

        } catch (\Exception $e) {
            Log::error('Erro no teste de conexão NDD Cargo', [
                'erro' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Erro no teste de conexão: ' . $e->getMessage(),
                'details' => [
                    'erro' => $e->getMessage()
                ]
            ];
        }
    }

    /**
     * Carrega certificado digital (com cache)
     *
     * @throws \Exception Se não conseguir carregar
     */
    private function loadCertificate(): void
    {
        // Se já está carregado, não recarregar
        if ($this->digitalSignature !== null) {
            return;
        }

        $cacheKey = 'nddcargo_certificate_loaded';

        // Verificar se está em cache (flag)
        if (Cache::has($cacheKey)) {
            // Recarregar certificado (mas não refazer validação pesada)
            $this->digitalSignature = new DigitalSignature();

            $certType = config('nddcargo.certificate_type', 'pfx');

            if ($certType === 'pfx') {
                $this->digitalSignature->loadFromPfx(
                    pfxPath: config('nddcargo.certificate_pfx_path'),
                    password: config('nddcargo.certificate_password')
                );
            } else {
                $this->digitalSignature->loadFromPem(
                    certPath: config('nddcargo.certificate_cert_path'),
                    keyPath: config('nddcargo.certificate_key_path'),
                    keyPassword: config('nddcargo.certificate_password')
                );
            }

            return;
        }

        // Primeira carga (com validação)
        Log::info('Carregando certificado digital NDD Cargo');

        $this->digitalSignature = new DigitalSignature();

        $certType = config('nddcargo.certificate_type', 'pfx');

        if ($certType === 'pfx') {
            $this->digitalSignature->loadFromPfx(
                pfxPath: config('nddcargo.certificate_pfx_path'),
                password: config('nddcargo.certificate_password')
            );
        } else {
            $this->digitalSignature->loadFromPem(
                certPath: config('nddcargo.certificate_cert_path'),
                keyPath: config('nddcargo.certificate_key_path'),
                keyPassword: config('nddcargo.certificate_password')
            );
        }

        // Marcar como carregado em cache
        Cache::put($cacheKey, true, self::CERT_CACHE_TTL);

        Log::info('Certificado digital carregado e validado com sucesso');
    }
}
