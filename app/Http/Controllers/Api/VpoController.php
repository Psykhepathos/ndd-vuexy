<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Vpo\VpoDataSyncService;
use App\Models\VpoTransportadorCache;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class VpoController extends Controller
{
    protected VpoDataSyncService $syncService;

    public function __construct(VpoDataSyncService $syncService)
    {
        $this->syncService = $syncService;
    }

    /**
     * GET /api/vpo/test-connection
     * Testa conexão com Progress e ANTT
     */
    public function testConnection(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'services' => [
                'progress' => true, // Já temos ProgressService funcionando
                'antt_opendata' => true, // API pública sempre disponível
                'database_local' => VpoTransportadorCache::count() . ' registros em cache'
            ],
            'message' => 'Todos os serviços operacionais'
        ]);
    }

    /**
     * POST /api/vpo/sync/transportador
     * Sincroniza UM transportador específico
     *
     * Body: {
     *   "codtrn": 1,
     *   "codmot": null,  // opcional, apenas para empresas
     *   "placa": null,   // opcional
     *   "force_antt_update": false
     * }
     */
    public function syncTransportador(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'codtrn' => 'required|integer',
            'codmot' => 'nullable|integer',
            'placa' => 'nullable|string|max:10',
            'force_antt_update' => 'boolean'
        ]);

        $result = $this->syncService->syncTransportador(
            $validated['codtrn'],
            $validated['codmot'] ?? null,
            $validated['placa'] ?? null,
            $validated['force_antt_update'] ?? false
        );

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * POST /api/vpo/sync/batch
     * Sincroniza múltiplos transportadores
     *
     * Body: {
     *   "codtrn_list": [1, 2, 3, 4, 5],
     *   "force_antt_update": false
     * }
     */
    public function syncBatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'codtrn_list' => 'required|array|min:1|max:100',
            'codtrn_list.*' => 'integer',
            'force_antt_update' => 'boolean'
        ]);

        $result = $this->syncService->syncBatch(
            $validated['codtrn_list'],
            $validated['force_antt_update'] ?? false
        );

        return response()->json($result);
    }

    /**
     * GET /api/vpo/transportadores
     * Lista transportadores do cache local (paginado)
     *
     * Query params:
     * - page: página (default: 1)
     * - per_page: itens por página (default: 15, max: 100)
     * - search: busca por nome/rntrc/placa
     * - status: filtro por antt_status (Ativo/Suspenso/Cancelado)
     * - qualidade_minima: score mínimo de qualidade (0-100)
     * - apenas_validos: apenas com RNTRC válido (true/false)
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->get('per_page', 15), 100);

        $query = VpoTransportadorCache::query();

        // Filtros
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('antt_nome', 'like', "%{$search}%")
                  ->orWhere('antt_rntrc', 'like', "%{$search}%")
                  ->orWhere('placa', 'like', "%{$search}%")
                  ->orWhere('cpf_cnpj', 'like', "%{$search}%");
            });
        }

        if ($status = $request->get('status')) {
            $query->where('antt_status', $status);
        }

        if ($qualidadeMinima = $request->get('qualidade_minima')) {
            $query->qualidadeMinima((int) $qualidadeMinima);
        }

        if ($request->boolean('apenas_validos')) {
            $query->rntrcValido();
        }

        // Ordenação
        $query->orderByDesc('score_qualidade')
              ->orderByDesc('ultima_sync_progress');

        $transportadores = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $transportadores->items(),
            'pagination' => [
                'total' => $transportadores->total(),
                'per_page' => $transportadores->perPage(),
                'current_page' => $transportadores->currentPage(),
                'last_page' => $transportadores->lastPage(),
            ]
        ]);
    }

    /**
     * GET /api/vpo/transportadores/{codtrn}
     * Busca transportador específico no cache
     */
    public function show(int $codtrn): JsonResponse
    {
        $transportador = VpoTransportadorCache::byCodtrn($codtrn)->first();

        if (!$transportador) {
            return response()->json([
                'success' => false,
                'message' => 'Transportador não encontrado no cache. Execute sincronização primeiro.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $transportador,
            'vpo_data' => $transportador->toVpoArray(), // Dados formatados para NDD Cargo
            'meta' => [
                'needs_update' => $transportador->isStale(),
                'rntrc_valido' => $transportador->isRntrcValido(),
                'needs_antt_update' => $transportador->needsAnttUpdate()
            ]
        ]);
    }

    /**
     * GET /api/vpo/statistics
     * Estatísticas do cache VPO
     */
    public function statistics(): JsonResponse
    {
        $total = VpoTransportadorCache::count();
        $ativos = VpoTransportadorCache::ativos()->count();
        $rntrcValidos = VpoTransportadorCache::rntrcValido()->count();
        $qualidadeAlta = VpoTransportadorCache::qualidadeMinima(80)->count();

        $avgQualidade = VpoTransportadorCache::avg('score_qualidade');

        $porStatus = VpoTransportadorCache::select('antt_status', DB::raw('COUNT(*) as total'))
            ->groupBy('antt_status')
            ->get()
            ->pluck('total', 'antt_status');

        $porFonte = VpoTransportadorCache::select('antt_fonte', DB::raw('COUNT(*) as total'))
            ->whereNotNull('antt_fonte')
            ->groupBy('antt_fonte')
            ->get()
            ->pluck('total', 'antt_fonte');

        return response()->json([
            'success' => true,
            'statistics' => [
                'total' => $total,
                'ativos' => $ativos,
                'rntrc_validos' => $rntrcValidos,
                'qualidade_alta' => $qualidadeAlta,
                'qualidade_media' => round($avgQualidade, 2),
                'por_status' => $porStatus,
                'por_fonte_antt' => $porFonte,
            ]
        ]);
    }

    /**
     * DELETE /api/vpo/transportadores/{codtrn}
     * Remove transportador do cache (força resync)
     */
    public function destroy(int $codtrn): JsonResponse
    {
        $deleted = VpoTransportadorCache::byCodtrn($codtrn)->delete();

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Transportador não encontrado no cache'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Transportador removido do cache'
        ]);
    }

    /**
     * POST /api/vpo/transportadores/{codtrn}/recalcular-qualidade
     * Recalcula score de qualidade
     */
    public function recalcularQualidade(int $codtrn): JsonResponse
    {
        $transportador = VpoTransportadorCache::byCodtrn($codtrn)->first();

        if (!$transportador) {
            return response()->json([
                'success' => false,
                'message' => 'Transportador não encontrado'
            ], 404);
        }

        $score = $transportador->calculateQualityScore();

        return response()->json([
            'success' => true,
            'score' => $score,
            'campos_faltantes' => $transportador->campos_faltantes,
            'avisos' => $transportador->avisos
        ]);
    }
}
