<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SemParar\SemPararService;
use App\Services\ProgressService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * SemPararController - Test endpoints for FASE 1A + 1B + 2A + 2B
 *
 * Provides test endpoints to verify SOAP integration
 * Based on SEMPARAR_IMPLEMENTATION_ROADMAP.md task 1.8
 */
class SemPararController extends Controller
{
    protected SemPararService $semPararService;
    protected ProgressService $progressService;

    public function __construct(
        SemPararService $semPararService,
        ProgressService $progressService
    ) {
        $this->semPararService = $semPararService;
        $this->progressService = $progressService;
    }

    /**
     * Test SemParar SOAP connection and authentication
     *
     * GET /api/semparar/test-connection
     *
     * @return JsonResponse
     */
    public function testConnection(): JsonResponse
    {
        try {
            $result = $this->semPararService->testConnection();

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => $result
            ], $result['success'] ? 200 : 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Falha no teste de conexão',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify vehicle status in SemParar
     *
     * POST /api/semparar/status-veiculo
     * Body: {"placa": "ABC1234"}
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function statusVeiculo(Request $request): JsonResponse
    {
        $request->validate([
            'placa' => 'required|string|min:7|max:8'
        ]);

        try {
            $placa = $request->input('placa');
            $result = $this->semPararService->statusVeiculo($placa);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['mensagem'] ?? 'Status obtido',
                'data' => $result
            ], $result['success'] ? 200 : 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Falha ao verificar status do veículo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current session token (debug only)
     *
     * GET /api/semparar/debug/token
     *
     * @return JsonResponse
     */
    public function debugToken(): JsonResponse
    {
        if (!config('app.debug')) {
            return response()->json([
                'success' => false,
                'message' => 'Endpoints de debug desabilitados em produção'
            ], 403);
        }

        $token = $this->semPararService->getToken();

        return response()->json([
            'success' => true,
            'token' => $token,
            'token_length' => $token ? strlen($token) : 0,
            'is_cached' => $token !== null
        ]);
    }

    /**
     * Clear token cache (force re-authentication)
     *
     * POST /api/semparar/debug/clear-cache
     *
     * @return JsonResponse
     */
    public function clearCache(): JsonResponse
    {
        if (!config('app.debug')) {
            return response()->json([
                'success' => false,
                'message' => 'Endpoints de debug desabilitados em produção'
            ], 403);
        }

        $this->semPararService->clearCache();

        return response()->json([
            'success' => true,
            'message' => 'Cache do token limpo com sucesso'
        ]);
    }

    /**
     * Roteirizar praças de pedágio
     *
     * POST /api/semparar/roteirizar
     * Body: {
     *   "pontos": [
     *     {"cod_ibge": 3118601, "desc": "CONTAGEM", "latitude": 0, "longitude": 0},
     *     ...
     *   ],
     *   "alternativas": false
     * }
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function roteirizar(Request $request): JsonResponse
    {
        $request->validate([
            'pontos' => 'required|array|min:2',
            'pontos.*.cod_ibge' => 'required|integer',
            'pontos.*.desc' => 'required|string',
            'pontos.*.latitude' => 'nullable|numeric',
            'pontos.*.longitude' => 'nullable|numeric',
            'alternativas' => 'nullable|boolean'
        ]);

        try {
            $pontos = $request->input('pontos');
            $alternativas = $request->input('alternativas', false);

            $result = $this->semPararService->roteirizarPracasPedagio($pontos, $alternativas);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['success'] ? 'Roteirização concluída' : 'Erro na roteirização',
                'data' => $result
            ], $result['success'] ? 200 : 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao roteirizar',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cadastrar rota temporária
     *
     * POST /api/semparar/rota-temporaria
     * Body: {
     *   "praca_ids": [123, 456, 789],
     *   "nome_rota": "ROTA_TEMP_123456"
     * }
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function cadastrarRotaTemporaria(Request $request): JsonResponse
    {
        $request->validate([
            'praca_ids' => 'required|array|min:1',
            'praca_ids.*' => 'required|integer',
            'nome_rota' => 'required|string|max:100'
        ]);

        try {
            $pracaIds = $request->input('praca_ids');
            $nomeRota = $request->input('nome_rota');

            $result = $this->semPararService->cadastrarRotaTemporaria($pracaIds, $nomeRota);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['success'] ? 'Rota temporária criada' : 'Erro ao criar rota',
                'data' => $result
            ], $result['success'] ? 200 : 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao cadastrar rota temporária',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obter custo da rota
     *
     * POST /api/semparar/custo-rota
     * Body: {
     *   "nome_rota": "ROTA_TEMP_123456",
     *   "placa": "ABC1234",
     *   "eixos": 2,
     *   "data_inicio": "2025-10-27",
     *   "data_fim": "2025-11-03"
     * }
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function obterCustoRota(Request $request): JsonResponse
    {
        $request->validate([
            'nome_rota' => 'required|string',
            'placa' => 'required|string|min:7|max:8',
            'eixos' => 'required|integer|min:2|max:9',
            'data_inicio' => 'required|date',
            'data_fim' => 'required|date|after_or_equal:data_inicio'
        ]);

        try {
            $result = $this->semPararService->obterCustoRota(
                $request->input('nome_rota'),
                $request->input('placa'),
                $request->input('eixos'),
                $request->input('data_inicio'),
                $request->input('data_fim')
            );

            return response()->json([
                'success' => $result['success'],
                'message' => $result['success'] ? 'Custo obtido com sucesso' : 'Erro ao obter custo',
                'data' => $result
            ], $result['success'] ? 200 : 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao obter custo da rota',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Comprar viagem (efetivar compra)
     *
     * POST /api/semparar/comprar-viagem
     * Body: {
     *   "nome_rota": "ROTA_TEMP_123456",
     *   "placa": "ABC1234",
     *   "eixos": 2,
     *   "data_inicio": "2025-10-27",
     *   "data_fim": "2025-11-03",
     *   "item_fin1": "PEDAGIO",
     *   "item_fin2": "",
     *   "item_fin3": ""
     * }
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function comprarViagem(Request $request): JsonResponse
    {
        // CORREÇÃO BUG #13: Validação de placa brasileira (ABC1234 ou ABC1D23 Mercosul)
        $validated = $request->validate([
            'nome_rota' => 'required|string',
            'placa' => [
                'required',
                'string',
                'regex:/^[A-Z]{3}\d{4}$|^[A-Z]{3}\d[A-Z]\d{2}$/'  // ABC1234 ou ABC1D23 (Mercosul)
            ],
            'eixos' => 'required|integer|min:2|max:9',
            'data_inicio' => 'required|date',
            'data_fim' => 'required|date|after_or_equal:data_inicio',
            'item_fin1' => 'nullable|string|max:50',
            'item_fin2' => 'nullable|string|max:50',
            'item_fin3' => 'nullable|string|max:50',
            // FASE 2B - Campos para salvar no Progress
            'cod_pac' => 'nullable|integer',
            'cod_trn' => 'nullable|integer',
            'cod_rota_create_sp' => 'nullable|string',
            's_parar_rot_id' => 'nullable|integer',
            'valor_viagem' => 'nullable|numeric',
            'res_compra' => 'nullable|string|max:50'
        ]);

        // CORREÇÃO BUG CRÍTICO #1: Verificar autenticação ANTES de query (prevenir information disclosure)
        if (!empty($validated['cod_pac'])) {
            // Verificar autenticação primeiro
            $user = auth()->user();
            if (!$user) {
                Log::warning('Tentativa de compra sem autenticação', [
                    'cod_pac' => $validated['cod_pac'],
                    'ip' => $request->ip(),
                    'timestamp' => now()->toIso8601String()
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Autenticação requerida'
                ], 401);
            }

            // Agora buscar pacote
            $pacote = DB::connection('progress')->select(
                "SELECT codtrn FROM PUB.pacote WHERE codpac = ?",
                [$validated['cod_pac']]
            )[0] ?? null;

            if (!$pacote) {
                return response()->json([
                    'success' => false,
                    'error' => 'Pacote não encontrado'
                ], 404);
            }

            // Verificar se usuário tem permissão (admin ou dono do transporte)
            if ($user->role !== 'admin' && $user->codtrn != $pacote->codtrn) {
                Log::warning('Tentativa de compra não autorizada', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'cod_pac' => $validated['cod_pac'],
                    'pacote_codtrn' => $pacote->codtrn,
                    'user_codtrn' => $user->codtrn,
                    'ip' => $request->ip(),
                    'timestamp' => now()->toIso8601String()
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Você não tem permissão para comprar viagem com este pacote'
                ], 403);
            }
        }

        // CORREÇÃO #7: LGPD Art. 46 - Logging de operação financeira
        $user = $request->user();
        Log::info('Compra de viagem SemParar iniciada', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'placa' => $request->input('placa'),
            'nome_rota' => $request->input('nome_rota'),
            'valor_estimado' => $request->input('valor_viagem'),
            'timestamp' => now()->toIso8601String()
        ]);

        try {
            // FASE 2A - Comprar viagem no SemParar
            $result = $this->semPararService->comprarViagem(
                $request->input('nome_rota'),
                $request->input('placa'),
                $request->input('eixos'),
                $request->input('data_inicio'),
                $request->input('data_fim'),
                $request->input('item_fin1') ?? '',
                $request->input('item_fin2') ?? '',
                $request->input('item_fin3') ?? ''
            );

            // CORREÇÃO #7: Log do resultado da compra
            Log::info('Compra de viagem ' . ($result['success'] ? 'concluída' : 'falhou'), [
                'user_id' => $user->id,
                'cod_viagem' => $result['cod_viagem'] ?? null,
                'status' => $result['status'] ?? null,
                'timestamp' => now()->toIso8601String()
            ]);

            // Se compra foi bem-sucedida E temos dados para salvar no Progress
            if ($result['success'] && $request->has('cod_pac')) {
                // FASE 2B - Salvar viagem no Progress Database
                $progressResult = $this->progressService->salvarViagemSemParar([
                    'codViagem' => $result['cod_viagem'],
                    'codPac' => $request->input('cod_pac'),
                    'placa' => $request->input('placa'),
                    'nomeRotaSemParar' => $request->input('nome_rota'),
                    'codRotaCreateSp' => $request->input('cod_rota_create_sp', ''),
                    'sPararRotID' => $request->input('s_parar_rot_id', 0),
                    'valorViagem' => $request->input('valor_viagem', 0),
                    'codTrn' => $request->input('cod_trn', 0),
                    'resCompra' => $request->input('res_compra', 'sistema')
                ]);

                // Adicionar resultado da persistência ao response
                $result['progress_saved'] = $progressResult['success'];
                if (!$progressResult['success']) {
                    $result['progress_error'] = $progressResult['error'];
                }
            }

            return response()->json([
                'success' => $result['success'],
                'message' => $result['success'] ? 'Viagem comprada com sucesso' : 'Erro na compra',
                'data' => $result
            ], $result['success'] ? 200 : 400);
        } catch (\Exception $e) {
            // CORREÇÃO #7: Log completo (interno), mensagem genérica (público)
            $errorId = uniqid('err_');
            Log::error('Erro ao comprar viagem', [
                'error_id' => $errorId,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'input' => $request->except(['password', 'token']),
                'timestamp' => now()->toIso8601String()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno ao processar compra. Contate o suporte.',
                'error_id' => $errorId
            ], 500);
        }
    }

    /**
     * Obter recibo da viagem em PDF (FASE 2C)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function obterRecibo(Request $request): JsonResponse
    {
        // Validate input
        $request->validate([
            'cod_viagem' => 'required|string|min:1|max:50'
        ]);

        $codViagem = $request->input('cod_viagem');

        // CORREÇÃO #7: LGPD Art. 46 - Logging de acesso a dados sensíveis
        $user = $request->user();
        Log::info('Consulta de recibo SemParar', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'ip' => $request->ip(),
            'cod_viagem' => $codViagem,
            'timestamp' => now()->toIso8601String()
        ]);

        try {
            // Call SemParar service to get receipt data
            // NOTE: SOAP returns trip data (pracas, total, etc.), NOT PDF
            $result = $this->semPararService->obterRecibo($codViagem);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Dados do recibo obtidos com sucesso',
                    'data' => $result['data'],  // Full SOAP response data
                    'status' => $result['status'],
                    'status_mensagem' => $result['status_mensagem'],
                    'note' => 'SOAP retorna dados da viagem, não PDF. Use /gerar-recibo para gerar PDF e enviar por WhatsApp.'
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro ao obter dados do recibo',
                    'data' => $result
                ], 400);
            }

        } catch (\Exception $e) {
            // CORREÇÃO #7: Log completo (interno), mensagem genérica (público)
            $errorId = uniqid('err_');
            Log::error('Erro ao obter recibo', [
                'error_id' => $errorId,
                'user_id' => $user->id,
                'cod_viagem' => $codViagem,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'timestamp' => now()->toIso8601String()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno ao obter recibo. Contate o suporte.',
                'error_id' => $errorId
            ], 500);
        }
    }

    /**
     * Gerar recibo e enviar por WhatsApp/Email (FASE 2C - Correção)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function gerarRecibo(Request $request): JsonResponse
    {
        // Validate input
        // CORREÇÃO BUG #11: Validar email corretamente
        $request->validate([
            'cod_viagem' => 'required|string|min:1|max:50',
            'telefone' => 'required|string|min:12|max:15', // Format: 5531988892076
            'email' => 'nullable|email|max:255', // CORREÇÃO BUG #11: email validation
            'flg_imprime' => 'nullable|boolean'
        ]);

        $codViagem = $request->input('cod_viagem');
        $telefone = $request->input('telefone');
        $email = $request->input('email') ?? '';  // Fix: use null coalescing
        $flgImprime = $request->input('flg_imprime', true);

        // CORREÇÃO #7: LGPD Art. 46 - Logging de compartilhamento de dados (WhatsApp/Email)
        $user = $request->user();
        Log::info('Geração e envio de recibo SemParar', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'ip' => $request->ip(),
            'cod_viagem' => $codViagem,
            'telefone' => substr($telefone, 0, 4) . '****' . substr($telefone, -4),  // Mascarar telefone
            'email_fornecido' => !empty($email),
            'timestamp' => now()->toIso8601String()
        ]);

        try {
            // Call SemParar service to generate and send receipt
            $result = $this->semPararService->gerarRecibo(
                $codViagem,
                $telefone,
                $email,
                $flgImprime
            );

            // CORREÇÃO #7: Log do envio bem-sucedido
            if ($result['success']) {
                Log::info('Recibo enviado com sucesso', [
                    'user_id' => $user->id,
                    'cod_viagem' => $codViagem,
                    'timestamp' => now()->toIso8601String()
                ]);
            }

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Recibo gerado e enviado com sucesso',
                    'data' => $result
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro ao gerar recibo',
                    'data' => $result
                ], 400);
            }

        } catch (\Exception $e) {
            // CORREÇÃO #7: Log completo (interno), mensagem genérica (público)
            $errorId = uniqid('err_');
            Log::error('Erro ao gerar recibo', [
                'error_id' => $errorId,
                'user_id' => $user->id,
                'cod_viagem' => $codViagem,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'timestamp' => now()->toIso8601String()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno ao gerar recibo. Contate o suporte.',
                'error_id' => $errorId
            ], 500);
        }
    }

    /**
     * Consultar viagens por período (FASE 3A)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function consultarViagens(Request $request): JsonResponse
    {
        // Validate input
        $request->validate([
            'data_inicio' => 'required|date|date_format:Y-m-d',
            'data_fim' => 'required|date|date_format:Y-m-d|after_or_equal:data_inicio'
        ]);

        $dataInicio = $request->input('data_inicio');
        $dataFim = $request->input('data_fim');

        // CORREÇÃO #7: LGPD Art. 46 - Logging de acesso a dados sensíveis
        $user = $request->user();
        Log::info('Consulta de viagens SemParar', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'ip' => $request->ip(),
            'periodo' => ['inicio' => $dataInicio, 'fim' => $dataFim],
            'timestamp' => now()->toIso8601String()
        ]);

        try {
            // Call SemParar service
            $result = $this->semPararService->consultarViagens($dataInicio, $dataFim);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Viagens consultadas com sucesso',
                    'data' => $result
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro ao consultar viagens',
                    'data' => $result
                ], 400);
            }

        } catch (\Exception $e) {
            // CORREÇÃO #7: Log completo (interno), mensagem genérica (público)
            $errorId = uniqid('err_');
            Log::error('Erro ao consultar viagens', [
                'error_id' => $errorId,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'timestamp' => now()->toIso8601String()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno ao consultar viagens. Contate o suporte.',
                'error_id' => $errorId
            ], 500);
        }
    }

    /**
     * Cancelar viagem (FASE 3A)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function cancelarViagem(Request $request): JsonResponse
    {
        // Validate input
        $request->validate([
            'cod_viagem' => 'required|string|min:1|max:50'
        ]);

        $codViagem = $request->input('cod_viagem');

        // CORREÇÃO #7: LGPD Art. 46 - Logging de operação irreversível ANTES de executar
        $user = $request->user();
        Log::warning('Cancelamento de viagem SemParar solicitado', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'cod_viagem' => $codViagem,
            'timestamp' => now()->toIso8601String()
        ]);

        try {
            // Call SemParar service (passa email do usuário como responsável)
            $result = $this->semPararService->cancelarViagem($codViagem, $user->email);

            // CORREÇÃO #7: Log do resultado (operação irreversível!)
            Log::warning('Cancelamento de viagem ' . ($result['success'] ? 'concluído' : 'falhou'), [
                'user_id' => $user->id,
                'cod_viagem' => $codViagem,
                'status' => $result['status'] ?? null,
                'timestamp' => now()->toIso8601String()
            ]);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Viagem cancelada com sucesso',
                    'data' => $result
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro ao cancelar viagem',
                    'data' => $result
                ], 400);
            }

        } catch (\Exception $e) {
            // CORREÇÃO #7: Log completo (interno), mensagem genérica (público)
            $errorId = uniqid('err_');
            Log::error('Erro ao cancelar viagem', [
                'error_id' => $errorId,
                'user_id' => $user->id,
                'cod_viagem' => $codViagem,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'timestamp' => now()->toIso8601String()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno ao cancelar viagem. Contate o suporte.',
                'error_id' => $errorId
            ], 500);
        }
    }

    /**
     * Reemitir viagem com nova placa (FASE 3A)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function reemitirViagem(Request $request): JsonResponse
    {
        // Validate input
        $request->validate([
            'cod_viagem' => 'required|string|min:1|max:50',
            'placa' => 'required|string|size:7' // Brazilian license plate format: ABC1234
        ]);

        $codViagem = $request->input('cod_viagem');
        $placa = strtoupper($request->input('placa')); // Uppercase plate

        // CORREÇÃO #7: LGPD Art. 46 - Logging de alteração de dados
        $user = $request->user();
        Log::warning('Reemissão de viagem SemParar solicitada', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'cod_viagem' => $codViagem,
            'placa_nova' => $placa,
            'timestamp' => now()->toIso8601String()
        ]);

        try {
            // Call SemParar service
            $result = $this->semPararService->reemitirViagem($codViagem, $placa);

            // CORREÇÃO #7: Log do resultado
            Log::warning('Reemissão de viagem ' . ($result['success'] ? 'concluída' : 'falhou'), [
                'user_id' => $user->id,
                'cod_viagem' => $codViagem,
                'placa' => $placa,
                'status' => $result['status'] ?? null,
                'timestamp' => now()->toIso8601String()
            ]);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Viagem reemitida com sucesso',
                    'data' => $result
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro ao reemitir viagem',
                    'data' => $result
                ], 400);
            }

        } catch (\Exception $e) {
            // CORREÇÃO #7: Log completo (interno), mensagem genérica (público)
            $errorId = uniqid('err_');
            Log::error('Erro ao reemitir viagem', [
                'error_id' => $errorId,
                'user_id' => $user->id,
                'cod_viagem' => $codViagem,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'timestamp' => now()->toIso8601String()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno ao reemitir viagem. Contate o suporte.',
                'error_id' => $errorId
            ], 500);
        }
    }
}
