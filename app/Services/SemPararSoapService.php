<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use SoapClient;
use SoapFault;

/**
 * Serviço SOAP para API SemParar (ViaFácil)
 *
 * Baseado em SemParar/Connect.cls e Rota.cls do Progress
 *
 * Credenciais:
 * - CNPJ: 2024209702
 * - Usuário: CORPORATIVO
 * - Senha: Tambasa20
 *
 * WSDL: https://app.viafacil.com.br/wsvp/ValePedagio?wsdl
 */
class SemPararSoapService
{
    private ?SoapClient $client = null;
    private ?string $sessionToken = null;

    // Configurações do ambiente
    private string $wsdl = 'https://app.viafacil.com.br/wsvp/ValePedagio?wsdl';
    private string $cnpj = '2024209702';
    private string $usuario = 'CORPORATIVO';
    private string $senha = 'Tambasa20';

    // DEBUG MODE - Salva XMLs e loga tudo
    private bool $debugMode = true;
    private string $debugFolder = '';

    private function saveDebugXml(string $filename, string $content): void
    {
        if (!$this->debugMode) return;

        if (empty($this->debugFolder)) {
            $this->debugFolder = storage_path('app/semparar_debug_' . date('Y-m-d_His'));
            if (!file_exists($this->debugFolder)) {
                mkdir($this->debugFolder, 0755, true);
            }
        }

        file_put_contents($this->debugFolder . '/' . $filename, $content);
        Log::info("DEBUG XML salvo: {$this->debugFolder}/{$filename}");
    }

    private function logDebug(string $step, array $data = []): void
    {
        Log::info("🔍 DEBUG SEMPARAR [{$step}]", $data);
    }

    /**
     * Construtor
     */
    public function __construct()
    {
        // Configurações do SoapClient
    }

    /**
     * Conecta ao servidor SOAP SemParar
     */
    private function connect(): void
    {
        if ($this->client !== null) {
            return; // Já conectado
        }

        try {
            Log::info('SemParar SOAP: Conectando ao servidor', [
                'wsdl' => $this->wsdl
            ]);

            $options = [
                'trace' => 1,
                'exceptions' => true,
                'connection_timeout' => 30,
                'cache_wsdl' => WSDL_CACHE_NONE, // Desabilita cache durante desenvolvimento
                'soap_version' => SOAP_1_1,
                'stream_context' => stream_context_create([
                    'ssl' => [
                        'verify_peer' => true,
                        'verify_peer_name' => true,
                        'allow_self_signed' => false,
                        'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT
                    ]
                ])
            ];

            $this->client = new SoapClient($this->wsdl, $options);

            Log::info('SemParar SOAP: Conexão estabelecida');

        } catch (SoapFault $e) {
            Log::error('SemParar SOAP: Erro ao conectar', [
                'error' => $e->getMessage(),
                'faultcode' => $e->faultcode ?? null
            ]);
            throw new Exception('Erro ao conectar ao servidor SemParar: ' . $e->getMessage());
        }
    }

    /**
     * Autentica usuário e obtém token de sessão
     */
    private function autenticar(): string
    {
        if ($this->sessionToken !== null) {
            return $this->sessionToken; // Token já obtido
        }

        $this->connect();

        try {
            $this->logDebug('PASSO 1 - AUTENTICAÇÃO', [
                'cnpj' => $this->cnpj,
                'usuario' => $this->usuario
            ]);

            // Salva request XML antes da chamada
            $requestXml = $this->client->__getLastRequest() ?? '';

            // Baseado em Connect.cls -> executeWebServiceProcedure()
            $response = $this->client->autenticarUsuario(
                $this->cnpj,
                $this->usuario,
                $this->senha
            );

            // Salva request e response
            $requestXml = $this->client->__getLastRequest();
            $responseXml = $this->client->__getLastResponse();

            $this->saveDebugXml('01_autenticar_request.xml', $requestXml);
            $this->saveDebugXml('01_autenticar_response.xml', $responseXml);

            $this->logDebug('AUTENTICAÇÃO - XML Request', [
                'length' => strlen($requestXml),
                'preview' => substr($requestXml, 0, 300)
            ]);

            $this->logDebug('AUTENTICAÇÃO - XML Response', [
                'length' => strlen($responseXml),
                'preview' => substr($responseXml, 0, 300)
            ]);

            // Extrai o token do XML de resposta
            $this->sessionToken = $this->extractToken($responseXml);

            $this->logDebug('AUTENTICAÇÃO - TOKEN OBTIDO', [
                'token' => $this->sessionToken,
                'token_length' => strlen($this->sessionToken ?? '')
            ]);

            return $this->sessionToken;

        } catch (SoapFault $e) {
            Log::error('SemParar SOAP: Erro na autenticação', [
                'error' => $e->getMessage(),
                'faultcode' => $e->faultcode ?? null
            ]);
            throw new Exception('Erro ao autenticar no SemParar: ' . $e->getMessage());
        }
    }

    /**
     * Extrai token do XML de resposta
     */
    private function extractToken(string $xml): string
    {
        // Baseado em Connect.cls -> extractContentFromXml()
        $pattern = '/<sessao[^>]*>(.*?)<\/sessao>/i';
        if (preg_match($pattern, $xml, $matches)) {
            return trim($matches[1]);
        }

        throw new Exception('Token não encontrado na resposta do servidor');
    }

    /**
     * FASE 3: Obtém status do veículo
     *
     * Baseado em Rota.cls -> method statusVei()
     * RUN VALUE("obterStatusVeiculo") IN hPorta(placa, token, OUTPUT xml)
     *
     * @param string $placa Placa do veículo (ex: "ABC1234")
     * @return array ['descricao', 'eixos', 'proprietario', 'tag', 'status']
     */
    public function obterStatusVeiculo(string $placa): array
    {
        $token = $this->autenticar();

        try {
            Log::info('SemParar SOAP: Consultando status do veículo', [
                'placa' => $placa
            ]);

            $response = $this->client->obterStatusVeiculo($placa, $token);

            Log::debug('SemParar SOAP: Resposta status veículo (objeto)', [
                'response_type' => gettype($response),
                'placa' => $placa
            ]);

            // Acessa o XML da última resposta do SoapClient
            $xmlResponse = $this->client->__getLastResponse();

            Log::debug('SemParar SOAP: XML status veículo', [
                'xml_length' => strlen($xmlResponse ?? ''),
                'xml_preview' => substr($xmlResponse ?? '', 0, 500),
                'placa' => $placa
            ]);

            // Parse do XML de resposta
            $data = $this->parseVehicleStatus($xmlResponse);
            $data['placa'] = strtoupper($placa);

            Log::info('SemParar SOAP: Status do veículo obtido', [
                'placa' => $placa,
                'eixos' => $data['eixos']
            ]);

            return $data;

        } catch (SoapFault $e) {
            Log::error('SemParar SOAP: Erro ao obter status do veículo', [
                'placa' => $placa,
                'error' => $e->getMessage()
            ]);
            throw new Exception('Erro ao consultar veículo: ' . $e->getMessage());
        }
    }

    /**
     * Faz parse do XML de status do veículo
     *
     * Baseado em Rota.cls linhas 175-213
     */
    private function parseVehicleStatus(string $xml): array
    {
        $data = [];

        // Extrai <descricao>
        if (preg_match('/<descricao[^>]*>(.*?)<\/descricao>/i', $xml, $matches)) {
            $data['descricao'] = trim($matches[1]);
        }

        // Extrai <eixos>
        if (preg_match('/<eixos[^>]*>(\d+)<\/eixos>/i', $xml, $matches)) {
            $data['eixos'] = (int) $matches[1];
        }

        // Extrai <proprietario>
        if (preg_match('/<proprietario[^>]*>(.*?)<\/proprietario>/i', $xml, $matches)) {
            $data['proprietario'] = trim($matches[1]);
        }

        // Extrai <tag>
        if (preg_match('/<tag[^>]*>(.*?)<\/tag>/i', $xml, $matches)) {
            $data['tag'] = trim($matches[1]);
        }

        // Adiciona status
        $data['status'] = 'ATIVO'; // Se retornou dados, está ativo

        return $data;
    }

    /**
     * FASE 5: Verifica preço da viagem
     *
     * Baseado em Rota.cls -> method verificaPreco()
     * Método WSDL: obterCustoRota(nomeRota, placa, nEixos, inicioVigencia, fimVigencia, sessao)
     *
     * @param string $nomeRota Nome da rota SemParar (ex: "PP UF MG(PR5,PR6)")
     * @param int $qtdEixos Quantidade de eixos do veículo
     * @param string $placa Placa do veículo
     * @param string $dataInicio Data início vigência (YYYY-MM-DD)
     * @param string $dataFim Data fim vigência (YYYY-MM-DD)
     * @return array ['valor', 'numero_viagem', 'rota', 'placa', 'data_inicio', 'data_fim']
     */
    public function verificarPreco(string $nomeRota, int $qtdEixos, string $placa, string $dataInicio, string $dataFim): array
    {
        $token = $this->autenticar();

        try {
            Log::info('SemParar SOAP: Verificando preço da viagem', [
                'nome_rota' => $nomeRota,
                'qtd_eixos' => $qtdEixos,
                'placa' => $placa,
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim
            ]);

            // Tenta formato ISO YYYY-MM-DD
            $dataInicioFormatada = date('Y-m-d', strtotime($dataInicio));
            $dataFimFormatada = date('Y-m-d', strtotime($dataFim));

            // Log dos parâmetros antes de enviar
            Log::info('SemParar SOAP: Enviando obterCustoRota', [
                'nome_rota' => $nomeRota,
                'placa' => $placa,
                'qtd_eixos' => $qtdEixos,
                'data_inicio_formatada' => $dataInicioFormatada,
                'data_fim_formatada' => $dataFimFormatada,
                'token_length' => strlen($token)
            ]);

            // Método correto do WSDL: obterCustoRota
            // Parâmetros: (nomeRota, placa, nEixos, inicioVigencia, fimVigencia, sessao)
            $response = $this->client->obterCustoRota(
                $nomeRota,  // Nome da rota (string)
                $placa,
                $qtdEixos,
                $dataInicioFormatada,
                $dataFimFormatada,
                $token
            );

            // Log do request SOAP enviado
            $requestXml = $this->client->__getLastRequest();
            Log::debug('SemParar SOAP: Request XML', [
                'xml' => $requestXml
            ]);

            Log::debug('SemParar SOAP: Resposta verificarPreco (objeto)', [
                'response_type' => gettype($response)
            ]);

            // Acessa o XML da última resposta do SoapClient
            $xmlResponse = $this->client->__getLastResponse();

            Log::debug('SemParar SOAP: XML verificarPreco', [
                'xml_length' => strlen($xmlResponse ?? ''),
                'xml_preview' => substr($xmlResponse ?? '', 0, 1000)
            ]);

            // Verifica erro
            $erro = $this->verificarErro($xmlResponse);
            if ($erro) {
                throw new Exception("Erro SemParar: " . $erro);
            }

            // Parse do XML de resposta
            $data = $this->parsePriceVerification($xmlResponse);
            $data['nome_rota'] = $nomeRota;
            $data['placa'] = strtoupper($placa);
            $data['data_inicio'] = $dataInicio;
            $data['data_fim'] = $dataFim;
            $data['qtd_eixos'] = $qtdEixos;

            Log::info('SemParar SOAP: Preço verificado com sucesso', [
                'valor' => $data['valor'],
                'numero_viagem' => $data['numero_viagem']
            ]);

            return $data;

        } catch (SoapFault $e) {
            Log::error('SemParar SOAP: Erro ao verificar preço', [
                'nome_rota' => $nomeRota,
                'error' => $e->getMessage()
            ]);
            throw new Exception('Erro ao verificar preço: ' . $e->getMessage());
        }
    }

    /**
     * Faz parse do XML de verificação de preço
     *
     * Baseado em Rota.cls linhas 258-290
     * Status codes conhecidos:
     * - 0: Sucesso
     * - 12: Rota não encontrada ou inválida
     */
    private function parsePriceVerification(string $xml): array
    {
        $data = [];

        // Extrai <status>
        $status = 0;
        if (preg_match('/<status[^>]*>(\d+)<\/status>/i', $xml, $matches)) {
            $status = (int) trim($matches[1]);
        }

        // Verifica se houve erro no status
        if ($status !== 0) {
            $errorMessages = [
                12 => 'Rota não encontrada no sistema SemParar. Verifique se o nome da rota está correto ou se está cadastrada no SemParar.',
                1 => 'Sessão inválida ou expirada',
                2 => 'Placa inválida',
                3 => 'Data inválida',
                4 => 'Rota temporária não encontrada',
            ];

            $errorMsg = $errorMessages[$status] ?? "Erro SemParar (código " . $status . ")";
            throw new Exception($errorMsg);
        }

        // Extrai <valor>
        if (preg_match('/<valor[^>]*>([\d.,]+)<\/valor>/i', $xml, $matches)) {
            $valorStr = trim($matches[1]);
            // Remove pontos de milhares e converte vírgula decimal para ponto
            $valorStr = str_replace('.', '', $valorStr);
            $valorStr = str_replace(',', '.', $valorStr);
            $data['valor'] = (float) $valorStr;
        } else {
            $data['valor'] = 0.0;
        }

        // Extrai <numeroViagem>
        if (preg_match('/<numeroViagem[^>]*>(.*?)<\/numeroViagem>/i', $xml, $matches)) {
            $data['numero_viagem'] = trim($matches[1]);
        } else {
            $data['numero_viagem'] = '';
        }

        return $data;
    }

    /**
     * Verifica se há erro na resposta XML do SemParar
     * Checa tanto tag <erro> quanto <status> (0 = sucesso, outros = erro)
     *
     * Baseado em Rota.cls -> verificaErro()
     */
    private function verificarErro(string $xml): ?string
    {
        // Verifica tag <erro>
        if (preg_match('/<erro[^>]*>(.*?)<\/erro>/i', $xml, $matches)) {
            $erro = trim($matches[1]);
            if ($erro !== '' && $erro !== 'OK') {
                return $erro;
            }
        }

        // Verifica tag <status> (0 = sucesso, outros valores = erro)
        if (preg_match('/<status[^>]*>(\d+)<\/status>/i', $xml, $matches)) {
            $status = (int) trim($matches[1]);
            if ($status !== 0) {
                // Extrai mensagem de erro se houver
                if (preg_match('/<statusMensagem[^>]*>(.*?)<\/statusMensagem>/i', $xml, $msgMatches)) {
                    $mensagem = trim($msgMatches[1]);
                    if ($mensagem && $mensagem !== 'null') {
                        return "Status " . $status . ": " . $mensagem;
                    }
                }
                return "Erro status " . $status . " retornado pelo SemParar";
            }
        }

        return null;
    }

    /**
     * FASE 5: Cadastra rota temporária no SemParar
     *
     * Baseado em Rota.cls linha 947
     * Cria uma rota temporária com as praças de pedágio especificadas
     *
     * @param array $pracasIds Array de IDs de praças de pedágio
     * @param string $nomeRota Nome da rota (ex: "204 - PP UF MG - 123456-42")
     * @return array ['id_rota' => int, 'nome' => string]
     */
    public function cadastrarRotaTemporaria(array $pracasIds, string $nomeRota): array
    {
        $token = $this->autenticar();

        try {
            Log::info('SemParar SOAP: Cadastrando rota temporária', [
                'nome_rota' => $nomeRota,
                'total_pracas' => count($pracasIds)
            ]);

            // Monta XML com as praças
            $pracasXml = "<pracas>";
            foreach ($pracasIds as $pracaId) {
                $pracasXml .= "<id>" . $pracaId . "</id>";
            }
            $pracasXml .= "</pracas>";

            Log::debug('SemParar SOAP: XML praças', [
                'xml' => $pracasXml
            ]);

            $response = $this->client->cadastrarRotaTemporaria(
                $pracasXml,
                $nomeRota,
                $token
            );

            $xmlResponse = $this->client->__getLastResponse();

            Log::debug('SemParar SOAP: XML cadastrarRotaTemporaria', [
                'xml_length' => strlen($xmlResponse ?? ''),
                'xml_preview' => substr($xmlResponse ?? '', 0, 1000)
            ]);

            // Extrai <id> da rota criada
            if (preg_match('/<id[^>]*>(\d+)<\/id>/i', $xmlResponse, $matches)) {
                $idRota = (int) $matches[1];

                Log::info('SemParar SOAP: Rota temporária cadastrada', [
                    'id_rota' => $idRota,
                    'nome' => $nomeRota
                ]);

                return [
                    'success' => true,
                    'id_rota' => $idRota,
                    'nome' => $nomeRota
                ];
            }

            throw new Exception('ID da rota não encontrado na resposta');

        } catch (SoapFault $e) {
            Log::error('SemParar SOAP: Erro ao cadastrar rota temporária', [
                'error' => $e->getMessage()
            ]);
            throw new Exception('Erro ao cadastrar rota temporária: ' . $e->getMessage());
        }
    }

    /**
     * FASE 5: Roteiriza praças de pedágio
     *
     * Baseado EXATAMENTE em Rota.cls linha 873-899 e roteriza.i
     * SemParar aceita:
     * - Municípios: código IBGE com lat/lon = 0
     * - Entregas: lat/lon reais com código IBGE = 0
     *
     * @param array $pontos Array [{cod_ibge, desc, latitude, longitude}]
     * @return array ['success' => true, 'pracas_ids' => [int]]
     */
    public function roteirizarPracasPedagio(array $pontos): array
    {
        $token = $this->autenticar();

        try {
            $this->logDebug('PASSO 2 - ROTEIRIZAR PRAÇAS', [
                'total_pontos' => count($pontos),
                'todos_pontos' => $pontos
            ]);

            // Aplica regras especiais de negócio ANTES de montar XML
            // Progress: Rota.cls linha 723-834 (roterizaCa)
            $pontos = $this->aplicarRegrasEspeciaisRota($pontos, $codpac ?? null, $flgRetorno ?? false);

            // Monta XML EXATAMENTE como Progress DATASET gera
            $pontosXml = '<pontosParada xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">';
            $pontosXml .= '<pontoParada>';

            foreach ($pontos as $index => $ponto) {
                $lat = isset($ponto['latitude']) && $ponto['latitude'] !== '0' ? floatval($ponto['latitude']) : 0.0;
                $lon = isset($ponto['longitude']) && $ponto['longitude'] !== '0' ? floatval($ponto['longitude']) : 0.0;

                $this->logDebug("PONTO #{$index}", [
                    'cod_ibge' => $ponto['cod_ibge'],
                    'desc' => $ponto['desc'],
                    'latitude' => $lat,
                    'longitude' => $lon
                ]);

                $pontosXml .= '<ponto>';
                $pontosXml .= '<codigoIBGE>' . intval($ponto['cod_ibge']) . '</codigoIBGE>';
                $pontosXml .= '<latLong>';
                $pontosXml .= '<latitude>' . $lat . '</latitude>';
                $pontosXml .= '<longitude>' . $lon . '</longitude>';
                $pontosXml .= '</latLong>';
                $pontosXml .= '<descricao>' . htmlspecialchars($ponto['desc']) . '</descricao>';
                $pontosXml .= '</ponto>';
            }

            $pontosXml .= '</pontoParada>';
            $pontosXml .= '<status>0</status>';
            $pontosXml .= '</pontosParada>';

            $opcoesXml = '<opcoesRota>';
            $opcoesXml .= '<alternativas>false</alternativas>';
            $opcoesXml .= '<status>0</status>';
            $opcoesXml .= '<tipoRota>1</tipoRota>';
            $opcoesXml .= '</opcoesRota>';

            $this->logDebug('XML PONTOS MONTADO', [
                'pontos_xml_length' => strlen($pontosXml),
                'pontos_xml' => $pontosXml
            ]);

            $this->logDebug('XML OPCOES MONTADO', [
                'opcoes_xml' => $opcoesXml
            ]);

            // Salva XMLs que serão enviados
            $this->saveDebugXml('02_roteirizar_pontos_request.xml', $pontosXml);
            $this->saveDebugXml('02_roteirizar_opcoes_request.xml', $opcoesXml);

            $response = $this->client->roteirizarPracasPedagio(
                $pontosXml,
                $opcoesXml,
                $token
            );

            $requestXml = $this->client->__getLastRequest();
            $responseXml = $this->client->__getLastResponse();

            $this->saveDebugXml('02_roteirizar_request_completo.xml', $requestXml);
            $this->saveDebugXml('02_roteirizar_response.xml', $responseXml);

            $this->logDebug('ROTEIRIZAR - XML Response Recebido', [
                'xml_length' => strlen($responseXml),
                'xml_completo' => $responseXml
            ]);

            // Verifica erro
            $erro = $this->verificarErro($responseXml);
            if ($erro) {
                $this->logDebug('ERRO DETECTADO NA RESPOSTA', ['erro' => $erro]);
                throw new Exception("Erro SemParar: " . $erro);
            }

            // Extrai IDs das praças (linha 936-939 de Rota.cls)
            $pracasIds = [];
            if (preg_match_all('/<id[^>]*>(\d+)<\/id>/i', $xmlResponse, $matches)) {
                $pracasIds = array_map('intval', $matches[1]);
            }

            Log::info('SemParar SOAP: Praças roteirizadas com sucesso', [
                'total_pracas' => count($pracasIds),
                'pracas' => array_slice($pracasIds, 0, 10)  // Log primeiras 10
            ]);

            if (count($pracasIds) === 0) {
                Log::warning('SemParar SOAP: Nenhuma praça retornada - verificar XML enviado e resposta');
            }

            return [
                'success' => true,
                'pracas_ids' => $pracasIds
            ];

        } catch (SoapFault $e) {
            Log::error('SemParar SOAP: Erro ao roteirizar praças', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new Exception('Erro ao roteirizar praças: ' . $e->getMessage());
        }
    }

    /**
     * Lista rotas disponíveis no SemParar
     *
     * @return array Lista de rotas cadastradas
     */
    public function listarRotas(): array
    {
        $token = $this->autenticar();

        try {
            Log::info('SemParar SOAP: Listando rotas disponíveis');

            $response = $this->client->listarRotas($token);
            $xmlResponse = $this->client->__getLastResponse();

            Log::debug('SemParar SOAP: XML listarRotas', [
                'xml_length' => strlen($xmlResponse ?? ''),
                'xml_preview' => substr($xmlResponse ?? '', 0, 2000)
            ]);

            // Parse do XML para extrair lista de rotas
            $rotas = [];
            if (preg_match_all('/<nome[^>]*>(.*?)<\/nome>/i', $xmlResponse, $matches)) {
                $rotas = $matches[1];
            }

            Log::info('SemParar SOAP: Rotas encontradas', [
                'total' => count($rotas),
                'rotas' => array_slice($rotas, 0, 10)  // Log primeiras 10
            ]);

            return [
                'success' => true,
                'rotas' => $rotas
            ];

        } catch (SoapFault $e) {
            Log::error('SemParar SOAP: Erro ao listar rotas', [
                'error' => $e->getMessage()
            ]);
            throw new Exception('Erro ao listar rotas: ' . $e->getMessage());
        }
    }

    /**
     * Fecha a conexão SOAP
     */
    public function disconnect(): void
    {
        $this->client = null;
        $this->sessionToken = null;

        Log::info('SemParar SOAP: Conexão encerrada');
    }

    /**
     * Aplica regras especiais de rota EXATAMENTE como Progress
     * Progress: Rota.cls roterizaCa() linhas 723-834
     *
     * Regras implementadas:
     * 1. AC/AM: Se rota contém "AC", aplica regra ACAM (substitui múltiplos pontos por AM-Manaus)
     * 2. Pará (estado 16): Substitui por Maranhão (São Luís) em certos casos
     * 3. Blacklist de municípios: Ignora IBGE 5103379, 1501576, 1502509
     * 4. Cliente específico: Ignora "AVENIDA AEROPORTO,15"
     * 5. Retorno: Se flgretorno=true, mantém só primeira e última entrega
     * 6. Geocoding reverso: Se tem IBGE válido, ZERA GPS (Progress linha 787-791)
     */
    private function aplicarRegrasEspeciaisRota(array $pontos, ?int $codpac, bool $flgRetorno): array
    {
        $this->logDebug('APLICANDO REGRAS ESPECIAIS', [
            'total_pontos_original' => count($pontos),
            'codpac' => $codpac,
            'flgRetorno' => $flgRetorno
        ]);

        // Detecta se é rota AC/AM (linha 723)
        $isRotaACAM = false;
        if ($codpac) {
            try {
                $sql = "SELECT TOP 1 codrot FROM PUB.pacote WHERE codpac = {$codpac}";
                $progressService = app(ProgressService::class);
                $result = $progressService->executeCustomQuery($sql);
                if (!empty($result['data'])) {
                    $codrot = $result['data'][0]['codrot'];
                    $isRotaACAM = strpos($codrot, 'AC') !== false;
                }
            } catch (Exception $e) {
                Log::warning('Erro ao detectar rota AC/AM', ['error' => $e->getMessage()]);
            }
        }

        // Contadores para regras especiais
        $countPara = 0;
        $countACAM = 0;
        $pontosProcessados = [];

        foreach ($pontos as $ponto) {
            $skip = false;

            // REGRA 1: Cliente específico blacklist (linha 731)
            if (isset($ponto['endereco']) && $ponto['endereco'] === 'AVENIDA AEROPORTO,15') {
                $this->logDebug('REGRA: Ignorando cliente AVENIDA AEROPORTO,15', ['ponto' => $ponto]);
                continue;
            }

            // REGRA 2: Blacklist de municípios (linhas 768-772)
            $ibge = intval($ponto['cod_ibge'] ?? 0);
            if (in_array($ibge, [5103379, 1501576])) {
                $this->logDebug('REGRA: Ignorando município blacklist', ['ibge' => $ibge]);
                continue;
            }
            if ($ibge == 1502509 && !$flgRetorno) {
                $this->logDebug('REGRA: Ignorando IBGE 1502509 (não-retorno)', ['ibge' => $ibge]);
                continue;
            }

            // REGRA 3: Pará (estado 16) → Substitui por Maranhão (linhas 758-767, 799-805)
            if ($ibge > 0) {
                $estadoId = intval(substr((string)$ibge, 0, 2));

                if ($estadoId == 16) { // Pará
                    if ($countPara >= 1 && !$flgRetorno) {
                        $this->logDebug('REGRA: Deletando ponto duplicado do Pará', ['ibge' => $ibge]);
                        continue;
                    }

                    // Substitui por São Luís - MA (estado 15, município 140)
                    $ponto['cod_ibge'] = 2111300; // IBGE de São Luís - MA
                    $ponto['desc'] = 'São Luís';
                    $ponto['estado'] = 'Maranhão';
                    $countPara++;

                    $this->logDebug('REGRA: Substituindo Pará por Maranhão', [
                        'ibge_original' => $ibge,
                        'ibge_novo' => $ponto['cod_ibge']
                    ]);
                }
            }

            // REGRA 4: AC/AM (linhas 773-783, 807-813)
            if ($isRotaACAM && $ibge > 0) {
                $estadoId = intval(substr((string)$ibge, 0, 2));

                if (in_array($estadoId, [12, 13])) { // Acre ou Amazonas
                    if ($countACAM >= 1 && !$flgRetorno) {
                        $this->logDebug('REGRA: Deletando ponto duplicado AC/AM', ['ibge' => $ibge]);
                        continue;
                    }

                    // Substitui por Manaus - AM (estado 13, município 40)
                    $ponto['cod_ibge'] = 1302603; // IBGE de Manaus - AM
                    $ponto['desc'] = 'Manaus';
                    $ponto['estado'] = 'Amazonas';
                    $countACAM++;

                    $this->logDebug('REGRA: Substituindo AC/AM por Manaus', [
                        'ibge_original' => $ibge,
                        'ibge_novo' => $ponto['cod_ibge']
                    ]);
                }
            }

            // REGRA 5: Geocoding reverso - Se tem IBGE, ZERA GPS (linhas 787-791)
            if (intval($ponto['cod_ibge'] ?? 0) > 0) {
                $ponto['latitude'] = '0';
                $ponto['longitude'] = '0';

                $this->logDebug('REGRA: Zerando GPS (tem IBGE)', [
                    'ibge' => $ponto['cod_ibge'],
                    'desc' => $ponto['desc']
                ]);
            }

            $pontosProcessados[] = $ponto;
        }

        // REGRA 6: Retorno - mantém só primeira e última (linhas 823-834)
        if ($flgRetorno && count($pontosProcessados) > 2) {
            $primeiraEntrega = $pontosProcessados[0];
            $ultimaEntrega = end($pontosProcessados);

            // Reordena: última entrega fica com seqped=0 (Progress linha 832)
            $ultimaEntrega['seqped'] = 0;

            $pontosProcessados = [$ultimaEntrega, $primeiraEntrega];

            $this->logDebug('REGRA: Retorno - mantendo só primeira e última', [
                'total_original' => count($pontos),
                'total_final' => count($pontosProcessados)
            ]);
        }

        $this->logDebug('REGRAS ESPECIAIS APLICADAS', [
            'total_pontos_original' => count($pontos),
            'total_pontos_final' => count($pontosProcessados),
            'removidos' => count($pontos) - count($pontosProcessados),
            'regras_aplicadas' => [
                'isPara' => $countPara > 0,
                'isACAM' => $countACAM > 0,
                'isRetorno' => $flgRetorno
            ]
        ]);

        return $pontosProcessados;
    }
}
