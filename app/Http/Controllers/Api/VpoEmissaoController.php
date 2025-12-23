<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Vpo\VpoEmissaoService;
use App\Services\ProgressService;
use App\Models\VpoEmissao;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

/**
 * Controller para emissão de Vale Pedágio Obrigatório (VPO) via NDD Cargo
 *
 * @see docs/integracoes/ndd-cargo/VPO_EMISSAO_WIZARD.md (futuro)
 */
class VpoEmissaoController extends Controller
{
    protected VpoEmissaoService $emissaoService;
    protected ProgressService $progressService;

    public function __construct(VpoEmissaoService $emissaoService, ProgressService $progressService)
    {
        $this->emissaoService = $emissaoService;
        $this->progressService = $progressService;
    }

    /**
     * 1. Iniciar emissão VPO
     *
     * POST /api/vpo/emissao/iniciar
     *
     * Body: {
     *   "codpac": 123456,
     *   "rota_id": 204
     * }
     */
    public function iniciar(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'codpac' => 'required|integer',
            'rota_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validação falhou',
                'errors' => $validator->errors()
            ], 422);
        }

        // Log dos dados recebidos do frontend para debug
        Log::info('VpoEmissaoController: Dados recebidos do frontend', [
            'codpac' => $request->codpac,
            'rota_id' => $request->rota_id,
            'pracas_pedagio_count' => is_array($request->input('pracas_pedagio')) ? count($request->input('pracas_pedagio')) : 0,
            'pracas_pedagio_sample' => array_slice($request->input('pracas_pedagio', []), 0, 2),
            'valor_total' => $request->input('valor_total'),
            'km_total' => $request->input('km_total'),
        ]);

        $result = $this->emissaoService->iniciarEmissao([
            'codpac' => $request->codpac,
            'rota_id' => $request->rota_id,
            'skip_validation' => $request->boolean('skip_validation', false), // TEMP: bypass para testes
            'usuario_id' => auth()->id() ?? null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            // Dados calculados no frontend (Step 4) - praças de pedágio, custo e distância
            'pracas_pedagio' => $request->input('pracas_pedagio', []),
            'valor_total' => $request->input('valor_total', 0),
            'km_total' => $request->input('km_total', 0),
            // Dados adicionais
            'codmot' => $request->input('codmot'),
            'placa' => $request->input('placa'),
            'eixos' => $request->input('eixos'),
            'data_inicio' => $request->input('data_inicio'),
            'data_fim' => $request->input('data_fim'),
        ]);

        if (!$result['success']) {
            $response = [
                'success' => false,
                'message' => $result['error']
            ];
            // Incluir detalhes de validação se disponíveis
            if (isset($result['validation_errors'])) {
                $response['validation_errors'] = $result['validation_errors'];
                $response['score_qualidade'] = $result['score_qualidade'] ?? null;
            }
            return response()->json($response, 400);
        }

        return response()->json([
            'success' => true,
            'data' => $result['data']->getSummary(),
            'message' => 'Emissão iniciada com sucesso'
        ]);
    }

    /**
     * 2. Consultar resultado (polling UUID)
     *
     * GET /api/vpo/emissao/{uuid}?force_retry=1
     *
     * Retorna dados completos da emissão para atualização no frontend
     *
     * Query params:
     * - force_retry: Se 1, tenta novamente mesmo se falhou com TIMEOUT
     */
    public function consultar(Request $request, string $uuid): JsonResponse
    {
        $forceRetry = $request->boolean('force_retry', false);
        $result = $this->emissaoService->consultarResultado($uuid, $forceRetry);

        if (!$result['success'] && $result['status'] === 'not_found') {
            return response()->json([
                'success' => false,
                'message' => 'Emissão não encontrada'
            ], 404);
        }

        // Retornar dados completos, não apenas o summary
        $emissao = $result['data'];
        $data = $emissao ? $emissao->toArray() : null;

        // Incluir relacionamento usuario se existir
        if ($emissao && $emissao->usuario) {
            $data['usuario'] = [
                'id' => $emissao->usuario->id,
                'name' => $emissao->usuario->name,
            ];
        }

        $response = [
            'success' => $result['success'],
            'data' => $data,
            'status' => $result['status'],
            'message' => $result['error'] ?? 'Consulta realizada'
        ];

        if (isset($result['retry_after'])) {
            $response['retry_after'] = $result['retry_after'];
        }

        return response()->json($response);
    }

    /**
     * 3. Cancelar emissão (local - apenas marca como cancelada)
     *
     * POST /api/vpo/emissao/{uuid}/cancelar
     *
     * Use para cancelar emissões que ainda não foram concluídas na NDD Cargo.
     * Para cancelar emissões já concluídas, use o endpoint /cancelar-ndd-cargo
     */
    public function cancelar(string $uuid): JsonResponse
    {
        $result = $this->emissaoService->cancelarEmissao($uuid);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error']
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $result['data']->getSummary(),
            'message' => 'Emissão cancelada'
        ]);
    }

    /**
     * 3.1 Cancelar emissão na NDD Cargo (envia cancelamento real)
     *
     * POST /api/vpo/emissao/{uuid}/cancelar-ndd-cargo
     *
     * Body: {
     *   "motivo": "Motivo do cancelamento (obrigatório, 1-500 chars)",
     *   "identificacao_tipo": "ide" ou "ndvp" (opcional, default: ide),
     *   "ndvp_numero": "123456789012" (obrigatório se tipo = ndvp),
     *   "ndvp_cod_verificador": "1234" (obrigatório se tipo = ndvp),
     *   "ide_numero": "uuid-ou-numero" (opcional, default: uuid da emissão),
     *   "ide_serie": "1016" (opcional, default: config nddcargo.serie_padrao)
     * }
     *
     * IMPORTANTE: Este endpoint envia o cancelamento para a NDD Cargo!
     * Só funciona para emissões com status = 'completed'
     */
    public function cancelarNddCargo(Request $request, string $uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'motivo' => 'required|string|min:1|max:500',
            'identificacao_tipo' => 'nullable|string|in:ide,ndvp',
            'ndvp_numero' => 'nullable|string|max:20',
            'ndvp_cod_verificador' => 'nullable|string|max:10',
            'ide_numero' => 'nullable|string|max:100',
            'ide_serie' => 'nullable|string|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validação falhou',
                'errors' => $validator->errors()
            ], 422);
        }

        // Montar options
        $options = [
            'identificacao_tipo' => $request->input('identificacao_tipo', 'ide'),
        ];

        if ($request->filled('ndvp_numero')) {
            $options['ndvp_numero'] = $request->input('ndvp_numero');
        }
        if ($request->filled('ndvp_cod_verificador')) {
            $options['ndvp_cod_verificador'] = $request->input('ndvp_cod_verificador');
        }
        if ($request->filled('ide_numero')) {
            $options['ide_numero'] = $request->input('ide_numero');
        }
        if ($request->filled('ide_serie')) {
            $options['ide_serie'] = $request->input('ide_serie');
        }

        Log::info('VpoEmissaoController: Solicitação de cancelamento NDD Cargo', [
            'uuid' => $uuid,
            'motivo' => $request->input('motivo'),
            'options' => $options,
            'user_id' => auth()->id(),
            'ip' => $request->ip()
        ]);

        $result = $this->emissaoService->cancelarEmissaoNddCargo(
            $uuid,
            $request->input('motivo'),
            $options
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
                'data' => $result['data'] ? $result['data']->getSummary() : null
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $result['data']->getSummary(),
            'message' => 'Emissão cancelada na NDD Cargo com sucesso'
        ]);
    }

    /**
     * 4. Listar emissões (histórico)
     *
     * GET /api/vpo/emissoes?codpac=123&status=completed&per_page=15
     */
    public function index(Request $request): JsonResponse
    {
        $query = VpoEmissao::query()->with('usuario');

        // Filtros
        if ($request->filled('codpac')) {
            $query->byPacote($request->codpac);
        }

        if ($request->filled('codtrn')) {
            $query->byTransportador($request->codtrn);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('rota_id')) {
            $query->where('rota_id', $request->rota_id);
        }

        // Paginação
        $perPage = min($request->input('per_page', 15), 100);
        $emissoes = $query->recent()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $emissoes->items(),
            'pagination' => [
                'current_page' => $emissoes->currentPage(),
                'per_page' => $emissoes->perPage(),
                'total' => $emissoes->total(),
                'last_page' => $emissoes->lastPage(),
            ]
        ]);
    }

    /**
     * 5. Listar rotas disponíveis para um pacote
     *
     * GET /api/vpo/emissao/pacote/{codpac}/rotas
     */
    public function rotasDisponiveis(int $codpac): JsonResponse
    {
        try {
            // Buscar todas as rotas ativas
            $sql = "SELECT sPararRotID, desSPararRot, flgCD, flgRetorno, tempoViagem FROM PUB.semPararRot ORDER BY desSPararRot";
            $result = $this->progressService->executeCustomQuery($sql);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro ao buscar rotas'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'codpac' => $codpac,
                    'rotas' => $result['data']['results']
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 6. Validar pacote antes de iniciar emissão
     *
     * POST /api/vpo/emissao/validar-pacote
     *
     * Body: { "codpac": 123456 }
     */
    public function validarPacote(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'codpac' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $codpac = $request->codpac;

        try {
            // Buscar pacote
            $sql = "SELECT codpac, codtrn, codmot, numpla, sitpac FROM PUB.pacote WHERE codpac = {$codpac}";
            $result = $this->progressService->executeCustomQuery($sql);

            if (!$result['success'] || empty($result['data']['results'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pacote não encontrado'
                ], 404);
            }

            $pacote = $result['data']['results'][0];

            // Buscar itinerário (verificar se tem GPS)
            $itinerario = $this->progressService->getItinerarioPacote($codpac);
            $temGps = false;
            $totalEntregas = 0;

            if ($itinerario['success'] && !empty($itinerario['data']['pedidos'])) {
                $totalEntregas = count($itinerario['data']['pedidos']);

                foreach ($itinerario['data']['pedidos'] as $pedido) {
                    if (!empty($pedido['gps_lat']) && !empty($pedido['gps_lon'])) {
                        $temGps = true;
                        break;
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'pacote' => $pacote,
                    'tem_gps' => $temGps,
                    'total_entregas' => $totalEntregas,
                    'valido' => $temGps && $totalEntregas > 0
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 7. Preview waypoints (para exibir no mapa antes de iniciar)
     *
     * POST /api/vpo/emissao/preview-waypoints
     *
     * Body: {
     *   "codpac": 123456,
     *   "rota_id": 204
     * }
     */
    public function previewWaypoints(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'codpac' => 'required|integer',
            'rota_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Buscar rota + waypoints (mesmo método usado no service)
            $rotaMunicipios = $this->progressService->getSemPararRotaWithMunicipios($request->rota_id);

            if (!$rotaMunicipios['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro ao buscar municípios da rota'
                ], 500);
            }

            $waypoints = [];

            // Municípios da rota
            foreach ($rotaMunicipios['data']['municipios'] ?? [] as $mun) {
                if (isset($mun['latitude']) && isset($mun['longitude'])) {
                    $waypoints[] = [
                        'lat' => (float) $mun['latitude'],
                        'lon' => (float) $mun['longitude'],
                        'tipo' => 'rota',
                        'nome' => $mun['desMun'] ?? ''
                    ];
                }
            }

            // Primeira + última entrega
            $itinerario = $this->progressService->getItinerarioPacote($request->codpac);

            if ($itinerario['success'] && !empty($itinerario['data']['pedidos'])) {
                $entregas = $itinerario['data']['pedidos'];

                // Primeira
                $primeira = $entregas[0];
                if (!empty($primeira['gps_lat']) && !empty($primeira['gps_lon'])) {
                    $waypoints[] = [
                        'lat' => $this->processGps($primeira['gps_lat']),
                        'lon' => $this->processGps($primeira['gps_lon']),
                        'tipo' => 'primeira_entrega',
                        'nome' => $primeira['razcli'] ?? ''
                    ];
                }

                // Última
                $ultima = end($entregas);
                if (!empty($ultima['gps_lat']) && !empty($ultima['gps_lon'])) {
                    $waypoints[] = [
                        'lat' => $this->processGps($ultima['gps_lat']),
                        'lon' => $this->processGps($ultima['gps_lon']),
                        'tipo' => 'ultima_entrega',
                        'nome' => $ultima['razcli'] ?? ''
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'waypoints' => $waypoints,
                    'total' => count($waypoints)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 8. Estatísticas de emissões VPO
     *
     * GET /api/vpo/emissao/statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            // Calcular média de tempo de processamento (compatível SQLite e MySQL)
            $avgSeconds = null;
            $emissoes = VpoEmissao::completed()
                ->whereNotNull('requested_at')
                ->whereNotNull('completed_at')
                ->get(['requested_at', 'completed_at']);

            if ($emissoes->count() > 0) {
                $totalSeconds = $emissoes->reduce(function ($carry, $emissao) {
                    return $carry + $emissao->completed_at->diffInSeconds($emissao->requested_at);
                }, 0);
                $avgSeconds = round($totalSeconds / $emissoes->count(), 2);
            }

            // Formato plano para o frontend
            $pending = VpoEmissao::where('status', 'pending')->count();
            $processing = VpoEmissao::processing()->count();
            $completed = VpoEmissao::completed()->count();
            $failed = VpoEmissao::failed()->count();
            $cancelled = VpoEmissao::where('status', 'cancelled')->count();
            $custoTotal = VpoEmissao::completed()->sum('custo_total') ?? 0;

            $stats = [
                'total' => VpoEmissao::count(),
                'pending' => $pending,
                'processing' => $processing,
                'completed' => $completed,
                'failed' => $failed,
                'cancelled' => $cancelled,
                'custo_total' => $custoTotal,
                'por_status' => [
                    'pending' => $pending,
                    'processing' => $processing,
                    'completed' => $completed,
                    'failed' => $failed,
                    'cancelled' => $cancelled,
                ],
                'media_tempo_processamento' => $avgSeconds,
                'ultimas_24h' => VpoEmissao::where('created_at', '>=', now()->subDay())->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // === HELPERS ===

    protected function processGps(?string $gpsString): ?float
    {
        if (!$gpsString || $gpsString === '0') return null;

        $value = (int) $gpsString;
        if ($value === 0) return null;

        $isNegative = ($gpsString[0] === '2' || $gpsString[0] === '3');
        $decimal = abs($value) / 10_000_000;

        return $isNegative ? -$decimal : $decimal;
    }
}
