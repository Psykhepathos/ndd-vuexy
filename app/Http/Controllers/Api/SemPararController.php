<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SemParar\SemPararService;
use App\Services\ProgressService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

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
                'message' => 'Connection test failed',
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
                'message' => $result['mensagem'] ?? 'Status retrieved',
                'data' => $result
            ], $result['success'] ? 200 : 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle status verification failed',
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
                'message' => 'Debug endpoints are disabled in production'
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
                'message' => 'Debug endpoints are disabled in production'
            ], 403);
        }

        $this->semPararService->clearCache();

        return response()->json([
            'success' => true,
            'message' => 'Token cache cleared successfully'
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
        $request->validate([
            'nome_rota' => 'required|string',
            'placa' => 'required|string|min:7|max:8',
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
            return response()->json([
                'success' => false,
                'message' => 'Erro ao comprar viagem',
                'error' => $e->getMessage()
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
        try {
            // Validate input
            $request->validate([
                'cod_viagem' => 'required|string|min:1|max:50'
            ]);

            $codViagem = $request->input('cod_viagem');

            // Call SemParar service to get receipt
            $result = $this->semPararService->obterRecibo($codViagem);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Recibo obtido com sucesso',
                    'data' => [
                        'recibo_pdf' => $result['recibo_pdf'],
                        'pdf_size_bytes' => strlen($result['recibo_pdf']),
                        'status' => $result['status'],
                        'status_mensagem' => $result['status_mensagem']
                    ]
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro ao obter recibo',
                    'data' => $result
                ], 400);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao obter recibo',
                'error' => $e->getMessage()
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
        try {
            // Validate input
            $request->validate([
                'cod_viagem' => 'required|string|min:1|max:50',
                'telefone' => 'required|string|min:12|max:15', // Format: 5531988892076
                'email' => 'nullable|email|max:255',
                'flg_imprime' => 'nullable|boolean'
            ]);

            $codViagem = $request->input('cod_viagem');
            $telefone = $request->input('telefone');
            $email = $request->input('email', '');
            $flgImprime = $request->input('flg_imprime', true);

            // Call SemParar service to generate and send receipt
            $result = $this->semPararService->gerarRecibo(
                $codViagem,
                $telefone,
                $email,
                $flgImprime
            );

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
            return response()->json([
                'success' => false,
                'message' => 'Erro ao gerar recibo',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
