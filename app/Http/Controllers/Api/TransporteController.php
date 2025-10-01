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
        // Validação RIGOROSA dos parâmetros com proteção contra ataques
        $validated = $request->validate([
            'page' => 'integer|min:1|max:1000',  // Limit max page to prevent memory exhaustion
            'per_page' => 'integer|min:5|max:50',  // Reduced from 100 to 50 for security
            'search' => [
                'nullable',
                'string',
                'max:100',  // Reduced from 255
                'regex:/^[a-zA-Z0-9\s\-._@]+$/'  // Only alphanumeric, spaces, and safe chars
            ],
            'codigo' => 'nullable|integer|min:1|max:999999999',  // Changed to integer with bounds
            'nome' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[a-zA-ZÀ-ÿ\s\-\.]+$/'  // Only letters, spaces, hyphens, dots (no special chars)
            ],
            'tipo' => 'nullable|string|in:autonomo,empresa,todos',
            'natureza' => 'nullable|string|in:T,A',
            'status_ativo' => 'nullable|boolean'  // Added missing validation
        ]);

        $page = isset($validated['page']) ? (int) $validated['page'] : 1;
        $perPage = isset($validated['per_page']) ? (int) $validated['per_page'] : 10;
        $search = $validated['search'] ?? '';
        $codigo = isset($validated['codigo']) ? (int) $validated['codigo'] : null;
        $nome = $validated['nome'] ?? '';
        $tipo = $validated['tipo'] ?? 'todos';
        $natureza = $validated['natureza'] ?? '';
        $ativo = isset($validated['status_ativo']) ? (bool) $validated['status_ativo'] : null;

        // Preparar filtros para o service (já validados)
        $filters = [
            'page' => $page,
            'per_page' => $perPage,
            'search' => $search,
            'codigo' => $codigo,  // Now guaranteed to be integer or null
            'nome' => $nome,
            'tipo' => $tipo,
            'natureza' => $natureza,
            'ativo' => $ativo
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
     * Busca transporte específico por ID com relacionamentos
     */
    public function show($id): JsonResponse
    {
        // CRITICAL: Validate and sanitize ID to prevent SQL injection
        if (!is_numeric($id) || $id < 1 || $id > 999999999) {
            return response()->json([
                'success' => false,
                'message' => 'ID inválido',
                'data' => null
            ], 422);
        }

        $id = (int) $id;  // Force integer casting

        $result = $this->progressService->getTransporteById($id);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Transporte não encontrado',
                'data' => null
            ], $result['error'] ? 500 : 404);
        }

        // Buscar motoristas (para empresas sempre, para autônomos se houver dados na trnmot)
        $transporte = $result['data'];
        $motoristasResult = $this->progressService->getMotoristasPorTransportador($id);
        $transporte['motoristas'] = $motoristasResult['success'] ? $motoristasResult['data'] : [];
        
        // Buscar veículos
        $veiculosResult = $this->progressService->getVeiculosPorTransportador($id);
        $transporte['veiculos'] = $veiculosResult['success'] ? $veiculosResult['data'] : [];

        return response()->json([
            'success' => true,
            'message' => 'Detalhes do transportador obtidos com sucesso',
            'data' => $transporte
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
     * Obtém estatísticas dos transportadores
     * Otimizado para usar uma única query agregada
     */
    public function statistics(): JsonResponse
    {
        try {
            // Single aggregated query using CASE statements (Progress SQL syntax - single line)
            $sql = "SELECT COUNT(*) as total, SUM(CASE WHEN flgautonomo = 1 THEN 1 ELSE 0 END) as autonomos, SUM(CASE WHEN flgautonomo = 0 THEN 1 ELSE 0 END) as empresas, SUM(CASE WHEN flgati = 1 THEN 1 ELSE 0 END) as ativos, SUM(CASE WHEN flgati = 0 THEN 1 ELSE 0 END) as inativos, SUM(CASE WHEN numpla IS NOT NULL AND numpla <> '' THEN 1 ELSE 0 END) as com_placa, SUM(CASE WHEN numtel IS NOT NULL AND numtel <> '' THEN 1 ELSE 0 END) as com_telefone FROM PUB.transporte";

            $result = $this->progressService->executeCustomQuery($sql);

            if (!$result['success'] || empty($result['data']['results'])) {
                throw new \Exception($result['error'] ?? 'Nenhum dado retornado');
            }

            $row = $result['data']['results'][0];

            // Convert to integers, handling NULL values
            $stats = [
                'total' => (int)($row['total'] ?? 0),
                'autonomos' => (int)($row['autonomos'] ?? 0),
                'empresas' => (int)($row['empresas'] ?? 0),
                'ativos' => (int)($row['ativos'] ?? 0),
                'inativos' => (int)($row['inativos'] ?? 0),
                'com_placa' => (int)($row['com_placa'] ?? 0),
                'com_telefone' => (int)($row['com_telefone'] ?? 0)
            ];

            return response()->json([
                'success' => true,
                'message' => 'Estatísticas obtidas com sucesso',
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao obter estatísticas: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Obtém o schema da tabela transporte
     */
    public function schema(): JsonResponse
    {
        $result = $this->progressService->getTransporteTableSchema();

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
                'data' => null
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Schema da tabela transporte obtido com sucesso',
            'data' => $result['data']
        ]);
    }

    /**
     * Executa consulta SQL customizada (apenas SELECT)
     * IMPORTANTE: Apenas administradores podem usar este endpoint
     */
    public function query(Request $request): JsonResponse
    {
        // Verificar se usuário é admin
        $user = $request->user();
        if (!$user || !$user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Acesso negado. Apenas administradores podem executar consultas customizadas.',
                'data' => null
            ], 403);
        }

        $validated = $request->validate([
            'sql' => [
                'required',
                'string',
                'max:5000',
                'regex:/^SELECT\s/i'  // Must start with SELECT (case-insensitive)
            ]
        ]);

        // Additional security: block dangerous keywords even in SELECT
        $sql = $validated['sql'];
        $sqlUpper = strtoupper($sql);
        $dangerousPatterns = [
            'DROP', 'DELETE', 'TRUNCATE', 'ALTER', 'CREATE',
            'INSERT', 'UPDATE', 'EXEC', 'EXECUTE', '--', '/*', '*/',
            'UNION', 'INTO OUTFILE', 'INTO DUMPFILE', 'LOAD_FILE'
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (strpos($sqlUpper, $pattern) !== false) {
                return response()->json([
                    'success' => false,
                    'message' => "Palavra-chave proibida detectada: {$pattern}",
                    'data' => null
                ], 422);
            }
        }

        $result = $this->progressService->executeCustomQuery($sql);

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