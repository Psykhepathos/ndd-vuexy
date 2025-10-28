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
            $token = $this->soapClient->getToken() ?? $this->soapClient->autenticarUsuario();

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
            $token = $this->soapClient->getToken() ?? $this->soapClient->autenticarUsuario();

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
            $token = $this->soapClient->getToken() ?? $this->soapClient->autenticarUsuario();

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
            $token = $this->soapClient->getToken() ?? $this->soapClient->autenticarUsuario();

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
            $token = $this->soapClient->getToken() ?? $this->soapClient->autenticarUsuario();

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
            $token = $this->soapClient->getToken() ?? $this->soapClient->autenticarUsuario();

            if (!$token) {
                throw new \Exception('Falha ao obter token de autenticação');
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
            $reciboPDF = (string)($response->reciboPDF ?? '');

            // Check for errors
            if ($status !== 0) {
                Log::error('[SemParar] Erro ao obter recibo', [
                    'status' => $status,
                    'status_mensagem' => $statusMensagem
                ]);

                throw new \Exception("Erro SemParar status {$status}: {$statusMensagem}");
            }

            // Validate PDF content
            if (empty($reciboPDF)) {
                throw new \Exception('Recibo PDF não disponível na resposta');
            }

            Log::info('[SemParar] Recibo obtido com sucesso', [
                'cod_viagem' => $codViagem,
                'pdf_size_bytes' => strlen($reciboPDF),
                'status' => $status
            ]);

            return [
                'success' => true,
                'recibo_pdf' => $reciboPDF,  // Base64 encoded PDF
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
                'recibo_pdf' => null
            ];

        } catch (Exception $e) {
            Log::error('[SemParar] Erro ao obter recibo', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'recibo_pdf' => null
            ];
        }
    }
}
