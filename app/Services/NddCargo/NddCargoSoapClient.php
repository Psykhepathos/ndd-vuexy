<?php

namespace App\Services\NddCargo;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

/**
 * Cliente SOAP de baixo nível para API NDD Cargo
 *
 * Responsável por:
 * - Construir envelope SOAP com protocolo CrossTalk
 * - Encapsular XML de negócio assinado em CDATA
 * - Enviar requisições HTTP POST para endpoint NDD
 * - Processar respostas SOAP
 *
 * IMPORTANTE: A NDD Cargo usa protocolo proprietário CrossTalk sobre SOAP 1.1:
 * - Encoding: UTF-16 (não UTF-8!)
 * - CDATA sections para message e rawData
 * - Headers específicos: SOAPAction, Content-Type
 *
 * @see docs/integracoes/ndd-cargo/README.md
 * @see docs/integracoes/ndd-cargo/ANALISE_NTESTE_PY.md (linhas 124-224)
 */
class NddCargoSoapClient
{
    /**
     * ProcessCode para Consultar Roteirizador
     */
    private const PROCESS_CODE_ROTEIRIZADOR = 2027;

    /**
     * ProcessCode para Operação Vale Pedágio (OVP)
     * @see docs/NDD-SOAP-API-Documentation.md - ProcessCode 2019
     */
    private const PROCESS_CODE_EMITIR_VPO = 2019;

    /**
     * ProcessCode para Cancelamento de Operação Vale Pedágio (CAVP)
     * @see docs/integracoes/ndd-cargo/CANCELAMENTO_VPO.md
     */
    private const PROCESS_CODE_CANCELAR_VPO = 2020;

    /**
     * MessageType (sempre 100 para Request)
     */
    private const MESSAGE_TYPE_REQUEST = 100;

    /**
     * ExchangePattern: 7 = Síncrono, 8 = Consulta Assíncrona, 9 = Assíncrono
     */
    private const EXCHANGE_PATTERN_SYNC = 7;
    private const EXCHANGE_PATTERN_ASYNC_QUERY = 8;
    private const EXCHANGE_PATTERN_ASYNC = 9;

    /**
     * Namespaces XML
     */
    private const NS_SOAP = 'http://schemas.xmlsoap.org/soap/envelope/';
    private const NS_TEMPURI = 'http://tempuri.org/';
    private const NS_XSD = 'http://www.w3.org/2001/XMLSchema';
    private const NS_XSI = 'http://www.w3.org/2001/XMLSchema-instance';
    private const NS_NDD = 'http://www.nddigital.com.br/nddcargo';

    /**
     * @var string URL do endpoint SOAP
     */
    private string $endpointUrl;

    /**
     * @var string CNPJ da empresa
     */
    private string $cnpjEmpresa;

    /**
     * @var string Token de autenticação
     */
    private string $token;

    /**
     * @var string Versão da API
     */
    private string $versaoLayout;

    /**
     * @var int Timeout em segundos
     */
    private int $timeout;

    public function __construct()
    {
        $this->endpointUrl = config('nddcargo.endpoint_url');
        $this->cnpjEmpresa = config('nddcargo.cnpj_empresa');
        $this->token = config('nddcargo.token');
        $this->versaoLayout = config('nddcargo.versao_layout', '4.2.12.0');
        $this->timeout = config('nddcargo.timeout', 60);
    }

    /**
     * Envia consulta síncrona de roteirizador
     *
     * @param string $xmlAssinado XML de negócio já assinado digitalmente
     * @param string $guid UUID da transação
     * @return array ['success' => bool, 'data' => string|null, 'error' => string|null]
     */
    public function consultarRoteirizador(string $xmlAssinado, string $guid): array
    {
        try {
            // Construir CrossTalk Message
            $crossTalkMessage = $this->buildCrossTalkMessage(
                processCode: self::PROCESS_CODE_ROTEIRIZADOR,
                messageType: self::MESSAGE_TYPE_REQUEST,
                exchangePattern: self::EXCHANGE_PATTERN_SYNC,
                guid: $guid
            );

            // Construir envelope SOAP
            $soapEnvelope = $this->buildSoapEnvelope($crossTalkMessage, $xmlAssinado);

            // Enviar requisição
            return $this->sendSoapRequest($soapEnvelope);

        } catch (\Exception $e) {
            Log::error('Erro ao consultar roteirizador NDD Cargo', [
                'erro' => $e->getMessage(),
                'guid' => $guid
            ]);

            return [
                'success' => false,
                'data' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Envia emissão VPO (síncrono - igual ao roteirizador!)
     *
     * @param string $xmlAssinado XML de negócio já assinado digitalmente (operacaoValePedagio_envio)
     * @param string $guid UUID da transação
     * @return array ['success' => bool, 'data' => array|null, 'error' => string|null]
     *               data contém: ['uuid' => string, 'raw_response' => string]
     */
    public function emitirVPO(string $xmlAssinado, string $guid): array
    {
        try {
            // Construir CrossTalk Message (síncrono - ExchangePattern 7, igual ao roteirizador!)
            $crossTalkMessage = $this->buildCrossTalkMessage(
                processCode: self::PROCESS_CODE_EMITIR_VPO,
                messageType: self::MESSAGE_TYPE_REQUEST,
                exchangePattern: self::EXCHANGE_PATTERN_SYNC,  // 7 = Síncrono (não 9!)
                guid: $guid
            );

            // Construir envelope SOAP
            $soapEnvelope = $this->buildSoapEnvelope($crossTalkMessage, $xmlAssinado);

            // Enviar requisição
            $response = $this->sendSoapRequest($soapEnvelope);

            if (!$response['success']) {
                return $response;
            }

            // Para emissão VPO assíncrona, a resposta deve conter o UUID
            // Extrair UUID da resposta (formato pode variar, ajustar conforme necessário)
            $sendResult = $response['data'];

            Log::info('Emissão VPO enviada com sucesso', [
                'guid' => $guid,
                'response_size' => strlen($sendResult ?? '')
            ]);

            return [
                'success' => true,
                'data' => [
                    'uuid' => $guid,  // NDD Cargo deve retornar o mesmo GUID
                    'raw_response' => $sendResult
                ],
                'error' => null
            ];

        } catch (\Exception $e) {
            Log::error('Erro ao emitir VPO via NDD Cargo', [
                'erro' => $e->getMessage(),
                'guid' => $guid
            ]);

            return [
                'success' => false,
                'data' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Envia cancelamento de VPO (síncrono)
     *
     * @param string $xmlAssinado XML de negócio já assinado digitalmente (cancelarOperacaoValePedagio_envio)
     * @param string $guid UUID da transação
     * @return array ['success' => bool, 'data' => array|null, 'error' => string|null]
     *               data contém: ['uuid' => string, 'raw_response' => string]
     */
    public function cancelarVPO(string $xmlAssinado, string $guid): array
    {
        try {
            // Construir CrossTalk Message (síncrono - ExchangePattern 7)
            $crossTalkMessage = $this->buildCrossTalkMessage(
                processCode: self::PROCESS_CODE_CANCELAR_VPO,
                messageType: self::MESSAGE_TYPE_REQUEST,
                exchangePattern: self::EXCHANGE_PATTERN_SYNC,
                guid: $guid
            );

            // Construir envelope SOAP
            $soapEnvelope = $this->buildSoapEnvelope($crossTalkMessage, $xmlAssinado);

            // Enviar requisição
            $response = $this->sendSoapRequest($soapEnvelope);

            if (!$response['success']) {
                return $response;
            }

            $sendResult = $response['data'];

            Log::info('Cancelamento VPO enviado com sucesso', [
                'guid' => $guid,
                'response_size' => strlen($sendResult ?? '')
            ]);

            return [
                'success' => true,
                'data' => [
                    'uuid' => $guid,
                    'raw_response' => $sendResult
                ],
                'error' => null
            ];

        } catch (\Exception $e) {
            Log::error('Erro ao cancelar VPO via NDD Cargo', [
                'erro' => $e->getMessage(),
                'guid' => $guid
            ]);

            return [
                'success' => false,
                'data' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Consulta resultado de operação assíncrona
     *
     * @param string $guid UUID da transação original
     * @param int|null $processCode Código do processo (null = usa GUID para inferir)
     * @return array ['success' => bool, 'data' => string|null, 'error' => string|null]
     */
    public function consultarResultado(string $guid, ?int $processCode = null): array
    {
        try {
            // Se processCode não foi especificado, tentar inferir do GUID ou usar roteirizador como padrão
            // Na prática, o NDD Cargo geralmente consegue identificar pelo GUID
            $finalProcessCode = $processCode ?? self::PROCESS_CODE_ROTEIRIZADOR;

            // Construir CrossTalk Message (sem rawData)
            $crossTalkMessage = $this->buildCrossTalkMessage(
                processCode: $finalProcessCode,
                messageType: self::MESSAGE_TYPE_REQUEST,
                exchangePattern: self::EXCHANGE_PATTERN_ASYNC_QUERY,
                guid: $guid
            );

            // Construir envelope SOAP (rawData vazio para consulta)
            $soapEnvelope = $this->buildSoapEnvelope($crossTalkMessage, '');

            // Enviar requisição
            $response = $this->sendSoapRequest($soapEnvelope);

            if ($response['success']) {
                Log::info('Resultado consultado com sucesso', [
                    'guid' => $guid,
                    'process_code' => $finalProcessCode
                ]);
            }

            return $response;

        } catch (\Exception $e) {
            Log::error('Erro ao consultar resultado NDD Cargo', [
                'erro' => $e->getMessage(),
                'guid' => $guid,
                'process_code' => $processCode
            ]);

            return [
                'success' => false,
                'data' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Constrói CrossTalk_Message
     *
     * @param int $processCode
     * @param int $messageType
     * @param int $exchangePattern
     * @param string $guid
     * @return string XML da mensagem CrossTalk
     */
    private function buildCrossTalkMessage(
        int $processCode,
        int $messageType,
        int $exchangePattern,
        string $guid
    ): string {
        // Timestamp no formato ISO8601 com timezone brasileiro
        $dateTime = now()->timezone('America/Sao_Paulo')->format('Y-m-d\TH:i:sP');

        // Armazenar constantes em variáveis para usar no heredoc
        $nsXsd = self::NS_XSD;
        $nsXsi = self::NS_XSI;
        $nsNdd = self::NS_NDD;

        $xml = <<<XML
<CrossTalk_Message xmlns:xsd="{$nsXsd}" xmlns:xsi="{$nsXsi}" xmlns="{$nsNdd}">
    <CrossTalk_Header>
        <ProcessCode>{$processCode}</ProcessCode>
        <MessageType>{$messageType}</MessageType>
        <ExchangePattern>{$exchangePattern}</ExchangePattern>
        <GUID>{$guid}</GUID>
        <DateTime>{$dateTime}</DateTime>
        <EnterpriseId>{$this->cnpjEmpresa}</EnterpriseId>
        <Token>{$this->token}</Token>
    </CrossTalk_Header>
    <CrossTalk_Body>
        <CrossTalk_Version_Body versao="{$this->versaoLayout}"/>
    </CrossTalk_Body>
</CrossTalk_Message>
XML;

        return $xml;
    }

    /**
     * Constrói envelope SOAP completo
     *
     * @param string $crossTalkMessage
     * @param string $rawData XML assinado (ou vazio para consulta)
     * @return string XML do envelope SOAP
     */
    private function buildSoapEnvelope(string $crossTalkMessage, string $rawData): string {
        // Escapar CDATA corretamente
        $messageCdata = $this->escapeCdata($crossTalkMessage);
        $rawDataCdata = $this->escapeCdata($rawData);

        // Armazenar constantes em variáveis para usar no heredoc
        $nsSoap = self::NS_SOAP;
        $nsTempuri = self::NS_TEMPURI;

        $envelope = <<<XML
<?xml version='1.0' encoding='utf-16'?>
<soapenv:Envelope xmlns:soapenv="{$nsSoap}" xmlns:tem="{$nsTempuri}">
    <soapenv:Header/>
    <soapenv:Body>
        <tem:Send>
            <tem:message><![CDATA[{$messageCdata}]]></tem:message>
            <tem:rawData><![CDATA[{$rawDataCdata}]]></tem:rawData>
        </tem:Send>
    </soapenv:Body>
</soapenv:Envelope>
XML;

        return $envelope;
    }

    /**
     * Escapa conteúdo para CDATA (remove CDATA aninhado)
     *
     * @param string $content
     * @return string
     */
    private function escapeCdata(string $content): string
    {
        // Remover CDATA aninhado (não permitido em XML)
        return str_replace(['<![CDATA[', ']]>'], ['', ''], $content);
    }

    /**
     * Envia requisição SOAP HTTP POST
     *
     * @param string $soapEnvelope
     * @return array ['success' => bool, 'data' => string|null, 'error' => string|null]
     * @throws \Exception
     */
    private function sendSoapRequest(string $soapEnvelope): array
    {
        // Converter para UTF-16 (CRÍTICO: NDD Cargo exige UTF-16!)
        $soapEnvelopeUtf16 = mb_convert_encoding($soapEnvelope, 'UTF-16LE', 'UTF-8');

        // Sanitizar preview para logs (remover credenciais sensíveis)
        $previewSanitized = $soapEnvelope;
        $previewSanitized = preg_replace(
            '/<Token>.*?<\/Token>/s',
            '<Token>***REDACTED***</Token>',
            $previewSanitized
        );
        $previewSanitized = preg_replace(
            '/<EnterpriseId>.*?<\/EnterpriseId>/s',
            '<EnterpriseId>***REDACTED***</EnterpriseId>',
            $previewSanitized
        );

        // Log (apenas primeiros 500 chars, sanitizado)
        Log::info('Enviando requisição SOAP para NDD Cargo', [
            'endpoint' => $this->endpointUrl,
            'size_bytes' => strlen($soapEnvelopeUtf16),
            'preview' => substr($previewSanitized, 0, 500) . '...'
        ]);

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Content-Type' => 'text/xml; charset=utf-16',
                    'SOAPAction' => 'http://tempuri.org/Send',
                    'Accept' => 'text/xml',
                ])
                ->withBody($soapEnvelopeUtf16, 'text/xml; charset=utf-16')
                ->post($this->endpointUrl);

            if ($response->successful()) {
                // Laravel Http já faz a conversão automática para UTF-8
                $responseBody = $response->body();

                // Log detalhado da resposta (primeiros 2000 chars)
                Log::info('Resposta SOAP recebida com sucesso', [
                    'status' => $response->status(),
                    'size_bytes' => strlen($responseBody),
                    'response_preview' => substr($responseBody, 0, 2000)
                ]);

                // Extrair SendResult do CDATA
                $sendResult = $this->extractSendResult($responseBody);

                // Log do SendResult extraído
                if ($sendResult) {
                    Log::info('SendResult extraído', [
                        'sendResult_size' => strlen($sendResult),
                        'sendResult_preview' => substr($sendResult, 0, 1000)
                    ]);
                } else {
                    Log::warning('SendResult vazio ou nulo', [
                        'response_body' => substr($responseBody, 0, 1000)
                    ]);
                }

                return [
                    'success' => true,
                    'data' => $sendResult,
                    'error' => null
                ];
            } else {
                $errorMessage = "HTTP {$response->status()}: {$response->body()}";

                Log::error('Erro HTTP na requisição SOAP', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                return [
                    'success' => false,
                    'data' => null,
                    'error' => $errorMessage
                ];
            }

        } catch (\Exception $e) {
            Log::error('Exceção ao enviar requisição SOAP', [
                'erro' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Extrai SendResult do envelope SOAP de resposta
     *
     * @param string $soapResponse
     * @return string|null
     */
    private function extractSendResult(string $soapResponse): ?string
    {
        try {
            // Parse XML
            $xml = new \SimpleXMLElement($soapResponse);

            // Registrar namespaces
            $xml->registerXPathNamespace('soap', self::NS_SOAP);
            $xml->registerXPathNamespace('tem', self::NS_TEMPURI);

            // Buscar SendResult
            $sendResultNodes = $xml->xpath('//tem:SendResult');

            if (empty($sendResultNodes)) {
                // Tentar sem namespace
                $sendResultNodes = $xml->xpath('//SendResult');
            }

            if (!empty($sendResultNodes)) {
                $sendResult = (string) $sendResultNodes[0];

                // SendResult pode vir em CDATA, remover se necessário
                $sendResult = str_replace(['<![CDATA[', ']]>'], ['', ''], $sendResult);

                return $sendResult;
            }

            Log::warning('SendResult não encontrado na resposta SOAP');
            return null;

        } catch (\Exception $e) {
            Log::error('Erro ao extrair SendResult', [
                'erro' => $e->getMessage()
            ]);
            return null;
        }
    }
}
