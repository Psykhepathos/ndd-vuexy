<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProgressService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class RotaController extends Controller
{
    protected ProgressService $progressService;

    public function __construct(ProgressService $progressService)
    {
        $this->progressService = $progressService;
    }

    /**
     * Lista rotas do Progress para autocomplete
     *
     * Rate Limiting: Configurado em routes/api.php se necessário.
     * Atualmente não é necessário pois endpoint de autocomplete é de baixa prioridade
     * e não expõe dados sensíveis. Se houver abuso futuro, adicionar:
     * ->middleware('throttle:60,1') no route definition.
     */
    public function index(Request $request): JsonResponse
    {
        // CORREÇÃO BUG #35: Adicionar nullable à validação
        // CORREÇÃO BUG #36: Sanitizar busca com regex
        $request->validate([
            'search' => 'nullable|string|max:255|regex:/^[a-zA-Z0-9\s\-]+$/'
        ]);

        $search = $request->get('search', '');

        $result = $this->progressService->getRotas($search);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
                'data' => []
            ], 500);
        }

        // CORREÇÃO BUG #33: LGPD logging de pesquisa de rotas
        Log::info('Rotas pesquisadas', [
            'search' => $search,
            'total_results' => count($result['data'] ?? []),
            'user_id' => auth()->id() ?? null,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toIso8601String()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Rotas obtidas com sucesso',
            'data' => $result['data']
        ]);
    }
}