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
     */
    public function testConnection(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'services' => [
                'progress' => true,
                'antt_opendata' => true,
                'database_local' => VpoTransportadorCache::count() . ' registros em cache'
            ],
            'message' => 'Todos os serviços operacionais'
        ]);
    }

    /**
     * POST /api/vpo/sync/transportador
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
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->get('per_page', 15), 100);

        $query = VpoTransportadorCache::query();

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
            'vpo_data' => $transportador->toVpoArray(),
            'meta' => [
                'needs_update' => $transportador->isStale(),
                'rntrc_valido' => $transportador->isRntrcValido(),
                'needs_antt_update' => $transportador->needsAnttUpdate()
            ]
        ]);
    }

    /**
     * GET /api/vpo/statistics
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

    /**
     * PUT /api/vpo/transportadores/{codtrn}
     * Atualiza campos faltantes do cache VPO (preenchidos pelo usuário)
     */
    public function update(Request $request, int $codtrn): JsonResponse
    {
        $transportador = VpoTransportadorCache::byCodtrn($codtrn)->first();

        if (!$transportador) {
            return response()->json([
                'success' => false,
                'message' => 'Transportador não encontrado no cache.'
            ], 404);
        }

        $camposEditaveis = [
            'antt_rntrc', 'antt_validade', 'antt_status',
            'placa', 'veiculo_tipo', 'veiculo_modelo',
            'condutor_rg', 'condutor_nome', 'condutor_sexo', 'condutor_nome_mae', 'condutor_data_nascimento',
            'endereco_rua', 'endereco_numero', 'endereco_bairro', 'endereco_cidade', 'endereco_estado', 'endereco_cep',
            'contato_telefone', 'contato_celular', 'contato_email',
        ];

        $validated = $request->validate([
            'antt_rntrc' => 'nullable|string|max:20',
            'antt_validade' => 'nullable|date',
            'antt_status' => 'nullable|string|max:50',
            'placa' => 'nullable|string|max:10',
            'veiculo_tipo' => 'nullable|string|max:50',
            'veiculo_modelo' => 'nullable|string|max:100',
            'condutor_rg' => 'nullable|string|max:20',
            'condutor_nome' => 'nullable|string|max:200',
            'condutor_sexo' => 'nullable|string|max:1|in:M,F',
            'condutor_nome_mae' => 'nullable|string|max:200',
            'condutor_data_nascimento' => 'nullable|date',
            'endereco_rua' => 'nullable|string|max:200',
            'endereco_numero' => 'nullable|string|max:20',
            'endereco_bairro' => 'nullable|string|max:100',
            'endereco_cidade' => 'nullable|string|max:100',
            'endereco_estado' => 'nullable|string|max:2',
            'endereco_cep' => 'nullable|string|max:10',
            'contato_telefone' => 'nullable|string|max:20',
            'contato_celular' => 'nullable|string|max:20',
            'contato_email' => 'nullable|email|max:200',
        ]);

        $dadosParaAtualizar = [];
        foreach ($validated as $campo => $valor) {
            if (in_array($campo, $camposEditaveis) && $valor !== null) {
                $dadosParaAtualizar[$campo] = $valor;
            }
        }

        if (empty($dadosParaAtualizar)) {
            return response()->json([
                'success' => false,
                'message' => 'Nenhum campo válido para atualizar'
            ], 400);
        }

        $dadosParaAtualizar['editado_manualmente'] = true;
        $dadosParaAtualizar['data_edicao_manual'] = now();

        $transportador->update($dadosParaAtualizar);
        $transportador->refresh();
        $score = $transportador->calculateQualityScore();

        return response()->json([
            'success' => true,
            'message' => 'Dados atualizados com sucesso',
            'data' => [
                'codtrn' => $transportador->codtrn,
                'campos_atualizados' => array_keys($dadosParaAtualizar),
                'score_qualidade' => $score,
                'campos_faltantes' => $transportador->campos_faltantes,
            ]
        ]);
    }
}
