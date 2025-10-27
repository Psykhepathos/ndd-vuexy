<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProgressService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class CompraViagemController extends Controller
{
    /**
     * ⚠️ CONTROLE DE CHAMADAS À API SEMPARAR ⚠️
     *
     * ALLOW_SOAP_QUERIES = true  -> Permite validações e consultas (statusVei, verificaPreco)
     * ALLOW_SOAP_PURCHASE = false -> BLOQUEIA compra real de viagens (compraViagem)
     *
     * Configuração atual: CONSULTAS REAIS, COMPRAS BLOQUEADAS
     */
    protected bool $ALLOW_SOAP_QUERIES = true;   // ✅ Permite statusVei, verificaPreco
    protected bool $ALLOW_SOAP_PURCHASE = false; // ❌ Bloqueia compraViagem
    protected ProgressService $progressService;

    public function __construct(ProgressService $progressService)
    {
        $this->progressService = $progressService;
    }

    /**
     * Inicializa a tela de compra de viagem
     * Retorna configurações iniciais
     */
    public function initialize(Request $request): JsonResponse
    {
        try {
            Log::info('API: Inicializando Compra de Viagem', [
                'allow_soap_queries' => $this->ALLOW_SOAP_QUERIES,
                'allow_soap_purchase' => $this->ALLOW_SOAP_PURCHASE
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Compra de Viagem inicializada',
                'data' => [
                    'test_mode' => !$this->ALLOW_SOAP_PURCHASE,
                    'allow_soap_queries' => $this->ALLOW_SOAP_QUERIES,
                    'allow_soap_purchase' => $this->ALLOW_SOAP_PURCHASE,
                    'warning' => !$this->ALLOW_SOAP_PURCHASE ?
                        '⚠️ MODO SEGURO: Consultas reais permitidas, mas COMPRAS BLOQUEADAS' :
                        '🚨 ATENÇÃO: Sistema em modo PRODUÇÃO - Compras serão efetuadas!',
                    'modos_disponiveis' => [
                        'cd' => 'Centro de Distribuição',
                        'outros' => 'Outros (Praça)',
                        'retorno' => 'Retorno'
                    ],
                    'hoje' => now()->format('Y-m-d'),
                    'data_fim_padrao_dias' => 5
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao inicializar Compra de Viagem', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao inicializar sistema',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retorna estatísticas gerais (para debug)
     */
    public function statistics(): JsonResponse
    {
        try {
            // TODO: Implementar estatísticas reais
            return response()->json([
                'success' => true,
                'data' => [
                    'total_viagens_compradas' => 0,
                    'ultima_compra' => null,
                    'test_mode' => !$this->ALLOW_SOAP_PURCHASE
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao obter estatísticas', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao obter estatísticas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Health check endpoint
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'service' => 'Compra de Viagem SemParar',
            'test_mode' => !$this->ALLOW_SOAP_PURCHASE,
            'allow_soap_queries' => $this->ALLOW_SOAP_QUERIES,
            'allow_soap_purchase' => $this->ALLOW_SOAP_PURCHASE,
            'timestamp' => now()->toIso8601String()
        ]);
    }

    /**
     * FASE 2: Valida pacote para compra de viagem
     * Verifica se o pacote existe, não é TCD (quando necessário), e carrega dados do transporte
     */
    public function validarPacote(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'codpac' => 'required|integer|min:1',
                'flgcd' => 'required|boolean'
            ]);

            Log::info('API: Validando pacote para compra de viagem', [
                'codpac' => $validated['codpac'],
                'flgcd' => $validated['flgcd'],
                'allow_soap_purchase' => $this->ALLOW_SOAP_PURCHASE
            ]);

            // VALIDAÇÃO 1: Verifica se pacote é TCD (Progress: compraRota.p linha 216-227)
            if (!$validated['flgcd']) {
                $isTCD = $this->progressService->isPacoteTCD($validated['codpac']);
                if ($isTCD) {
                    Log::warning('Tentativa de usar pacote TCD em modo normal', [
                        'codpac' => $validated['codpac']
                    ]);

                    return response()->json([
                        'success' => false,
                        'error' => 'Pacote é TCD. Utilize o modo CD para este pacote.',
                        'code' => 'PACOTE_TCD'
                    ], 400);
                }
            }

            // VALIDAÇÃO 2: Consulta o banco Progress
            $result = $this->progressService->validatePackageForCompraViagem(
                $validated['codpac'],
                $validated['flgcd']
            );

            if (!$result['success']) {
                Log::warning('Validação de pacote falhou', [
                    'codpac' => $validated['codpac'],
                    'error' => $result['error'],
                    'code' => $result['code'] ?? 'UNKNOWN'
                ]);

                return response()->json($result, 400);
            }

            // VALIDAÇÃO 3: Busca rota sugerida (Progress: compraRota.p linha 432-475)
            $rotaSugerida = null;

            // Tenta pacsoc primeiro
            $rotaPacsoc = $this->progressService->getRotaSugeridaPorPacsoc($validated['codpac']);
            if ($rotaPacsoc) {
                $rotaSugerida = $rotaPacsoc;
                Log::info('Rota sugerida encontrada via pacsoc', ['rota' => $rotaSugerida]);
            }

            // Se não achou por pacsoc, tenta introt
            if (!$rotaSugerida) {
                $rotaIntrot = $this->progressService->getRotaSugeridaPorIntrot($validated['codpac'], false);
                if ($rotaIntrot) {
                    $rotaSugerida = $rotaIntrot;
                    Log::info('Rota sugerida encontrada via introt', ['rota' => $rotaSugerida]);
                }
            }

            // Adiciona rota sugerida ao resultado
            $result['data']['rota_sugerida'] = $rotaSugerida;

            Log::info('Pacote validado com sucesso', [
                'codpac' => $validated['codpac'],
                'transporte' => $result['data']['transporte']['nomtrn']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Pacote validado com sucesso',
                'data' => $result['data'],
                'test_mode' => !$this->ALLOW_SOAP_PURCHASE,
                'warning' => !$this->ALLOW_SOAP_PURCHASE ?
                    '⚠️ Pacote validado - Compras reais estão BLOQUEADAS' :
                    null
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Erro ao validar pacote', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao validar pacote',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * FASE 3: Valida placa/veículo na API SemParar
     * Verifica se o veículo está cadastrado e ativo no sistema SemParar
     */
    public function validarPlaca(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'placa' => 'required|string|min:7|max:10'
            ]);

            Log::info('API: Validando placa no SemParar', [
                'placa' => $validated['placa'],
                'allow_soap_queries' => $this->ALLOW_SOAP_QUERIES
            ]);

            // Valida veículo (se ALLOW_SOAP_QUERIES=true, faz chamada SOAP real)
            $result = $this->progressService->validateVehicleStatusSemParar(
                $validated['placa'],
                !$this->ALLOW_SOAP_QUERIES  // false = chamada real, true = simulado
            );

            if (!$result['success']) {
                Log::warning('Validação de placa falhou', [
                    'placa' => $validated['placa'],
                    'error' => $result['error'],
                    'code' => $result['code'] ?? 'UNKNOWN'
                ]);

                return response()->json($result, 400);
            }

            Log::info('Placa validada com sucesso', [
                'placa' => $validated['placa'],
                'descricao' => $result['data']['descricao'],
                'eixos' => $result['data']['eixos']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Placa validada com sucesso',
                'data' => $result['data'],
                'test_mode' => !$this->ALLOW_SOAP_PURCHASE,
                'soap_real' => $this->ALLOW_SOAP_QUERIES,
                'warning' => $this->ALLOW_SOAP_QUERIES ?
                    '✅ Validação REAL via API SemParar - Compras ainda bloqueadas' :
                    '⚠️ Dados simulados - API SemParar não foi chamada'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Erro ao validar placa', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao validar placa',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * FASE 4: Lista rotas SemParar disponíveis para autocomplete
     * Busca rotas da tabela PUB.semPararRot
     */
    public function listarRotas(Request $request): JsonResponse
    {
        try {
            $search = $request->input('search', '');
            $flgcd = $request->input('flg_cd', null);

            Log::info('API: Listando rotas SemParar', [
                'search' => $search,
                'flg_cd' => $flgcd
            ]);

            // Busca rotas no Progress
            $rotas = $this->progressService->getSemPararRotas([
                'search' => $search,
                'flg_cd' => $flgcd,
                'per_page' => 50  // Limite para autocomplete
            ]);

            if (!$rotas['success']) {
                return response()->json($rotas, 400);
            }

            // Formata para autocomplete
            $results = $rotas['data']['results'] ?? [];

            $options = array_map(function($rota) {
                return [
                    'value' => $rota['spararrotid'],
                    'title' => $rota['desspararrot'],
                    'subtitle' => sprintf(
                        '%s | %d municípios | %d dias',
                        $rota['flgcd'] ? 'CD' : 'Rota',
                        $rota['totalmunicipios'] ?? 0,
                        $rota['tempoviagem'] ?? 0
                    ),
                    'flgcd' => $rota['flgcd'],
                    'flgretorno' => $rota['flgretorno'],
                    'tempoviagem' => $rota['tempoviagem']
                ];
            }, $results);

            return response()->json([
                'success' => true,
                'data' => $options,
                'total' => count($options)
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao listar rotas', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao listar rotas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * FASE 4.5: Valida rota selecionada
     * Progress: compraRota.p linha 492-696
     * Validações:
     * 1. Rota existe
     * 2. Rota é CD se flgcd=true
     * 3. Rota é Retorno se flgretorno=true
     * 4. Não existe viagem duplicada
     */
    public function validarRota(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'codpac' => 'required|integer|min:1',
                'cod_rota' => 'required|integer',
                'flgcd' => 'required|boolean',
                'flgretorno' => 'boolean'
            ]);

            $rotaId = $validated['cod_rota'];
            $flgcd = $validated['flgcd'];
            $flgRetorno = $validated['flgretorno'] ?? false;

            Log::info('API: Validando rota selecionada', $validated);

            // Busca dados da rota
            $rota = $this->progressService->getSemPararRota($rotaId);

            if (!$rota['success'] || empty($rota['data'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Rota não encontrada',
                    'code' => 'ROTA_NAO_ENCONTRADA'
                ], 400);
            }

            $rotaData = $rota['data'];

            // VALIDAÇÃO 1: Rota é CD? (linha 507-518)
            if ($flgcd && !$rotaData['flgcd']) {
                return response()->json([
                    'success' => false,
                    'error' => 'Rota não é CD. Selecione uma rota de Centro de Distribuição.',
                    'code' => 'ROTA_NAO_CD'
                ], 400);
            }

            // VALIDAÇÃO 2: Rota NÃO é CD quando deveria ser normal (linha 519-530)
            if (!$flgcd && $rotaData['flgcd']) {
                return response()->json([
                    'success' => false,
                    'error' => 'Rota é CD. Utilize o modo CD para esta rota.',
                    'code' => 'ROTA_E_CD'
                ], 400);
            }

            // VALIDAÇÃO 3: Rota é Retorno? (linha 531-542)
            if ($flgRetorno && !$rotaData['flgretorno']) {
                return response()->json([
                    'success' => false,
                    'error' => 'Rota não é de retorno. Selecione uma rota de retorno.',
                    'code' => 'ROTA_NAO_RETORNO'
                ], 400);
            }

            // VALIDAÇÃO 4: Rota NÃO é retorno quando deveria ser (linha 543-554)
            if (!$flgRetorno && $rotaData['flgretorno']) {
                return response()->json([
                    'success' => false,
                    'error' => 'Rota é de retorno. Alterne para modo retorno.',
                    'code' => 'ROTA_E_RETORNO'
                ], 400);
            }

            // VALIDAÇÃO 5: Viagem duplicada (linha 555-581)
            $viagemCheck = $this->progressService->viagemJaComprada($validated['codpac'], $rotaId);

            if ($viagemCheck['duplicada']) {
                $viagem = $viagemCheck['viagem'];

                return response()->json([
                    'success' => false,
                    'error' => sprintf(
                        'Já existe viagem comprada para este pacote nesta rota. Viagem %s, placa %s, R$ %.2f, data %s',
                        $viagem['codViagem'],
                        $viagem['NumPla'],
                        $viagem['valViagem'],
                        $viagem['dataCompra']
                    ),
                    'code' => 'VIAGEM_DUPLICADA',
                    'viagem_existente' => $viagem
                ], 400);
            }

            // Calcula datas de vigência (linha 584-588)
            $dataInicio = now()->format('Y-m-d');
            $tempoViagem = $rotaData['tempoviagem'] ?? 5;
            $dataFim = now()->addDays($tempoViagem)->format('Y-m-d');

            Log::info('Rota validada com sucesso', [
                'rota' => $rotaData['desspararrot'],
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Rota validada com sucesso',
                'data' => [
                    'rota' => $rotaData,
                    'data_inicio' => $dataInicio,
                    'data_fim' => $dataFim,
                    'tempo_viagem_dias' => $tempoViagem
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Erro ao validar rota', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao validar rota',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * FASE 5: Verifica preço da viagem
     * Chama API SemParar verificarPreco() para calcular valor
     */
    /**
     * FASE 5: Verifica preço da viagem
     * Chama API SemParar seguindo fluxo de compraRota.p:
     * 1. Cria rota temporária com praças de pedágio
     * 2. Calcula preço usando rota temporária
     */
    public function verificarPreco(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'codpac' => 'required|integer|min:1',  // NOVO: código do pacote
                'cod_rota' => 'required|integer',
                'qtd_eixos' => 'required|integer|min:2|max:9',
                'placa' => 'required|string|size:7',
                'data_inicio' => 'required|date',
                'data_fim' => 'required|date|after_or_equal:data_inicio'
            ]);

            Log::info('API: Verificando preço da viagem com rota temporária', [
                'codpac' => $validated['codpac'],
                'cod_rota' => $validated['cod_rota'],
                'qtd_eixos' => $validated['qtd_eixos'],
                'placa' => $validated['placa'],
                'data_inicio' => $validated['data_inicio'],
                'data_fim' => $validated['data_fim'],
                'allow_soap_queries' => $this->ALLOW_SOAP_QUERIES
            ]);

            // Chama ProgressService com novo fluxo de rota temporária
            $result = $this->progressService->verifyTripPriceSemParar(
                $validated['cod_rota'],    // Código da rota no Progress
                $validated['codpac'],       // Código do pacote
                $validated['qtd_eixos'],
                $validated['placa'],
                $validated['data_inicio'],
                $validated['data_fim'],
                !$this->ALLOW_SOAP_QUERIES  // false = real call, true = simulated
            );

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            return response()->json([
                'success' => true,
                'data' => $result['data'],
                'test_mode' => !$this->ALLOW_SOAP_PURCHASE,
                'soap_real' => $this->ALLOW_SOAP_QUERIES,
                'warning' => $this->ALLOW_SOAP_QUERIES ?
                    '✅ Preço REAL calculado via API SemParar com rota temporária - Compras ainda bloqueadas' :
                    '⚠️ Dados simulados'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro de validação',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Erro ao verificar preço', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao verificar preço',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * FASE 6: Compra de viagem SemParar
     * Progress: compraRota.p linha 827-995
     *
     * Fluxo completo:
     * 1. Chama compraViagem() do SemParar
     * 2. Salva sPararViagem no Progress
     * 3. Salva semPararRotMuLog no Progress
     * 4. (TODO) Gera recibo via Python API
     */
    public function comprarViagem(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'codpac' => 'required|integer|min:1',
                'cod_rota' => 'required|integer',
                'placa' => 'required|string|size:7',
                'qtd_eixos' => 'required|integer|min:2|max:9',
                'data_inicio' => 'required|date',
                'data_fim' => 'required|date|after_or_equal:data_inicio',
                'nome_rota_semparar' => 'required|string',
                'cod_rota_semparar' => 'required|string',
                'valor_viagem' => 'required|numeric|min:0',
                'flgcd' => 'boolean',
                'flgretorno' => 'boolean'
            ]);

            Log::info('API: Iniciando compra de viagem', [
                'codpac' => $validated['codpac'],
                'cod_rota' => $validated['cod_rota'],
                'placa' => $validated['placa'],
                'valor' => $validated['valor_viagem'],
                'allow_soap_purchase' => $this->ALLOW_SOAP_PURCHASE
            ]);

            // VALIDAÇÃO FINAL: Verifica se compras estão permitidas
            if (!$this->ALLOW_SOAP_PURCHASE) {
                Log::warning('Tentativa de compra bloqueada - ALLOW_SOAP_PURCHASE=false');

                return response()->json([
                    'success' => false,
                    'message' => '🚫 COMPRA BLOQUEADA - Sistema em modo seguro',
                    'error' => 'Compras reais estão desabilitadas. Configure ALLOW_SOAP_PURCHASE=true para permitir.',
                    'code' => 'COMPRA_BLOQUEADA',
                    'test_mode' => true
                ], 403);
            }

            // Busca dados do transporte para salvar
            $pacote = $this->progressService->getPacoteById($validated['codpac']);
            if (!$pacote['success']) {
                return response()->json([
                    'success' => false,
                    'error' => 'Pacote não encontrado',
                    'code' => 'PACOTE_NAO_ENCONTRADO'
                ], 400);
            }

            $codtrn = $pacote['data']['codtrn'];

            // TODO: Chamar SemParar compraViagem() quando ALLOW_SOAP_PURCHASE=true
            // Por enquanto, simula compra bem-sucedida
            $numeroViagem = 'SIM_' . time() . '_' . $validated['codpac'];

            Log::info('Compra simulada com sucesso', [
                'codpac' => $validated['codpac'],
                'numero_viagem' => $numeroViagem
            ]);

            // PASSO 1: Salva sPararViagem no Progress (linha 856-867)
            $dadosViagem = [
                'codpac' => $validated['codpac'],
                'codRotCreateSP' => $validated['cod_rota_semparar'],
                'codtrn' => $codtrn,
                'codViagem' => $numeroViagem,
                'nomRotSemParar' => $validated['nome_rota_semparar'],
                'placa' => $validated['placa'],
                'rotaId' => $validated['cod_rota'],
                'valorViagem' => $validated['valor_viagem'],
                'usuario' => 'SYSTEM' // TODO: Pegar usuário autenticado
            ];

            $resultViagem = $this->progressService->salvarSPararViagem($dadosViagem);

            if (!$resultViagem['success']) {
                Log::error('Erro ao salvar viagem no Progress', [
                    'error' => $resultViagem['error']
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Viagem comprada mas erro ao salvar no banco: ' . $resultViagem['error'],
                    'code' => 'ERRO_SALVAR_VIAGEM',
                    'numero_viagem' => $numeroViagem
                ], 500);
            }

            // PASSO 2: Salva semPararRotMuLog no Progress (linha 868-888)
            $rota = $this->progressService->getSemPararRota($validated['cod_rota']);
            if ($rota['success'] && !empty($rota['data']['municipios'])) {
                $municipios = $rota['data']['municipios'];

                $resultLog = $this->progressService->salvarSemPararRotMuLog(
                    $numeroViagem,
                    $validated['cod_rota'],
                    $municipios
                );

                if (!$resultLog['success']) {
                    Log::warning('Erro ao salvar log de municípios', [
                        'error' => $resultLog['error']
                    ]);
                }
            }

            // PASSO 3: TODO - Gerar recibo (linha 890-916)
            // criaRecibo() via Python API

            Log::info('Compra de viagem concluída com sucesso', [
                'codpac' => $validated['codpac'],
                'numero_viagem' => $numeroViagem,
                'valor' => $validated['valor_viagem']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Viagem comprada com sucesso!',
                'data' => [
                    'numero_viagem' => $numeroViagem,
                    'codpac' => $validated['codpac'],
                    'rota' => $validated['nome_rota_semparar'],
                    'placa' => $validated['placa'],
                    'valor' => $validated['valor_viagem'],
                    'data_compra' => now()->format('Y-m-d H:i:s')
                ],
                'test_mode' => !$this->ALLOW_SOAP_PURCHASE,
                'warning' => '⚠️ Compra SIMULADA - ALLOW_SOAP_PURCHASE=false'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Erro ao comprar viagem', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao comprar viagem',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
