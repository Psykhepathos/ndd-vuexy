<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProgressService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

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
            'status_ativo' => 'nullable|in:true,false,1,0'  // Accept both boolean and string representations
        ]);

        $page = isset($validated['page']) ? (int) $validated['page'] : 1;
        $perPage = isset($validated['per_page']) ? (int) $validated['per_page'] : 10;
        $search = $validated['search'] ?? '';
        $codigo = isset($validated['codigo']) ? (int) $validated['codigo'] : null;
        $nome = $validated['nome'] ?? '';
        $tipo = $validated['tipo'] ?? 'todos';
        $natureza = $validated['natureza'] ?? '';
        // Convert string/numeric status to consistent format for ProgressService
        $ativo = isset($validated['status_ativo']) ? $validated['status_ativo'] : null;

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

        // LGPD Art. 46 - Log de acesso a dados de transportadores
        Log::info('Listagem de transportes acessada', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'filters' => $filters,
            'timestamp' => now()->toIso8601String()
        ]);

        $result = $this->progressService->getTransportesPaginated($filters);

        if (!$result['success']) {
            $errorId = uniqid('err_');

            Log::error('Falha ao listar transportes', [
                'error_id' => $errorId,
                'error_message' => $result['error'] ?? 'Erro desconhecido',
                'filters' => $filters,
                'ip' => $request->ip(),
                'timestamp' => now()->toIso8601String()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao processar solicitação. ID: ' . $errorId,
                'error_id' => $errorId,
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
    public function show(Request $request, $id): JsonResponse
    {
        // CRITICAL: Validate and sanitize ID to prevent SQL injection
        if (!is_numeric($id) || $id < 1 || $id > 999999999) {
            Log::warning('Tentativa de acesso com ID inválido', [
                'id' => $id,
                'ip' => $request->ip(),
                'timestamp' => now()->toIso8601String()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'ID inválido',
                'data' => null
            ], 422);
        }

        $id = (int) $id;  // Force integer casting

        // LGPD Art. 46 - Log de acesso a dados específicos de transportador
        Log::info('Detalhes de transportador acessados', [
            'transporte_id' => $id,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toIso8601String()
        ]);

        $result = $this->progressService->getTransporteById($id);

        if (!$result['success']) {
            $errorId = uniqid('err_');

            Log::error('Falha ao buscar transportador', [
                'error_id' => $errorId,
                'transporte_id' => $id,
                'error_message' => $result['error'] ?? 'Transporte não encontrado',
                'ip' => $request->ip(),
                'timestamp' => now()->toIso8601String()
            ]);

            return response()->json([
                'success' => false,
                'message' => $result['error'] ? 'Erro ao processar solicitação. ID: ' . $errorId : 'Transporte não encontrado',
                'error_id' => $result['error'] ? $errorId : null,
                'data' => null
            ], $result['error'] ? 500 : 404);
        }

        // Buscar motoristas (para empresas sempre, para autônomos se houver dados na trnmot)
        $transporte = $result['data'];
        $motoristasResult = $this->progressService->getMotoristasPorTransportador($id);
        if (!$motoristasResult['success']) {
            Log::warning('Falha ao carregar motoristas do transportador', [
                'transporte_id' => $id,
                'error' => $motoristasResult['error'] ?? 'Erro desconhecido',
                'ip' => $request->ip(),
                'timestamp' => now()->toIso8601String()
            ]);
        }
        $transporte['motoristas'] = $motoristasResult['success'] ? $motoristasResult['data'] : [];

        // Buscar veículos
        $veiculosResult = $this->progressService->getVeiculosPorTransportador($id);
        if (!$veiculosResult['success']) {
            Log::warning('Falha ao carregar veículos do transportador', [
                'transporte_id' => $id,
                'error' => $veiculosResult['error'] ?? 'Erro desconhecido',
                'ip' => $request->ip(),
                'timestamp' => now()->toIso8601String()
            ]);
        }
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
    public function testConnection(Request $request): JsonResponse
    {
        Log::info('Tentativa de teste de conexão Progress', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toIso8601String()
        ]);

        $result = $this->progressService->testConnection();

        if (!$result['success']) {
            $errorId = uniqid('err_');

            Log::error('Falha no teste de conexão Progress', [
                'error_id' => $errorId,
                'error' => $result['error'] ?? 'Erro desconhecido',
                'ip' => $request->ip(),
                'timestamp' => now()->toIso8601String()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Falha na conexão. ID: ' . $errorId,
                'error_id' => $errorId
            ], 500);
        }

        Log::info('Teste de conexão Progress bem-sucedido', [
            'ip' => $request->ip(),
            'timestamp' => now()->toIso8601String()
        ]);

        return response()->json($result, 200);
    }

    /**
     * Obtém estatísticas dos transportadores
     * Otimizado para usar uma única query agregada
     */
    public function statistics(Request $request): JsonResponse
    {
        Log::info('Estatísticas de transportes acessadas', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toIso8601String()
        ]);

        try {
            // Single aggregated query using CASE statements (Progress SQL syntax - single line)
            $sql = "SELECT COUNT(*) as total, SUM(CASE WHEN flgautonomo = 1 THEN 1 ELSE 0 END) as autonomos, SUM(CASE WHEN flgautonomo = 0 THEN 1 ELSE 0 END) as empresas, SUM(CASE WHEN flgati = 1 THEN 1 ELSE 0 END) as ativos, SUM(CASE WHEN flgati = 0 THEN 1 ELSE 0 END) as inativos, SUM(CASE WHEN numpla IS NOT NULL AND numpla <> '' THEN 1 ELSE 0 END) as com_placa, SUM(CASE WHEN numtel IS NOT NULL AND numtel <> '' THEN 1 ELSE 0 END) as com_telefone FROM PUB.transporte";

            $result = $this->progressService->executeCustomQuery($sql);

            if (!$result['success'] || empty($result['data']['results'])) {
                throw new \RuntimeException($result['error'] ?? 'Nenhum dado retornado');
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
            $errorId = uniqid('err_');

            Log::error('Falha ao obter estatísticas', [
                'error_id' => $errorId,
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
                'timestamp' => now()->toIso8601String()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao processar solicitação. ID: ' . $errorId,
                'error_id' => $errorId,
                'data' => null
            ], 500);
        }
    }

    /**
     * Obtém o schema da tabela transporte
     * TODO: Considerar adicionar autenticação (auth:sanctum) para este endpoint
     */
    public function schema(Request $request): JsonResponse
    {
        // LGPD Art. 46 - Log de acesso a metadados (crítico para segurança)
        Log::info('Schema da tabela transporte acessado', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toIso8601String()
        ]);

        $result = $this->progressService->getTransporteTableSchema();

        if (!$result['success']) {
            $errorId = uniqid('err_');

            Log::error('Falha ao obter schema', [
                'error_id' => $errorId,
                'error' => $result['error'] ?? 'Erro desconhecido',
                'ip' => $request->ip(),
                'timestamp' => now()->toIso8601String()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao processar solicitação. ID: ' . $errorId,
                'error_id' => $errorId,
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
        if (!$user || $user->role !== 'admin') {
            Log::warning('Tentativa de acesso não autorizado a query customizada', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toIso8601String()
            ]);

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

        $sql = $validated['sql'];
        $sqlUpper = strtoupper($sql);

        // Security: Block dangerous SQL keywords using word boundaries
        $dangerousKeywords = ['DROP', 'DELETE', 'TRUNCATE', 'ALTER', 'CREATE',
                              'INSERT', 'UPDATE', 'EXEC', 'EXECUTE'];

        foreach ($dangerousKeywords as $keyword) {
            // Use word boundaries to avoid false positives (e.g., "codRotCreateSP" is allowed)
            if (preg_match('/\b' . $keyword . '\b/i', $sqlUpper)) {
                Log::warning('Query customizada bloqueada - palavra-chave perigosa', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'sql' => $sql,
                    'keyword' => $keyword,
                    'ip' => $request->ip(),
                    'timestamp' => now()->toIso8601String()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => "Palavra-chave proibida detectada: {$keyword}",
                    'data' => null
                ], 422);
            }
        }

        // Security: Block SQL comments
        if (preg_match('/(--|\/\*|\*\/)/', $sql)) {
            Log::warning('Query customizada bloqueada - comentários SQL', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'sql' => $sql,
                'ip' => $request->ip(),
                'timestamp' => now()->toIso8601String()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Comentários SQL não são permitidos',
                'data' => null
            ], 422);
        }

        // Security: Block UNION and other dangerous patterns
        $dangerousPatterns = ['UNION', 'INTO OUTFILE', 'INTO DUMPFILE', 'LOAD_FILE'];
        foreach ($dangerousPatterns as $pattern) {
            if (preg_match('/\b' . preg_quote($pattern, '/') . '\b/i', $sqlUpper)) {
                Log::warning('Query customizada bloqueada - pattern perigoso', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'sql' => $sql,
                    'pattern' => $pattern,
                    'ip' => $request->ip(),
                    'timestamp' => now()->toIso8601String()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => "Pattern não permitido detectado: {$pattern}",
                    'data' => null
                ], 422);
            }
        }

        // LGPD Art. 46 - CRÍTICO: Log completo de query customizada
        Log::info('Query customizada executada por admin', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'sql' => $sql,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toIso8601String()
        ]);

        $result = $this->progressService->executeCustomQuery($sql);

        if (!$result['success']) {
            $errorId = uniqid('err_');

            Log::error('Query customizada falhou', [
                'error_id' => $errorId,
                'user_id' => $user->id,
                'user_email' => $user->email,
                'sql' => $sql,
                'error' => $result['error'] ?? 'Erro desconhecido',
                'ip' => $request->ip(),
                'timestamp' => now()->toIso8601String()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao executar consulta. ID: ' . $errorId,
                'error_id' => $errorId,
                'data' => null
            ], 400);
        }

        // Log de sucesso com quantidade de resultados
        Log::info('Query customizada concluída com sucesso', [
            'user_id' => $user->id,
            'result_count' => count($result['data']['results'] ?? []),
            'timestamp' => now()->toIso8601String()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Consulta executada com sucesso',
            'data' => $result['data']
        ]);
    }
}