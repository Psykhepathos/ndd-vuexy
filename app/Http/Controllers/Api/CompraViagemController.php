<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProgressService;
use App\Services\SemParar\SemPararService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class CompraViagemController extends Controller
{
    /**
     * ⚠️ CONTROLE DE CHAMADAS À API SEMPARAR ⚠️
     *
     * ALLOW_SOAP_QUERIES = true  -> Permite validações e consultas (statusVei, verificaPreco)
     * ALLOW_SOAP_PURCHASE = true/false -> Controla compra real de viagens (compraViagem)
     *
     * Configuração lida do .env
     */
    protected bool $ALLOW_SOAP_QUERIES;
    protected bool $ALLOW_SOAP_PURCHASE;
    protected ProgressService $progressService;
    protected SemPararService $semPararService;

    public function __construct(ProgressService $progressService, SemPararService $semPararService)
    {
        $this->progressService = $progressService;
        $this->semPararService = $semPararService;
        // Ler configurações do .env
        $this->ALLOW_SOAP_QUERIES = env('ALLOW_SOAP_QUERIES', true);
        $this->ALLOW_SOAP_PURCHASE = env('ALLOW_SOAP_PURCHASE', false);
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
            $errorId = uniqid('err_');

            Log::error('Erro ao inicializar Compra de Viagem', [
                'error_id' => $errorId,
                'method' => __METHOD__,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => request()->user()->id ?? null,
                'ip' => request()->ip(),
                'timestamp' => now()->toIso8601String()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno no processamento. Contate o suporte.',
                'error_id' => $errorId
            ], 500);
        }
    }

    /**
     * CORREÇÃO #9: Retorna estatísticas reais do banco Progress
     *
     * Consulta tabela PUB.sPararViagem para obter:
     * - Total de viagens compradas
     * - Valor total de viagens
     * - Última compra realizada
     * - Viagens por status (ativa/cancelada)
     */
    public function statistics(): JsonResponse
    {
        try {
            // CORREÇÃO BUG IMPORTANTE #3: Validar resultados de queries antes de usar

            // Query para estatísticas gerais
            $sqlGeral = "SELECT COUNT(*) as total, SUM(valViagem) as valor_total FROM PUB.sPararViagem WHERE flgCancelado = false OR flgCancelado IS NULL";
            $resultGeral = $this->progressService->executeCustomQuery($sqlGeral);

            if (!$resultGeral['success']) {
                Log::error('Erro ao obter estatísticas gerais', [
                    'method' => __METHOD__,
                    'error' => $resultGeral['error'] ?? 'Unknown error'
                ]);
            }

            // Query para última compra
            $sqlUltima = "SELECT TOP 1 codViagem, NumPla, valViagem, dataCompra FROM PUB.sPararViagem WHERE flgCancelado = false OR flgCancelado IS NULL ORDER BY dataCompra DESC";
            $resultUltima = $this->progressService->executeCustomQuery($sqlUltima);

            if (!$resultUltima['success']) {
                Log::error('Erro ao obter última compra', [
                    'method' => __METHOD__,
                    'error' => $resultUltima['error'] ?? 'Unknown error'
                ]);
            }

            // Query para viagens canceladas
            $sqlCanceladas = "SELECT COUNT(*) as total FROM PUB.sPararViagem WHERE flgCancelado = true";
            $resultCanceladas = $this->progressService->executeCustomQuery($sqlCanceladas);

            if (!$resultCanceladas['success']) {
                Log::error('Erro ao obter viagens canceladas', [
                    'method' => __METHOD__,
                    'error' => $resultCanceladas['error'] ?? 'Unknown error'
                ]);
            }

            // Processa resultados
            $totalViagens = 0;
            $valorTotal = 0;
            if ($resultGeral['success'] && !empty($resultGeral['data'])) {
                $totalViagens = (int)($resultGeral['data'][0]['total'] ?? 0);
                $valorTotal = (float)($resultGeral['data'][0]['valor_total'] ?? 0);
            }

            $ultimaCompra = null;
            if ($resultUltima['success'] && !empty($resultUltima['data'])) {
                $ultima = $resultUltima['data'][0];
                $ultimaCompra = [
                    'cod_viagem' => $ultima['codViagem'] ?? null,
                    'placa' => $ultima['NumPla'] ?? null,
                    'valor' => (float)($ultima['valViagem'] ?? 0),
                    'data' => $ultima['dataCompra'] ?? null
                ];
            }

            $totalCanceladas = 0;
            if ($resultCanceladas['success'] && !empty($resultCanceladas['data'])) {
                $totalCanceladas = (int)($resultCanceladas['data'][0]['total'] ?? 0);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'total_viagens_compradas' => $totalViagens,
                    'total_viagens_canceladas' => $totalCanceladas,
                    'valor_total_viagens' => $valorTotal,
                    'ultima_compra' => $ultimaCompra,
                    'test_mode' => !$this->ALLOW_SOAP_PURCHASE
                ]
            ]);

        } catch (\Exception $e) {
            $errorId = uniqid('err_');

            Log::error('Erro ao obter estatísticas', [
                'error_id' => $errorId,
                'method' => __METHOD__,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => request()->user()->id ?? null,
                'ip' => request()->ip(),
                'timestamp' => now()->toIso8601String()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno no processamento. Contate o suporte.',
                'error_id' => $errorId
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
            $errorId = uniqid('err_');

            Log::error('Erro ao validar pacote', [
                'error_id' => $errorId,
                'method' => __METHOD__,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'input' => $this->sanitizeLogData($request->all()),
                'user_id' => request()->user()->id ?? null,
                'ip' => request()->ip(),
                'timestamp' => now()->toIso8601String()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno no processamento. Contate o suporte.',
                'error_id' => $errorId
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

            // CORREÇÃO #5: Validar formato de placa brasileira
            $plateValidation = $this->validateBrazilianPlate($validated['placa']);
            if (!$plateValidation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Formato de placa inválido',
                    'error' => $plateValidation['error'],
                    'code' => 'PLACA_FORMATO_INVALIDO'
                ], 400);
            }

            // CORREÇÃO #6: Sanitiza dados sensíveis no log (LGPD)
            Log::info('API: Validando placa no SemParar', $this->sanitizeLogData([
                'placa' => $validated['placa'],
                'allow_soap_queries' => $this->ALLOW_SOAP_QUERIES
            ]));

            // Valida veículo (se ALLOW_SOAP_QUERIES=true, faz chamada SOAP real)
            $result = $this->progressService->validateVehicleStatusSemParar(
                $validated['placa'],
                !$this->ALLOW_SOAP_QUERIES  // false = chamada real, true = simulado
            );

            if (!$result['success']) {
                // CORREÇÃO #6: Sanitiza dados sensíveis no log (LGPD)
                Log::warning('Validação de placa falhou', $this->sanitizeLogData([
                    'placa' => $validated['placa'],
                    'error' => $result['error'],
                    'code' => $result['code'] ?? 'UNKNOWN'
                ]));

                return response()->json($result, 400);
            }

            // CORREÇÃO #6: Sanitiza dados sensíveis no log (LGPD)
            Log::info('Placa validada com sucesso', $this->sanitizeLogData([
                'placa' => $validated['placa'],
                'descricao' => $result['data']['descricao'],
                'eixos' => $result['data']['eixos']
            ]));

            // Salvar no cache para uso posterior
            $veiculoCache = \App\Models\VeiculoSemPararCache::updateFromSemParar(
                $result['data'],
                $this->ALLOW_SOAP_QUERIES, // Se foi chamada real
                auth()->id()
            );

            // Incluir dados do cache na resposta
            $responseData = $result['data'];
            $responseData['cache_id'] = $veiculoCache->id;
            $responseData['tipo_veiculo'] = $veiculoCache->tipo_veiculo;

            return response()->json([
                'success' => true,
                'message' => 'Placa validada com sucesso',
                'data' => $responseData,
                'test_mode' => !$this->ALLOW_SOAP_PURCHASE,
                'soap_real' => $this->ALLOW_SOAP_QUERIES,
                'cached' => true,
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
            $errorId = uniqid('err_');

            Log::error('Erro ao validar placa', [
                'error_id' => $errorId,
                'method' => __METHOD__,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'input' => $this->sanitizeLogData($request->all()),
                'user_id' => request()->user()->id ?? null,
                'ip' => request()->ip(),
                'timestamp' => now()->toIso8601String()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno no processamento. Contate o suporte.',
                'error_id' => $errorId
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
            $errorId = uniqid('err_');

            Log::error('Erro ao listar rotas', [
                'error_id' => $errorId,
                'method' => __METHOD__,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => request()->user()->id ?? null,
                'ip' => request()->ip(),
                'timestamp' => now()->toIso8601String()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno no processamento. Contate o suporte.',
                'error_id' => $errorId
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
            $errorId = uniqid('err_');

            Log::error('Erro ao validar rota', [
                'error_id' => $errorId,
                'method' => __METHOD__,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => request()->user()->id ?? null,
                'ip' => request()->ip(),
                'timestamp' => now()->toIso8601String()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno no processamento. Contate o suporte.',
                'error_id' => $errorId
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

            // CORREÇÃO #5: Validar formato de placa brasileira
            $plateValidation = $this->validateBrazilianPlate($validated['placa']);
            if (!$plateValidation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Formato de placa inválido',
                    'error' => $plateValidation['error'],
                    'code' => 'PLACA_FORMATO_INVALIDO'
                ], 400);
            }

            // CORREÇÃO #8: Validar datas da viagem
            $dateValidation = $this->validateTripDates($validated['data_inicio'], $validated['data_fim']);
            if (!$dateValidation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datas inválidas',
                    'error' => $dateValidation['error'],
                    'code' => 'DATAS_INVALIDAS'
                ], 400);
            }

            // CORREÇÃO #6: Sanitiza dados sensíveis no log (LGPD)
            Log::info('API: Verificando preço da viagem com rota temporária', $this->sanitizeLogData([
                'codpac' => $validated['codpac'],
                'cod_rota' => $validated['cod_rota'],
                'qtd_eixos' => $validated['qtd_eixos'],
                'placa' => $validated['placa'],
                'data_inicio' => $validated['data_inicio'],
                'data_fim' => $validated['data_fim'],
                'allow_soap_queries' => $this->ALLOW_SOAP_QUERIES
            ]));

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
            $errorId = uniqid('err_');

            Log::error('Erro ao verificar preço', [
                'error_id' => $errorId,
                'method' => __METHOD__,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => request()->user()->id ?? null,
                'ip' => request()->ip(),
                'timestamp' => now()->toIso8601String()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno no processamento. Contate o suporte.',
                'error_id' => $errorId
            ], 500);
        }
    }

    /**
     * Lista viagens compradas do Progress
     * Busca na tabela PUB.sPararViagem com filtros opcionais
     * Segue fluxo de consultaViagem.p
     * Suporta paginação server-side
     */
    public function listarViagens(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:5|max:100',
                'data_inicio' => 'required|date',
                'data_fim' => 'required|date|after_or_equal:data_inicio',
                'cod_pac' => 'nullable|integer',
                'placa' => 'nullable|string|max:10',
                's_parar_rot_id' => 'nullable|integer',
                'cod_trn' => 'nullable|integer'
            ]);

            $page = (int) $request->get('page', 1);
            $perPage = (int) $request->get('per_page', 15);

            Log::info('API: Listando viagens do Progress com filtros', $validated);

            $result = $this->progressService->getViagensCompradas(
                $validated['data_inicio'],
                $validated['data_fim'],
                $validated['cod_pac'] ?? null,
                $validated['placa'] ?? null,
                $validated['s_parar_rot_id'] ?? null,
                $validated['cod_trn'] ?? null,
                $page,
                $perPage
            );

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            return response()->json([
                'success' => true,
                'data' => $result['data'],
                'pagination' => $result['pagination'],
                'periodo' => [
                    'inicio' => $validated['data_inicio'],
                    'fim' => $validated['data_fim']
                ],
                'filtros' => [
                    'rota_id' => $validated['s_parar_rot_id'] ?? null,
                    'transportador' => $validated['cod_trn'] ?? null,
                    'placa' => $validated['placa'] ?? null,
                    'pacote' => $validated['cod_pac'] ?? null
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            $errorId = uniqid('err_');

            Log::error('Erro ao listar viagens', [
                'error_id' => $errorId,
                'method' => __METHOD__,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => request()->user()->id ?? null,
                'ip' => request()->ip(),
                'timestamp' => now()->toIso8601String()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno no processamento. Contate o suporte.',
                'error_id' => $errorId
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
                'flgretorno' => 'boolean',
                // CORREÇÃO #7: Suporte a idempotência (opcional)
                'idempotency_key' => 'nullable|string|uuid'
            ]);

            // CORREÇÃO #5: Validar formato de placa brasileira
            $plateValidation = $this->validateBrazilianPlate($validated['placa']);
            if (!$plateValidation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Formato de placa inválido',
                    'error' => $plateValidation['error'],
                    'code' => 'PLACA_FORMATO_INVALIDO'
                ], 400);
            }

            // CORREÇÃO #8: Validar datas da viagem
            $dateValidation = $this->validateTripDates($validated['data_inicio'], $validated['data_fim']);
            if (!$dateValidation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datas inválidas',
                    'error' => $dateValidation['error'],
                    'code' => 'DATAS_INVALIDAS'
                ], 400);
            }

            // ========================================================================
            // CORREÇÃO BUG IMPORTANTE #4: IDEMPOTÊNCIA - Fix race condition with atomic lock
            // ========================================================================
            // Se cliente enviou idempotency_key, verifica se já processamos essa requisição
            if (isset($validated['idempotency_key']) && !empty($validated['idempotency_key'])) {
                $cacheKey = 'idempotency:compra:' . $validated['idempotency_key'];
                $lockKey = 'idempotency:lock:' . $validated['idempotency_key'];

                // Primeiro tenta pegar resultado cached (fast path)
                $cachedResult = Cache::get($cacheKey);
                if ($cachedResult) {
                    Log::info('Requisição idempotente detectada - retornando resultado cached', [
                        'idempotency_key' => $validated['idempotency_key'],
                        'codpac' => $validated['codpac'],
                        'cached_at' => $cachedResult['cached_at'] ?? 'unknown'
                    ]);

                    return response()->json($cachedResult['response'], $cachedResult['status_code']);
                }

                // Se não tem cache, adquire lock antes de processar
                // Lock por 30 segundos (tempo máximo de processamento esperado)
                $lock = Cache::lock($lockKey, 30);

                if (!$lock->get()) {
                    // Outro request está processando, aguarda e retorna resultado
                    Log::warning('Idempotency lock collision - aguardando processamento', [
                        'idempotency_key' => $validated['idempotency_key'],
                        'ip' => request()->ip()
                    ]);

                    // Aguarda até 10 segundos para o lock ser liberado
                    sleep(2);
                    $cachedResult = Cache::get($cacheKey);
                    if ($cachedResult) {
                        return response()->json($cachedResult['response'], $cachedResult['status_code']);
                    }

                    // Se ainda não tem cache, retorna erro 409 Conflict
                    return response()->json([
                        'success' => false,
                        'message' => 'Requisição duplicada em processamento. Tente novamente em alguns segundos.',
                        'code' => 'IDEMPOTENCY_CONFLICT'
                    ], 409);
                }

                // Lock adquirido com sucesso - processar normalmente
                // IMPORTANTE: Lock será liberado automaticamente no final do método
            }

            // CORREÇÃO #6: Sanitiza dados sensíveis no log (LGPD) - não mascara valor em info
            Log::info('API: Iniciando compra de viagem', $this->sanitizeLogData([
                'codpac' => $validated['codpac'],
                'cod_rota' => $validated['cod_rota'],
                'placa' => $validated['placa'],
                'valor' => $validated['valor_viagem'],
                'idempotency_key' => $validated['idempotency_key'] ?? null,
                'allow_soap_purchase' => $this->ALLOW_SOAP_PURCHASE,
                'user_id' => request()->user()->id ?? null,
                'ip' => request()->ip(),
                'timestamp' => now()->toIso8601String()
            ], false));

            // ========================================================================
            // CORREÇÃO #1: RE-VALIDAÇÃO DE DUPLICATAS (Proteção contra Race Condition)
            // ========================================================================
            // Verifica novamente se viagem já foi comprada por outro usuário
            // Esta validação já foi feita no Step 3, mas é necessária novamente
            // porque pode ter havido compra concorrente entre a validação e a compra
            $viagemCheck = $this->progressService->viagemJaComprada(
                $validated['codpac'],
                $validated['cod_rota']
            );

            if ($viagemCheck['duplicada']) {
                $viagem = $viagemCheck['viagem'];

                // CORREÇÃO #6: Sanitiza dados sensíveis no log (LGPD)
                Log::warning('Tentativa de compra duplicada bloqueada', $this->sanitizeLogData([
                    'codpac' => $validated['codpac'],
                    'cod_rota' => $validated['cod_rota'],
                    'placa' => $validated['placa'],
                    'viagem_existente' => [
                        'codViagem' => $viagem['codViagem'],
                        'placa' => $viagem['NumPla'],
                        'valor' => $viagem['valViagem'],
                        'data' => $viagem['dataCompra']
                    ]
                ], true));

                return response()->json([
                    'success' => false,
                    'message' => 'Viagem já foi comprada',
                    'error' => sprintf(
                        'Viagem já foi comprada por outro usuário. Viagem %s, placa %s, R$ %.2f, data %s',
                        $viagem['codViagem'],
                        $viagem['NumPla'],
                        $viagem['valViagem'],
                        $viagem['dataCompra']
                    ),
                    'code' => 'VIAGEM_JA_COMPRADA',
                    'viagem_existente' => [
                        'codViagem' => $viagem['codViagem'],
                        'placa' => $viagem['NumPla'],
                        'valor' => $viagem['valViagem'],
                        'data' => $viagem['dataCompra']
                    ]
                ], 409); // 409 Conflict
            }

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

            // ========================================================================
            // CORREÇÃO #2: RE-VALIDAÇÃO DE EIXOS (Proteção contra Manipulação)
            // ========================================================================
            // Re-valida veículo no SemParar para obter número de eixos real
            // Previne manipulação de eixos no frontend (usuário alterando no dialog)
            $validacaoPlaca = $this->progressService->validateVehicleStatusSemParar(
                $validated['placa'],
                false  // false = chamada SOAP real
            );

            if (!$validacaoPlaca['success']) {
                // CORREÇÃO #6: Sanitiza dados sensíveis no log (LGPD)
                Log::error('Falha ao re-validar veículo antes da compra', $this->sanitizeLogData([
                    'placa' => $validated['placa'],
                    'error' => $validacaoPlaca['error'] ?? 'Erro desconhecido'
                ]));

                return response()->json([
                    'success' => false,
                    'message' => 'Falha ao validar veículo',
                    'error' => 'Não foi possível validar o veículo no sistema SemParar. Tente novamente.',
                    'code' => 'VEICULO_VALIDACAO_FALHOU'
                ], 500);
            }

            $eixosReais = $validacaoPlaca['data']['eixos'];

            // Verifica se número de eixos foi alterado (possível fraude)
            if ($validated['qtd_eixos'] != $eixosReais) {
                // CORREÇÃO #6: Sanitiza dados sensíveis no log (LGPD)
                Log::warning('Tentativa de manipulação de eixos detectada e bloqueada', $this->sanitizeLogData([
                    'placa' => $validated['placa'],
                    'eixos_reais' => $eixosReais,
                    'eixos_informados' => $validated['qtd_eixos'],
                    'codpac' => $validated['codpac']
                ]));

                return response()->json([
                    'success' => false,
                    'message' => 'Número de eixos incorreto',
                    'error' => sprintf(
                        'O veículo de placa %s possui %d eixos, não %d. Por favor, valide novamente a placa.',
                        $validated['placa'],
                        $eixosReais,
                        $validated['qtd_eixos']
                    ),
                    'code' => 'EIXOS_INVALIDOS',
                    'eixos_corretos' => $eixosReais
                ], 400);
            }

            Log::info('Eixos validados com sucesso', [
                'placa' => $validated['placa'],
                'eixos' => $eixosReais
            ]);

            // Comprar viagem no SemParar (real ou simulada)
            if ($this->ALLOW_SOAP_PURCHASE) {
                // COMPRA REAL via SOAP
                Log::info('Comprando viagem REAL no SemParar', [
                    'codpac' => $validated['codpac'],
                    'nome_rota' => $validated['nome_rota_semparar'],
                    'placa' => $validated['placa']
                ]);

                $resultadoCompra = $this->semPararService->comprarViagem(
                    $validated['nome_rota_semparar'],
                    $validated['placa'],
                    $eixosReais,  // CORREÇÃO: Usa eixos validados, não do frontend
                    $validated['data_inicio'],
                    $validated['data_fim'],
                    (string)$validated['codpac'] // item_fin1 = codpac (igual Progress linha 836)
                );

                if (!$resultadoCompra['success']) {
                    $errorId = uniqid('err_');

                    Log::error('Erro ao comprar viagem no SemParar', [
                        'error_id' => $errorId,
                        'method' => __METHOD__,
                        'error' => $resultadoCompra['error'] ?? 'Erro desconhecido',
                        'user_id' => request()->user()->id ?? null,
                        'ip' => request()->ip(),
                        'timestamp' => now()->toIso8601String()
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Erro ao processar compra no SemParar. Contate o suporte.',
                        'error_id' => $errorId,
                        'code' => 'ERRO_SEMPARAR'
                    ], 500);
                }

                $numeroViagem = $resultadoCompra['cod_viagem'];

                Log::info('Compra REAL bem-sucedida', [
                    'codpac' => $validated['codpac'],
                    'numero_viagem' => $numeroViagem
                ]);
            } else {
                // COMPRA SIMULADA
                $numeroViagem = 'SIM_' . time() . '_' . $validated['codpac'];

                Log::info('Compra SIMULADA com sucesso', [
                    'codpac' => $validated['codpac'],
                    'numero_viagem' => $numeroViagem
                ]);
            }

            // PASSO 1: Salva sPararViagem no Progress (linha 856-867)
            // resCompra no Progress espera string com nome do usuário (igual userid("dictdb"))
            // CORREÇÃO: Campo resCompra tem limite de 8 caracteres (tamanho userid Progress)
            $user = request()->user();
            $nomeUsuario = $user ? substr($user->name, 0, 8) : 'SYSTEM';

            $dadosViagem = [
                'codpac' => $validated['codpac'],
                'codRotCreateSP' => $validated['cod_rota_semparar'],
                'codtrn' => $codtrn,
                'codViagem' => $numeroViagem,
                'nomRotSemParar' => $validated['nome_rota_semparar'],
                'placa' => $validated['placa'],
                'rotaId' => $validated['cod_rota'],
                'valorViagem' => $validated['valor_viagem'],
                'usuario' => $nomeUsuario
            ];

            $resultViagem = $this->progressService->salvarSPararViagem($dadosViagem);

            if (!$resultViagem['success']) {
                $errorId = uniqid('err_');

                Log::error('Erro ao salvar viagem no Progress', [
                    'error_id' => $errorId,
                    'method' => __METHOD__,
                    'error' => $resultViagem['error'],
                    'cod_viagem' => $numeroViagem,
                    'user_id' => request()->user()->id ?? null,
                    'ip' => request()->ip(),
                    'timestamp' => now()->toIso8601String()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Viagem comprada mas erro ao salvar no banco. Contate o suporte.',
                    'error_id' => $errorId,
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

            // CORREÇÃO #6: Sanitiza dados sensíveis no log (LGPD) - mantém valor em info de sucesso
            Log::info('Compra de viagem concluída com sucesso', $this->sanitizeLogData([
                'codpac' => $validated['codpac'],
                'numero_viagem' => $numeroViagem,
                'placa' => $validated['placa'],
                'valor' => $validated['valor_viagem'],
                'user_id' => request()->user()->id ?? null,
                'ip' => request()->ip(),
                'timestamp' => now()->toIso8601String()
            ], false));

            // Prepara resposta de sucesso
            $responseData = [
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
                'warning' => $this->ALLOW_SOAP_PURCHASE
                    ? null
                    : '⚠️ Compra SIMULADA - ALLOW_SOAP_PURCHASE=false'
            ];

            // ========================================================================
            // CORREÇÃO #7: CACHEAR RESULTADO PARA IDEMPOTÊNCIA (24 horas)
            // ========================================================================
            if (isset($validated['idempotency_key']) && !empty($validated['idempotency_key'])) {
                $cacheKey = 'idempotency:compra:' . $validated['idempotency_key'];
                $cacheData = [
                    'response' => $responseData,
                    'status_code' => 200,
                    'cached_at' => now()->toIso8601String()
                ];

                // Cacheia por 24 horas (86400 segundos)
                Cache::put($cacheKey, $cacheData, 86400);

                Log::info('Resultado de compra cacheado para idempotência', [
                    'idempotency_key' => $validated['idempotency_key'],
                    'numero_viagem' => $numeroViagem,
                    'ttl_seconds' => 86400
                ]);
            }

            return response()->json($responseData);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            $errorId = uniqid('err_');

            Log::error('Erro ao comprar viagem', [
                'error_id' => $errorId,
                'method' => __METHOD__,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => request()->user()->id ?? null,
                'ip' => request()->ip(),
                'timestamp' => now()->toIso8601String()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno no processamento. Contate o suporte.',
                'error_id' => $errorId
            ], 500);
        }
    }

    /**
     * CORREÇÃO #5: Valida formato de placa brasileira
     *
     * Formatos aceitos:
     * - Antigo: ABC1234 (3 letras + 4 números)
     * - Mercosul: ABC1D23 (3 letras + 1 número + 1 letra + 2 números)
     *
     * @param string $placa
     * @return array{valid: bool, error?: string}
     */
    private function validateBrazilianPlate(string $placa): array
    {
        // Remove espaços e converte para maiúsculas
        $placa = strtoupper(trim($placa));

        // Verifica comprimento (deve ter exatamente 7 caracteres)
        if (strlen($placa) !== 7) {
            return [
                'valid' => false,
                'error' => 'Placa deve ter 7 caracteres'
            ];
        }

        // Regex para formato antigo: ABC1234
        $padraoAntigo = '/^[A-Z]{3}[0-9]{4}$/';

        // Regex para formato Mercosul: ABC1D23
        $padraoMercosul = '/^[A-Z]{3}[0-9][A-Z][0-9]{2}$/';

        if (preg_match($padraoAntigo, $placa) || preg_match($padraoMercosul, $placa)) {
            return ['valid' => true];
        }

        return [
            'valid' => false,
            'error' => 'Formato de placa inválido. Use ABC1234 (antigo) ou ABC1D23 (Mercosul)'
        ];
    }

    /**
     * CORREÇÃO #8: Valida datas da viagem
     *
     * Regras de negócio:
     * - Data início: máximo 7 dias no passado, máximo 90 dias no futuro
     * - Data fim: deve ser >= data_inicio
     * - Período: máximo 30 dias entre data_inicio e data_fim
     *
     * @param string $dataInicio Data início (Y-m-d)
     * @param string $dataFim Data fim (Y-m-d)
     * @return array{valid: bool, error?: string}
     */
    private function validateTripDates(string $dataInicio, string $dataFim): array
    {
        $inicio = \Carbon\Carbon::parse($dataInicio);
        $fim = \Carbon\Carbon::parse($dataFim);
        $hoje = \Carbon\Carbon::today();

        // Regra 1: Data início não pode ser mais de 7 dias no passado
        $limitePassado = $hoje->copy()->subDays(7);
        if ($inicio->lt($limitePassado)) {
            return [
                'valid' => false,
                'error' => sprintf(
                    'Data de início não pode ser anterior a %s (7 dias no passado)',
                    $limitePassado->format('d/m/Y')
                )
            ];
        }

        // Regra 2: Data início não pode ser mais de 90 dias no futuro
        $limiteFuturo = $hoje->copy()->addDays(90);
        if ($inicio->gt($limiteFuturo)) {
            return [
                'valid' => false,
                'error' => sprintf(
                    'Data de início não pode ser posterior a %s (90 dias no futuro)',
                    $limiteFuturo->format('d/m/Y')
                )
            ];
        }

        // Regra 3: Data fim deve ser >= data início (já validado pelo Laravel, mas reforça)
        if ($fim->lt($inicio)) {
            return [
                'valid' => false,
                'error' => 'Data de término deve ser igual ou posterior à data de início'
            ];
        }

        // Regra 4: Período máximo de 30 dias
        $diasPeriodo = $inicio->diffInDays($fim);
        if ($diasPeriodo > 30) {
            return [
                'valid' => false,
                'error' => sprintf(
                    'Período máximo permitido é de 30 dias. Período solicitado: %d dias',
                    $diasPeriodo
                )
            ];
        }

        return ['valid' => true];
    }

    /**
     * CORREÇÃO #6: Sanitiza dados sensíveis para logs (LGPD compliance)
     *
     * Mascara informações sensíveis antes de registrar em logs:
     * - Placas: ABC1234 -> ABC****
     * - Valores: 123.45 -> ***.** (apenas em warnings/errors)
     * - CPF/CNPJ: números mascarados
     *
     * @param array $data Dados a serem sanitizados
     * @param bool $maskValues Mascarar valores monetários (default: false)
     * @return array Dados sanitizados
     */
    private function sanitizeLogData(array $data, bool $maskValues = false): array
    {
        $sanitized = $data;

        // Mascara placa (ABC1234 -> ABC****)
        if (isset($sanitized['placa'])) {
            $placa = strtoupper($sanitized['placa']);
            $sanitized['placa'] = strlen($placa) >= 3
                ? substr($placa, 0, 3) . str_repeat('*', strlen($placa) - 3)
                : str_repeat('*', strlen($placa));
        }

        // Mascara valores monetários (apenas em warnings/errors de segurança)
        if ($maskValues) {
            if (isset($sanitized['valor'])) {
                $sanitized['valor'] = '***.**';
            }
            if (isset($sanitized['valor_viagem'])) {
                $sanitized['valor_viagem'] = '***.**';
            }
            if (isset($sanitized['valViagem'])) {
                $sanitized['valViagem'] = '***.**';
            }
        }

        // Mascara CPF/CNPJ se presente
        if (isset($sanitized['cpf'])) {
            $sanitized['cpf'] = '***.***.***-**';
        }
        if (isset($sanitized['cnpj'])) {
            $sanitized['cnpj'] = '**.***.***/****.--**';
        }

        // Mascara dados de viagem existente (em caso de duplicata)
        if (isset($sanitized['viagem_existente']) && is_array($sanitized['viagem_existente'])) {
            if (isset($sanitized['viagem_existente']['placa'])) {
                $placa = strtoupper($sanitized['viagem_existente']['placa']);
                $sanitized['viagem_existente']['placa'] = strlen($placa) >= 3
                    ? substr($placa, 0, 3) . str_repeat('*', strlen($placa) - 3)
                    : str_repeat('*', strlen($placa));
            }
            if (isset($sanitized['viagem_existente']['valor'])) {
                $sanitized['viagem_existente']['valor'] = '***.**';
            }
        }

        return $sanitized;
    }
}
