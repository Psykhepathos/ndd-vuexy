<?php

namespace App\Services\SemParar;

use Exception;
use Illuminate\Support\Facades\Log;
use App\Services\SemParar\XmlBuilders\PontosParadaBuilder;

/**
 * SemPararService - High-level wrapper for SemParar business operations
 *
 * Based on Progress Rota.cls (lines 110-606 in SEMPARAR_AI_REFERENCE.md)
 * Provides business logic methods wrapping SOAP client calls
 */
class SemPararService
{
    /**
     * Low-level SOAP client
     */
    protected SemPararSoapClient $soapClient;

    /**
     * Initialize service with SOAP client
     *
     * CORREÇÃO BUG #19: Timeout de 10s é adequado para SOAP SemParar
     * - Operações normais: 1-3s
     * - Picos de latência: até 8s
     * - Se timeout constante, aumentar para 15s em SemPararSoapClient
     * - Monitorar logs de timeout para ajustes futuros
     */
    public function __construct(SemPararSoapClient $soapClient = null)
    {
        $this->soapClient = $soapClient ?? new SemPararSoapClient();
    }

    /**
     * Verify vehicle status in SemParar system
     *
     * Based on Rota.cls::statusVeiculo() (lines 156-177 in SEMPARAR_AI_REFERENCE.md)
     * Progress code:
     *   RUN VALUE("consultarStatusVeiculo") IN hPorta(
     *     INPUT cToken,
     *     INPUT placa,
     *     OUTPUT xml
     *   )
     *   ASSIGN status = extractContentFromXml(xml, "status")
     *
     * @param string $placa Vehicle license plate (e.g., "ABC1234")
     * @return array Vehicle status information
     * @throws Exception if verification fails
     */
    public function statusVeiculo(string $placa): array
    {
        try {
            Log::info('[SemParar] Verifying vehicle status', ['placa' => $placa]);

            // Call SOAP method obterStatusVeiculo (NOT consultarStatusVeiculo!)
            // WSDL shows: ArrayOf_tns1_Veiculo obterStatusVeiculo(string $placa, long $sessao)
            // Returns: Array of stdClass objects with properties:
            //   - placa, proprietario, descricao, eixos, tag, status (0=ativo)

            // Get token and call directly (positional params, NOT named params!)
            $soapClient = $this->soapClient->getSoapClient();

            // CORREÇÃO BUG #16: Validar token explicitamente
            $token = $this->soapClient->getToken();
            if (!$token) {
                $token = $this->soapClient->autenticarUsuario();
            }
            if (!$token) {
                throw new \Exception('Falha na autenticação SemParar. Token não pôde ser obtido.');
            }

            $response = $soapClient->obterStatusVeiculo(strtoupper(trim($placa)), $token);

            // Response is an array of vehicle objects, not XML
            if (!is_array($response) || empty($response)) {
                Log::warning('[SemParar] Vehicle not found or no data returned', ['placa' => $placa]);
                return [
                    'success' => false,
                    'status' => 'INATIVO',
                    'mensagem' => 'Veículo não encontrado no sistema SemParar',
                    'placa' => $placa,
                    'erro' => 'ERRO'
                ];
            }

            // Get first vehicle from array
            $veiculo = $response[0];

            // Status = 0 means ACTIVE in SemParar API
            $isActive = isset($veiculo->status) && (int)$veiculo->status === 0;
            $statusText = $isActive ? 'ATIVO' : 'INATIVO';

            Log::info('[SemParar] Vehicle status retrieved', [
                'placa' => $placa,
                'status' => $statusText,
                'is_active' => $isActive,
                'proprietario' => $veiculo->proprietario ?? null,
                'eixos' => $veiculo->eixos ?? null,
                'tag' => $veiculo->tag ?? null
            ]);

            return [
                'success' => $isActive,
                'status' => $statusText,
                'mensagem' => $isActive ? 'Veículo ativo no sistema' : 'Veículo inativo',
                'placa' => $placa,
                'erro' => $isActive ? 'OK' : 'ERRO',
                'dados_veiculo' => [
                    'proprietario' => $veiculo->proprietario ?? null,
                    'descricao' => $veiculo->descricao ?? null,
                    'eixos' => $veiculo->eixos ?? null,
                    'tag' => $veiculo->tag ?? null
                ]
            ];
        } catch (Exception $e) {
            Log::error('[SemParar] Vehicle status verification failed', [
                'placa' => $placa,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'status' => 'ERRO',
                'mensagem' => $e->getMessage(),
                'placa' => $placa,
                'erro' => 'ERRO'
            ];
        }
    }

    /**
     * Test SemParar connection and authentication
     *
     * @return array Test results
     */
    public function testConnection(): array
    {
        return $this->soapClient->testConnection();
    }

    /**
     * Get current session token (for debugging)
     *
     * @return string|null
     */
    public function getToken(): ?string
    {
        return $this->soapClient->getToken();
    }

    /**
     * Clear cached token (force re-authentication)
     */
    public function clearCache(): void
    {
        $this->soapClient->clearCachedToken();
    }

    /**
     * Roteirizar praças de pedágio
     *
     * Based on Rota.cls::roterizaCa() STEP 6 (SEMPARAR_AI_REFERENCE.md lines 179-194)
     * Progress: RUN VALUE("roteirizarPracasPedagio") IN hPorta(...)
     *
     * WSDL: InfoRoteirizacao roteirizarPracasPedagio(PontosParada $pontosParada, OpcoesRota $opcoesRota, long $sessao)
     *
     * @param array $pontos Array of points: [['cod_ibge' => 123, 'desc' => 'City', 'latitude' => 0, 'longitude' => 0]]
     * @param bool $alternativas Whether to return alternative routes
     * @return array Parsed toll plazas
     * @throws Exception if SOAP call fails
     */
    public function roteirizarPracasPedagio(array $pontos, bool $alternativas = false): array
    {
        try {
            Log::info('[SemParar] Roteirizando praças de pedágio', [
                'total_pontos' => count($pontos),
                'alternativas' => $alternativas
            ]);

            // Build XML datasets
            $pontosXml = PontosParadaBuilder::buildPontosParadaXml($pontos);
            $opcoesXml = PontosParadaBuilder::buildOpcoesRotaXml($alternativas);

            // Get token and call directly (positional params!)
            $soapClient = $this->soapClient->getSoapClient();

            // CORREÇÃO BUG #16: Validar token explicitamente
            $token = $this->soapClient->getToken();
            if (!$token) {
                $token = $this->soapClient->autenticarUsuario();
            }
            if (!$token) {
                throw new \Exception('Falha na autenticação SemParar. Token não pôde ser obtido.');
            }

            Log::debug('[SemParar] Calling roteirizarPracasPedagio', [
                'pontos_xml_length' => strlen($pontosXml),
                'opcoes_xml_length' => strlen($opcoesXml),
                'token_length' => strlen($token)
            ]);

            // WSDL signature: roteirizarPracasPedagio(PontosParada, OpcoesRota, sessao)
            // Must send XML as SoapVar with XSD_ANYXML type (Progress sends dataset XML)
            $pontosParam = new \SoapVar($pontosXml, XSD_ANYXML);
            $opcoesParam = new \SoapVar($opcoesXml, XSD_ANYXML);

            $response = $soapClient->roteirizarPracasPedagio(
                $pontosParam,
                $opcoesParam,
                $token
            );

            // Log SOAP request/response for debugging
            Log::debug('[SemParar] SOAP Request', [
                'request' => $soapClient->__getLastRequest()
            ]);
            Log::debug('[SemParar] SOAP Response', [
                'response' => $soapClient->__getLastResponse()
            ]);

            // Parse response
            $pracas = PontosParadaBuilder::parsePracaPedagio($response);

            // Check status
            $status = $response->status ?? 0;
            if ($status !== 0) {
                Log::error('[SemParar] Roteirização retornou erro', [
                    'status' => $status,
                    'status_mensagem' => $response->statusMensagem ?? null
                ]);

                throw new Exception("Erro SemParar status {$status}: " . ($response->statusMensagem ?? 'Erro desconhecido'));
            }

            Log::info('[SemParar] Roteirização concluída', [
                'total_pracas' => count($pracas)
            ]);

            return [
                'success' => true,
                'pracas' => $pracas,
                'total' => count($pracas),
                'status' => $status
            ];

        } catch (Exception $e) {
            Log::error('[SemParar] Erro ao roteirizar praças', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'pracas' => []
            ];
        }
    }

    /**
     * Cadastrar rota temporária
     *
     * Based on Rota.cls::roterizaCa() STEP 9 (SEMPARAR_AI_REFERENCE.md lines 204-212)
     * Progress: RUN VALUE("cadastrarRotaTemporaria") IN hPorta(...)
     *
     * WSDL: InfoRota cadastrarRotaTemporaria(ArrayOf_xsd_int $pracas, string $nome, long $sessao)
     *
     * @param array $pracaIds Array of toll plaza IDs (integers)
     * @param string $nomeRota Route name
     * @return array Result with 'id' (route code) and 'nome' (route name)
     * @throws Exception if SOAP call fails
     */
    public function cadastrarRotaTemporaria(array $pracaIds, string $nomeRota): array
    {
        try {
            Log::info('[SemParar] Cadastrando rota temporária', [
                'nome_rota' => $nomeRota,
                'total_pracas' => count($pracaIds)
            ]);

            // Get token and call directly
            $soapClient = $this->soapClient->getSoapClient();

            // CORREÇÃO BUG #16: Validar token explicitamente
            $token = $this->soapClient->getToken();
            if (!$token) {
                $token = $this->soapClient->autenticarUsuario();
            }
            if (!$token) {
                throw new \Exception('Falha na autenticação SemParar. Token não pôde ser obtido.');
            }

            // WSDL signature: cadastrarRotaTemporaria(ArrayOf_xsd_int, string, sessao)
            // PHP SoapClient automatically converts array to SOAP array type
            $response = $soapClient->cadastrarRotaTemporaria(
                $pracaIds,
                $nomeRota,
                $token
            );

            // Response is InfoRota object with properties: id, nome, status
            $codRotaSemParar = $response->id ?? null;
            $nomeRotaSemParar = $response->nome ?? $nomeRota;
            $status = $response->status ?? 0;

            if ($status !== 0 || !$codRotaSemParar) {
                throw new Exception("Erro ao cadastrar rota temporária. Status: {$status}");
            }

            Log::info('[SemParar] Rota temporária cadastrada', [
                'cod_rota_semparar' => $codRotaSemParar,
                'nome_rota_semparar' => $nomeRotaSemParar
            ]);

            return [
                'success' => true,
                'cod_rota_semparar' => $codRotaSemParar,
                'nome_rota_semparar' => $nomeRotaSemParar,
                'status' => $status
            ];

        } catch (Exception $e) {
            Log::error('[SemParar] Erro ao cadastrar rota temporária', [
                'nome_rota' => $nomeRota,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obter custo da rota
     *
     * Based on Progress compraRota.p verification flow (COMPRA_VIAGEM_ERROS.md)
     * WSDL: CustoRota obterCustoRota(string $nomeRota, string $placa, int $nEixos, date $inicioVigencia, date $fimVigencia, long $sessao)
     *
     * @param string $nomeRota Route name (from cadastrarRotaTemporaria)
     * @param string $placa License plate
     * @param int $eixos Number of axles
     * @param string $dataInicio Start date (Y-m-d)
     * @param string $dataFim End date (Y-m-d)
     * @return array Result with 'valor' (float) and details
     * @throws Exception if SOAP call fails
     */
    public function obterCustoRota(
        string $nomeRota,
        string $placa,
        int $eixos,
        string $dataInicio,
        string $dataFim
    ): array {
        try {
            Log::info('[SemParar] Obtendo custo da rota', [
                'nome_rota' => $nomeRota,
                'placa' => $placa,
                'eixos' => $eixos,
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim
            ]);

            // Get token and call directly
            $soapClient = $this->soapClient->getSoapClient();

            // CORREÇÃO BUG #16: Validar token explicitamente
            $token = $this->soapClient->getToken();
            if (!$token) {
                $token = $this->soapClient->autenticarUsuario();
            }
            if (!$token) {
                throw new \Exception('Falha na autenticação SemParar. Token não pôde ser obtido.');
            }

            // WSDL signature: obterCustoRota(nomeRota, placa, nEixos, inicioVigencia, fimVigencia, sessao)
            $response = $soapClient->obterCustoRota(
                $nomeRota,
                strtoupper(trim($placa)),
                $eixos,
                $dataInicio,
                $dataFim,
                $token
            );

            // Response is CustoRota object with properties: valor, status, etc
            $valor = $response->valor ?? 0;
            $status = $response->status ?? 0;

            if ($status !== 0) {
                throw new Exception("Erro ao obter custo da rota. Status: {$status}");
            }

            Log::info('[SemParar] Custo da rota obtido', [
                'nome_rota' => $nomeRota,
                'valor' => $valor
            ]);

            return [
                'success' => true,
                'valor' => (float)$valor,
                'nome_rota' => $nomeRota,
                'placa' => $placa,
                'eixos' => $eixos,
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim,
                'status' => $status
            ];

        } catch (Exception $e) {
            Log::error('[SemParar] Erro ao obter custo da rota', [
                'nome_rota' => $nomeRota,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'valor' => 0
            ];
        }
    }

    /**
     * Comprar viagem (efetivar compra no SemParar)
     *
     * Based on Rota.cls::compraViagem() (line 247-273)
     * Progress code:
     *   RUN VALUE("comprarViagem") IN hPorta(
     *     INPUT rota, INPUT placa, INPUT nEixos,
     *     INPUT inicioVigenciaStr, INPUT fimVigenciaStr,
     *     INPUT itemFin1, INPUT "", INPUT "",
     *     INPUT cToken, OUTPUT xml
     *   )
     *
     * WSDL: Viagem comprarViagem(string $nomeRota, string $placa, int $nEixos,
     *                             date $inicioVigencia, date $fimVigencia,
     *                             string $itemFin1, string $itemFin2, string $itemFin3,
     *                             long $sessao)
     *
     * CORREÇÃO BUG #20: Idempotency não implementada
     *
     * ⚠️ LIMITAÇÃO CONHECIDA:
     * Este método NÃO é idempotente. Múltiplos requests simultâneos podem
     * gerar múltiplas compras no SemParar.
     *
     * Para implementar idempotency (futuro):
     * 1. Gerar idempotency_key no frontend (UUID)
     * 2. Salvar em cache antes da compra: Cache::put("viagem:{key}", 'processing', 300)
     * 3. Verificar cache: if (Cache::has("viagem:{key}")) { return cached result }
     * 4. Processar compra
     * 5. Atualizar cache com resultado: Cache::put("viagem:{key}", $result, 86400)
     *
     * Impacto sem idempotency:
     * - Usuário pode gerar múltiplas compras acidentalmente
     * - Rate limiting (10 req/min) mitiga parcialmente o problema
     * - Frontend deve desabilitar botão após click (UX)
     *
     * @param string $nomeRota Route name (from cadastrarRotaTemporaria)
     * @param string $placa License plate
     * @param int $eixos Number of axles
     * @param string $dataInicio Start date (Y-m-d)
     * @param string $dataFim End date (Y-m-d)
     * @param string $itemFin1 Financial item 1 (category code)
     * @param string $itemFin2 Financial item 2 (optional)
     * @param string $itemFin3 Financial item 3 (optional)
     * @return array Result with 'cod_viagem' (trip code) and status
     * @throws Exception if SOAP call fails
     */
    public function comprarViagem(
        string $nomeRota,
        string $placa,
        int $eixos,
        string $dataInicio,
        string $dataFim,
        string $itemFin1 = '',
        string $itemFin2 = '',
        string $itemFin3 = ''
    ): array {
        try {
            Log::info('[SemParar] Comprando viagem', [
                'nome_rota' => $nomeRota,
                'placa' => $placa,
                'eixos' => $eixos,
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim,
                'item_fin1' => $itemFin1
            ]);

            // Get token and call directly
            $soapClient = $this->soapClient->getSoapClient();

            // CORREÇÃO BUG #16: Validar token explicitamente
            $token = $this->soapClient->getToken();
            if (!$token) {
                $token = $this->soapClient->autenticarUsuario();
            }
            if (!$token) {
                throw new \Exception('Falha na autenticação SemParar. Token não pôde ser obtido.');
            }

            // WSDL signature: comprarViagem(nomeRota, placa, nEixos, inicioVigencia, fimVigencia,
            //                               itemFin1, itemFin2, itemFin3, sessao)
            $response = $soapClient->comprarViagem(
                $nomeRota,
                strtoupper(trim($placa)),
                $eixos,
                $dataInicio,
                $dataFim,
                $itemFin1,
                $itemFin2,
                $itemFin3,
                $token
            );

            // Check status
            $status = $response->status ?? 0;
            if ($status !== 0) {
                Log::error('[SemParar] Compra de viagem retornou erro', [
                    'status' => $status,
                    'status_mensagem' => $response->statusMensagem ?? null
                ]);

                throw new Exception("Erro SemParar status {$status}: " . ($response->statusMensagem ?? 'Erro desconhecido'));
            }

            // Extract trip code from response
            // Progress extracts: <numero xsi:type="xsd:long">123456</numero>
            $codViagem = (string)($response->numero ?? '');

            Log::info('[SemParar] Viagem comprada com sucesso', [
                'cod_viagem' => $codViagem,
                'status' => $status
            ]);

            return [
                'success' => true,
                'cod_viagem' => $codViagem,
                'status' => $status
            ];

        } catch (Exception $e) {
            Log::error('[SemParar] Erro ao comprar viagem', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'cod_viagem' => null
            ];
        }
    }

    /**
     * Obter recibo da viagem (PDF em base64)
     *
     * Based on Rota.cls::obterRecibo() (line 610-649)
     * Progress code:
     *   RUN VALUE("obterReciboViagem") IN hPorta(
     *     INPUT codViagem,
     *     INPUT this-object:cToken,
     *     OUTPUT retorno-Xml-Roteriza
     *   )
     *
     * WSDL: InfoReciboViagem obterReciboViagem(string $codigo, long $sessao)
     *
     * Returns XML with:
     * <obterReciboViagemReturn>
     *   <reciboPDF>base64_encoded_pdf_content</reciboPDF>
     *   <status>0</status>
     *   <statusMensagem>Sucesso</statusMensagem>
     * </obterReciboViagemReturn>
     *
     * @param string $codViagem Trip code (from comprarViagem)
     * @return array Result with 'recibo_pdf' (base64), 'status', and 'status_mensagem'
     * @throws Exception if SOAP call fails or receipt not available
     */
    public function obterRecibo(string $codViagem): array
    {
        try {
            Log::info('[SemParar] Obtendo recibo da viagem', [
                'cod_viagem' => $codViagem
            ]);

            // Get SOAP client and token
            $soapClient = $this->soapClient->getSoapClient();

            // CORREÇÃO BUG #16: Validar token explicitamente
            $token = $this->soapClient->getToken();
            if (!$token) {
                $token = $this->soapClient->autenticarUsuario();
            }
            if (!$token) {
                throw new \Exception('Falha na autenticação SemParar. Token não pôde ser obtido.');
            }

            Log::debug('[SemParar] Calling obterReciboViagem', [
                'cod_viagem' => $codViagem,
                'token_length' => strlen($token)
            ]);

            // Call SOAP method
            $response = $soapClient->obterReciboViagem(
                $codViagem,
                $token
            );

            // Log SOAP request/response for debugging
            Log::debug('[SemParar] SOAP Request', [
                'request' => $soapClient->__getLastRequest()
            ]);

            Log::debug('[SemParar] SOAP Response', [
                'response' => $soapClient->__getLastResponse()
            ]);

            // Extract data from response
            $status = (int)($response->status ?? 999);
            $statusMensagem = (string)($response->statusMensagem ?? 'Erro desconhecido');

            // Check for errors
            if ($status !== 0) {
                Log::error('[SemParar] Erro ao obter recibo', [
                    'status' => $status,
                    'status_mensagem' => $statusMensagem
                ]);

                throw new \Exception("Erro SemParar status {$status}: {$statusMensagem}");
            }

            // Convert SOAP response object to array
            // SOAP returns trip data (pracas, total, viagem, catVeiculo, etc.), NOT PDF
            // This data will be sent to Node.js service for PDF generation
            $responseData = json_decode(json_encode($response), true);

            Log::info('[SemParar] Recibo data obtido com sucesso', [
                'cod_viagem' => $codViagem,
                'status' => $status,
                'has_pracas' => isset($responseData['pracas']),
                'pracas_count' => isset($responseData['pracas']) ? count((array)$responseData['pracas']) : 0
            ]);

            return [
                'success' => true,
                'data' => $responseData,  // Full SOAP response data
                'status' => $status,
                'status_mensagem' => $statusMensagem
            ];

        } catch (\SoapFault $e) {
            Log::error('[SemParar] SOAP Fault ao obter recibo', [
                'error' => $e->getMessage(),
                'faultcode' => $e->faultcode ?? 'N/A',
                'faultstring' => $e->faultstring ?? 'N/A',
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Erro SOAP: ' . $e->getMessage(),
                'data' => null
            ];

        } catch (Exception $e) {
            Log::error('[SemParar] Erro ao obter recibo', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Gerar recibo da viagem e enviar por WhatsApp/Email
     *
     * Based on Rota.cls::criaRecibo() (line 608-653)
     *
     * Fluxo:
     * 1. Chama SOAP obterReciboViagem() para pegar dados da viagem
     * 2. Envia para serviço Python Flask (PYTHON_FLASK_URL/gerar-vale-pedagio)
     * 3. Serviço gera PDF e envia por WhatsApp/Email
     *
     * Progress code:
     *   RUN VALUE("obterReciboViagem") IN hPorta(...)
     *   paiOjson:Add("data", oJson).
     *   paiOjson:Add("telefone", formataCelular(codtrn)).
     *   paiOjson:Add("email", email).
     *   paiOjson:add("flgImprime", flgImprime).
     *   POST {PYTHON_FLASK_URL}/gerar-vale-pedagio
     *
     * @param string $codViagem Trip code
     * @param string $telefone Phone number in format 5531988892076 (country+ddd+number)
     * @param string $email Email address
     * @param bool $flgImprime Flag to print/display
     * @return array Result with success status and message from Python Flask service
     * @throws Exception if SOAP or HTTP call fails
     */
    public function gerarRecibo(
        string $codViagem,
        string $telefone,
        string $email = '',
        bool $flgImprime = true
    ): array {
        try {
            Log::info('[SemParar] Gerando recibo da viagem', [
                'cod_viagem' => $codViagem,
                'telefone' => $telefone,
                'email' => $email
            ]);

            // Step 1: Get receipt data from SOAP
            $reciboData = $this->obterRecibo($codViagem);

            if (!$reciboData['success']) {
                return [
                    'success' => false,
                    'error' => 'Erro ao obter dados do recibo: ' . ($reciboData['error'] ?? 'Desconhecido')
                ];
            }

            // Step 2: Prepare payload for Python Flask service (app.py)
            // Python expects: info["pracastwo"][0]["pracas"] (line 132 of app.py)
            // Structure: obterReciboViagemReturn -> pracastwo -> pracas
            $mainData = $reciboData['data'];
            $pracasArray = $mainData['pracas'] ?? [];
            unset($mainData['pracas']);  // Remove pracas from main record

            // CORREÇÃO BUG #18: round() para prevenir perda de precisão em valores monetários
            // Convert numeric strings to float for Python formatar_reais() function
            // Using round() with 2 decimals to prevent float precision loss (e.g., 123.45 → 123.44999)
            if (isset($mainData['total'])) {
                $mainData['total'] = round(floatval($mainData['total']), 2);
            }

            // Convert tarifa strings to float in each praca with precision rounding
            foreach ($pracasArray as &$praca) {
                if (isset($praca['tarifa'])) {
                    $praca['tarifa'] = round(floatval($praca['tarifa']), 2);
                }
            }
            unset($praca);  // Break reference

            // Wrap pracas in pracastwo structure as Python expects
            $mainData['pracastwo'] = [
                [
                    'pracas' => $pracasArray
                ]
            ];

            // SEMPRE usar email padrão para evitar problemas SMTP
            // WhatsApp é o principal (sempre funciona), email é secundário
            $originalEmail = $email;
            $email = config('mail.noreply_address', 'naoresponda@tambasa.com.br');

            if (!empty($originalEmail)) {
                Log::debug('[SemParar] Using default email (SMTP unreliable)', [
                    'original_email' => $originalEmail,
                    'using_email' => $email
                ]);
            }

            $payload = [
                'data' => [
                    'obterReciboViagemReturnDset' => [
                        'obterReciboViagemReturn' => [$mainData]
                    ]
                ],
                'telefone' => $telefone,
                'email' => $email,
                'flgImprime' => $flgImprime
            ];

            $pythonFlaskUrl = config('services.python_flask.url');
            if (empty($pythonFlaskUrl)) {
                throw new \Exception('PYTHON_FLASK_URL não configurado no .env');
            }
            $pdfEndpoint = $pythonFlaskUrl . '/gerar-vale-pedagio';

            Log::debug('[SemParar] Calling Python Flask PDF service', [
                'url' => $pdfEndpoint,
                'telefone' => $telefone,
                'has_email' => !empty($email),
                'pracas_count' => count($pracasArray)
            ]);

            // Step 3: Call Python Flask service to generate and send PDF
            $client = new \GuzzleHttp\Client([
                'timeout' => 30,
                'connect_timeout' => 10
            ]);

            $response = $client->post($pdfEndpoint, [
                'json' => $payload,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ]
            ]);

            $responseBody = json_decode($response->getBody()->getContents(), true);

            Log::info('[SemParar] Recibo gerado e enviado com sucesso', [
                'cod_viagem' => $codViagem,
                'telefone' => $telefone,
                'response' => $responseBody
            ]);

            return [
                'success' => true,
                'message' => 'Recibo gerado e enviado com sucesso',
                'status' => $responseBody['status'] ?? 'success',
                'telefone' => $telefone,
                'email' => $email
            ];

        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            Log::error('[SemParar] Erro de conexão com serviço Node.js', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Serviço de geração de PDF indisponível. Verifique se o servidor Node.js está rodando.'
            ];

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            Log::error('[SemParar] Erro HTTP ao gerar recibo', [
                'error' => $e->getMessage(),
                'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null
            ]);

            return [
                'success' => false,
                'error' => 'Erro ao gerar recibo: ' . $e->getMessage()
            ];

        } catch (Exception $e) {
            Log::error('[SemParar] Erro ao gerar recibo', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Consultar viagens por período (FASE 3A)
     *
     * Based on Rota.cls::extratoRota() (line 968-1017)
     * SOAP method: obterExtratoCreditos
     *
     * @param string $dataInicio Data início (YYYY-MM-DD)
     * @param string $dataFim Data fim (YYYY-MM-DD)
     * @return array Success/error with trip list
     */
    public function consultarViagens(string $dataInicio, string $dataFim): array
    {
        try {
            Log::info('[SemParar] Consultando viagens por período', [
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim
            ]);

            // Get SOAP EXTRATO client (vpextrato WSDL) and token
            // Progress uses separate WSDL for this method (Rota.cls line 971)
            $soapExtratoClient = $this->soapClient->getSoapExtratoClient();

            // CORREÇÃO BUG #16: Validar token explicitamente
            $token = $this->soapClient->getToken();
            if (!$token) {
                $token = $this->soapClient->autenticarUsuario();
            }
            if (!$token) {
                throw new \Exception('Falha na autenticação SemParar. Token não pôde ser obtido.');
            }

            // Format dates to ISO format with timezone (as Progress does)
            $dataInicioISO = $dataInicio . 'T00:00:00Z';
            $dataFimISO = $dataFim . 'T23:59:59Z';

            Log::debug('[SemParar] Calling obterExtratoCreditos (vpextrato WSDL)', [
                'data_inicio_iso' => $dataInicioISO,
                'data_fim_iso' => $dataFimISO,
                'token_length' => strlen($token),
                'wsdl' => 'vpextrato'
            ]);

            // Call SOAP method using vpextrato WSDL
            // Progress: RUN VALUE("obterExtratoCreditos") IN hport(input inicio, input fim, input this-object:cToken, ...)
            $response = $soapExtratoClient->obterExtratoCreditos(
                $dataInicioISO,
                $dataFimISO,
                $token
            );

            // Log SOAP request/response for debugging
            Log::debug('[SemParar] SOAP Request', [
                'request' => $soapExtratoClient->__getLastRequest()
            ]);

            Log::debug('[SemParar] SOAP Response', [
                'response' => $soapExtratoClient->__getLastResponse()
            ]);

            // Convert response to array
            $responseData = json_decode(json_encode($response), true);

            Log::info('[SemParar] Viagens consultadas com sucesso', [
                'total_viagens' => is_array($responseData) ? count($responseData) : 0
            ]);

            return [
                'success' => true,
                'viagens' => $responseData,
                'periodo' => [
                    'inicio' => $dataInicio,
                    'fim' => $dataFim
                ]
            ];

        } catch (\SoapFault $e) {
            Log::error('[SemParar] SOAP Fault ao consultar viagens', [
                'error' => $e->getMessage(),
                'faultcode' => $e->faultcode ?? 'N/A',
                'faultstring' => $e->faultstring ?? 'N/A',
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Erro SOAP: ' . $e->getMessage()
            ];

        } catch (Exception $e) {
            Log::error('[SemParar] Erro ao consultar viagens', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Cancelar viagem (FASE 3A)
     *
     * Based on Rota.cls::cancelaViagem() (line 99-105)
     * SOAP method: cancelarViagem
     *
     * @param string $codViagem Trip code to cancel
     * @return array Success/error message
     */
    public function cancelarViagem(string $codViagem): array
    {
        try {
            Log::info('[SemParar] Cancelando viagem', [
                'cod_viagem' => $codViagem
            ]);

            // Get SOAP client and token
            $soapClient = $this->soapClient->getSoapClient();

            // CORREÇÃO BUG #16: Validar token explicitamente
            $token = $this->soapClient->getToken();
            if (!$token) {
                $token = $this->soapClient->autenticarUsuario();
            }
            if (!$token) {
                throw new \Exception('Falha na autenticação SemParar. Token não pôde ser obtido.');
            }

            Log::debug('[SemParar] Calling cancelarViagem', [
                'cod_viagem' => $codViagem,
                'token_length' => strlen($token)
            ]);

            // Call SOAP method
            $response = $soapClient->cancelarViagem(
                $codViagem,
                $token
            );

            // Log SOAP request/response for debugging
            Log::debug('[SemParar] SOAP Request', [
                'request' => $soapClient->__getLastRequest()
            ]);

            Log::debug('[SemParar] SOAP Response', [
                'response' => $soapClient->__getLastResponse()
            ]);

            // Extract status
            $status = (int)($response->status ?? 999);
            $statusMensagem = (string)($response->statusMensagem ?? 'Erro desconhecido');

            if ($status !== 0) {
                Log::error('[SemParar] Erro ao cancelar viagem', [
                    'status' => $status,
                    'status_mensagem' => $statusMensagem
                ]);

                throw new \Exception("Erro SemParar status {$status}: {$statusMensagem}");
            }

            Log::info('[SemParar] Viagem cancelada com sucesso', [
                'cod_viagem' => $codViagem
            ]);

            return [
                'success' => true,
                'message' => 'Viagem cancelada com sucesso',
                'cod_viagem' => $codViagem,
                'status' => $status,
                'status_mensagem' => $statusMensagem
            ];

        } catch (\SoapFault $e) {
            Log::error('[SemParar] SOAP Fault ao cancelar viagem', [
                'error' => $e->getMessage(),
                'faultcode' => $e->faultcode ?? 'N/A',
                'faultstring' => $e->faultstring ?? 'N/A',
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Erro SOAP: ' . $e->getMessage()
            ];

        } catch (Exception $e) {
            Log::error('[SemParar] Erro ao cancelar viagem', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Reemitir viagem com nova placa (FASE 3A)
     *
     * Based on Rota.cls::reemiteViagem() (line 108-158)
     * SOAP method: reemitirViagem
     *
     * @param string $codViagem Trip code to reissue
     * @param string $placa New license plate
     * @return array Success/error message
     */
    public function reemitirViagem(string $codViagem, string $placa): array
    {
        try {
            Log::info('[SemParar] Reemitindo viagem', [
                'cod_viagem' => $codViagem,
                'placa' => $placa
            ]);

            // Get SOAP client and token
            $soapClient = $this->soapClient->getSoapClient();

            // CORREÇÃO BUG #16: Validar token explicitamente
            $token = $this->soapClient->getToken();
            if (!$token) {
                $token = $this->soapClient->autenticarUsuario();
            }
            if (!$token) {
                throw new \Exception('Falha na autenticação SemParar. Token não pôde ser obtido.');
            }

            // CORREÇÃO BUG #17: Implementar validação de praças vazias
            // Progress builds pracas string from database (e.g., "1-2-3-4-5-6")
            // For now, we'll use "all" or empty string to reemit all toll plazas
            // TODO: Query database to get exact toll plaza sequence if needed
            $pracas = '';  // Empty means reemit all plazas

            // Validate pracas parameter
            // Note: SemParar API may require non-empty pracas string
            // If empty string causes errors, implement database query to fetch pracas
            if (empty($pracas)) {
                Log::warning('[SemParar] Reemitindo viagem com praças vazias (pode causar erro)', [
                    'cod_viagem' => $codViagem,
                    'pracas_count' => 0,
                    'note' => 'Se API retornar erro, implementar query ao banco para buscar praças'
                ]);
            }

            Log::debug('[SemParar] Calling reemitirViagem', [
                'cod_viagem' => $codViagem,
                'placa' => $placa,
                'pracas' => $pracas,
                'token_length' => strlen($token)
            ]);

            // Call SOAP method
            $response = $soapClient->reemitirViagem(
                $codViagem,
                $placa,
                $pracas,
                $token
            );

            // Log SOAP request/response for debugging
            Log::debug('[SemParar] SOAP Request', [
                'request' => $soapClient->__getLastRequest()
            ]);

            Log::debug('[SemParar] SOAP Response', [
                'response' => $soapClient->__getLastResponse()
            ]);

            // Extract status
            $status = (int)($response->status ?? 999);
            $statusMensagem = (string)($response->statusMensagem ?? 'Erro desconhecido');

            if ($status !== 0) {
                Log::error('[SemParar] Erro ao reemitir viagem', [
                    'status' => $status,
                    'status_mensagem' => $statusMensagem
                ]);

                throw new \Exception("Erro SemParar status {$status}: {$statusMensagem}");
            }

            Log::info('[SemParar] Viagem reemitida com sucesso', [
                'cod_viagem' => $codViagem,
                'placa' => $placa
            ]);

            return [
                'success' => true,
                'message' => 'Viagem reemitida com sucesso',
                'cod_viagem' => $codViagem,
                'placa' => $placa,
                'status' => $status,
                'status_mensagem' => $statusMensagem
            ];

        } catch (\SoapFault $e) {
            Log::error('[SemParar] SOAP Fault ao reemitir viagem', [
                'error' => $e->getMessage(),
                'faultcode' => $e->faultcode ?? 'N/A',
                'faultstring' => $e->faultstring ?? 'N/A',
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Erro SOAP: ' . $e->getMessage()
            ];

        } catch (Exception $e) {
            Log::error('[SemParar] Erro ao reemitir viagem', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
