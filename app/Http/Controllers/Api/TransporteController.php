<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProgressService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TransporteController extends Controller
{
    protected ProgressService $progressService;

    public function __construct(ProgressService $progressService)
    {
        $this->progressService = $progressService;
    }

    /**
     * Lista transportes do Progress com paginação
     */
    public function index(Request $request): JsonResponse
    {
        // Validação dos parâmetros de paginação
        $request->validate([
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:5|max:100',
            'search' => 'string|max:255',
            'codigo' => 'string|max:50',
            'nome' => 'string|max:255'
        ]);

        $page = (int) $request->get('page', 1);
        $perPage = (int) $request->get('per_page', 10);
        $search = $request->get('search', '');
        $codigo = $request->get('codigo', '');
        $nome = $request->get('nome', '');

        // Preparar filtros para o service
        $filters = [
            'page' => $page,
            'per_page' => $perPage,
            'search' => $search,
            'codigo' => $codigo,
            'nome' => $nome
        ];

        $result = $this->progressService->getTransportesPaginated($filters);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
                'data' => null
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Transportes obtidos com sucesso',
            'data' => $result['data'],
            'pagination' => $result['pagination'] ?? null
        ]);
    }

    /**
     * Busca transporte específico por ID
     */
    public function show($id): JsonResponse
    {
        $result = $this->progressService->getTransporteById($id);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Transporte não encontrado',
                'data' => null
            ], $result['error'] ? 500 : 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Transporte encontrado',
            'data' => $result['data']
        ]);
    }

    /**
     * Teste de conexão Progress
     */
    public function testConnection(): JsonResponse
    {
        $result = $this->progressService->testConnection();

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    /**
     * Executa consulta SQL customizada (apenas SELECT)
     */
    public function query(Request $request): JsonResponse
    {
        $request->validate([
            'sql' => 'required|string'
        ]);

        $result = $this->progressService->executeCustomQuery($request->input('sql'));

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
                'data' => null
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Consulta executada com sucesso',
            'data' => $result['data']
        ]);
    }
}