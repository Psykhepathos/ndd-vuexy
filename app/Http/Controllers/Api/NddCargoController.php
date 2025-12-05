<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NddCargo\NddCargoService;
use App\Services\NddCargo\DTOs\ConsultarRoteirizadorRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Controller para integração com API NDD Cargo
 *
 * Disponibiliza endpoints REST para:
 * - Consultar roteirizador (cálculo de rotas e praças de pedágio)
 * - Consultar resultados assíncronos
 * - Testar conectividade
 *
 * Rate Limiting:
 * - Consultas: 60 req/min
 * - Testes: 10 req/min
 *
 * @see docs/integracoes/ndd-cargo/README.md
 */
class NddCargoController extends Controller
{
    /**
     * @var NddCargoService
     */
    private NddCargoService $nddCargoService;

    public function __construct(NddCargoService $nddCargoService)
    {
        $this->nddCargoService = $nddCargoService;

        // Rate limiting
        $this->middleware('throttle:60,1')->only([
            'consultarRoteirizador',
            'consultarRotaSimples'
        ]);
        $this->middleware('throttle:10,1')->only(['testConnection']);
    }

    /**
     * Consulta roteirizador completo
     *
     * POST /api/ndd-cargo/roteirizador
     *
     * Body:
     * {
     *   "cnpj_empresa": "17359233000188",
     *   "cnpj_contratante": "17359233000188",
     *   "categoria_pedagio": 7,
     *   "pontos_parada": {
     *     "origem": "01310100",
     *     "destino": "20040020"
     *   },
     *   "tipo_rota_padrao": 1,
     *   "evitar_pedagogios": false,
     *   "priorizar_rodovias": false,
     *   "tipo_rota": 1,
     *   "tipo_veiculo": 5,
     *   "retornar_trecho": false
     * }
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function consultarRoteirizador(Request $request): JsonResponse
    {
        try {
            // Validação
            $validator = Validator::make($request->all(), [
                'cnpj_empresa' => 'required|string|size:14',
                'cnpj_contratante' => 'required|string|size:14',
                'categoria_pedagio' => 'integer|min:1|max:7',
                'pontos_parada' => 'required|array',
                'pontos_parada.origem' => 'required|string|size:8',
                'pontos_parada.destino' => 'required|string|size:8',
                'tipo_rota_padrao' => 'integer|min:1|max:3',
                'evitar_pedagogios' => 'boolean',
                'priorizar_rodovias' => 'boolean',
                'tipo_rota' => 'integer|min:1|max:3',
                'tipo_veiculo' => 'integer|min:1|max:10',
                'retornar_trecho' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dados inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Criar DTO
            $dto = ConsultarRoteirizadorRequest::fromArray($request->all());

            // Consultar roteirizador
            $response = $this->nddCargoService->consultarRoteirizador($dto);

            // Retornar resposta
            if ($response->sucesso) {
                return response()->json([
                    'success' => true,
                    'data' => $response->toArray()
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $response->mensagem,
                    'status' => $response->status
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Erro no endpoint consultarRoteirizador', [
                'erro' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno ao processar requisição',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Consulta rota simples (apenas CEPs origem/destino)
     *
     * POST /api/ndd-cargo/rota-simples
     *
     * Body:
     * {
     *   "cep_origem": "01310100",
     *   "cep_destino": "20040020",
     *   "categoria_pedagio": 7
     * }
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function consultarRotaSimples(Request $request): JsonResponse
    {
        try {
            // Validação
            $validator = Validator::make($request->all(), [
                'cep_origem' => 'required|string|size:8',
                'cep_destino' => 'required|string|size:8',
                'categoria_pedagio' => 'integer|min:1|max:7',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dados inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Consultar
            $response = $this->nddCargoService->consultarRotaSimples(
                cepOrigem: $request->input('cep_origem'),
                cepDestino: $request->input('cep_destino'),
                categoriaPedagio: $request->input('categoria_pedagio', 7)
            );

            // Retornar resposta
            if ($response->sucesso) {
                return response()->json([
                    'success' => true,
                    'data' => $response->toArray()
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $response->mensagem,
                    'status' => $response->status
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Erro no endpoint consultarRotaSimples', [
                'erro' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno ao processar requisição',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Consulta resultado de operação assíncrona
     *
     * GET /api/ndd-cargo/resultado/{guid}
     *
     * @param string $guid UUID da transação original
     * @return JsonResponse
     */
    public function consultarResultado(string $guid): JsonResponse
    {
        try {
            // Validar GUID
            if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $guid)) {
                return response()->json([
                    'success' => false,
                    'message' => 'GUID inválido'
                ], 422);
            }

            // Consultar resultado
            $response = $this->nddCargoService->consultarResultado($guid);

            // Retornar resposta
            if ($response->sucesso) {
                return response()->json([
                    'success' => true,
                    'data' => $response->toArray()
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $response->mensagem,
                    'status' => $response->status
                ], $response->status === -2 ? 404 : 400);
            }

        } catch (\Exception $e) {
            Log::error('Erro no endpoint consultarResultado', [
                'guid' => $guid,
                'erro' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno ao processar requisição',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Testa conexão com API NDD Cargo
     *
     * GET /api/ndd-cargo/test-connection
     *
     * @return JsonResponse
     */
    public function testConnection(): JsonResponse
    {
        try {
            $result = $this->nddCargoService->testConnection();

            return response()->json($result, $result['success'] ? 200 : 500);

        } catch (\Exception $e) {
            Log::error('Erro no teste de conexão NDD Cargo', [
                'erro' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao testar conexão',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Retorna informações sobre a integração NDD Cargo
     *
     * GET /api/ndd-cargo/info
     *
     * @return JsonResponse
     */
    public function info(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'name' => 'NDD Cargo API Integration',
                'version' => '1.0.0',
                'environment' => config('nddcargo.environment'),
                'endpoint' => config('nddcargo.endpoint_url'),
                'versao_layout' => config('nddcargo.versao_layout'),
                'documentation' => [
                    'overview' => url('/docs/integracoes/ndd-cargo/README.md'),
                    'index' => url('/docs/integracoes/ndd-cargo/INDEX.md'),
                    'python_analysis' => url('/docs/integracoes/ndd-cargo/ANALISE_NTESTE_PY.md'),
                ],
                'endpoints' => [
                    'test_connection' => url('/api/ndd-cargo/test-connection'),
                    'info' => url('/api/ndd-cargo/info'),
                    'roteirizador' => url('/api/ndd-cargo/roteirizador'),
                    'rota_simples' => url('/api/ndd-cargo/rota-simples'),
                    'resultado' => url('/api/ndd-cargo/resultado/{guid}'),
                ],
            ]
        ]);
    }
}
