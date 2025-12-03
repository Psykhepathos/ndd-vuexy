<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

class ProgressController extends Controller
{
    protected ProgressService $progressService;

    public function __construct(ProgressService $progressService)
    {
        $this->progressService = $progressService;
    }

    /**
     * @OA\Get(
     *     path="/api/progress/test-connection",
     *     summary="Testa conexão ODBC com Progress",
     *     tags={"Progress ODBC"},
     *     @OA\Response(
     *         response=200,
     *         description="Conexão testada com sucesso",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Conexão Progress estabelecida com sucesso"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="host", type="string", example="192.168.80.113"),
     *                 @OA\Property(property="database", type="string", example="tambasa"),
     *                 @OA\Property(property="timestamp", type="string", example="2024-12-01 15:30:00")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erro na conexão",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="Falha na conexão Progress")
     *         )
     *     )
     * )
     */
    public function testConnection(): JsonResponse
    {
        try {
            $result = $this->progressService->testConnection();
            
            return response()->json($result, $result['success'] ? 200 : 500);
            
        } catch (\Exception $e) {
            Log::error('Erro no teste de conexão Progress', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Erro interno no teste de conexão'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/progress/transportes",
     *     summary="Lista dados da tabela transporte",
     *     tags={"Progress ODBC"},
     *     @OA\Parameter(
     *         name="codigo",
     *         in="query",
     *         description="Filtrar por código do transporte",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="data_inicio",
     *         in="query",
     *         description="Data de início para filtro",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="data_fim",
     *         in="query",
     *         description="Data fim para filtro",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filtrar por status",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Limite de registros (padrão: 100)",
     *         @OA\Schema(type="integer", default=100)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Dados da tabela transporte",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Dados da tabela transporte obtidos com sucesso"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="transportes", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="total", type="integer", example=50),
     *                 @OA\Property(property="filters_applied", type="object")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erro na consulta",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function getTransportes(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'codigo' => 'nullable|string|max:50',
                'data_inicio' => 'nullable|date',
                'data_fim' => 'nullable|date|after_or_equal:data_inicio',
                'status' => 'nullable|string|max:20',
                'limit' => 'nullable|integer|min:1|max:100'  // CORREÇÃO #6: Reduzir limite para prevenir timeout
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parâmetros inválidos',
                    'errors' => $validator->errors()
                ], 400);
            }

            $filters = $request->only(['codigo', 'data_inicio', 'data_fim', 'status', 'limit']);
            $result = $this->progressService->getTransportes($filters);
            
            return response()->json($result, $result['success'] ? 200 : 500);
            
        } catch (\Exception $e) {
            Log::error('Erro na busca de transportes', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Erro interno na busca de transportes'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/progress/transportes/{id}",
     *     summary="Busca transporte específico por ID/código",
     *     tags={"Progress ODBC"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID ou código do transporte",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Transporte encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Transporte encontrado com sucesso"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Transporte não encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Transporte não encontrado")
     *         )
     *     )
     * )
     */
    public function getTransporteById(string $id): JsonResponse
    {
        try {
            $result = $this->progressService->getTransporteById($id);
            
            $statusCode = $result['success'] ? 200 : 404;
            return response()->json($result, $statusCode);
            
        } catch (\Exception $e) {
            Log::error('Erro na busca de transporte por ID', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Erro interno na busca de transporte'
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/progress/query",
     *     summary="Executa consulta SQL personalizada (apenas SELECT)",
     *     tags={"Progress ODBC"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="sql", 
     *                 type="string", 
     *                 example="SELECT * FROM transporte WHERE codigo LIKE ? LIMIT 10"
     *             ),
     *             @OA\Property(
     *                 property="bindings", 
     *                 type="array", 
     *                 @OA\Items(type="string"),
     *                 example={"%123%"}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Consulta executada com sucesso",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Consulta executada com sucesso"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="results", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="sql", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Consulta inválida",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function executeCustomQuery(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'sql' => 'required|string',
                'bindings' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parâmetros inválidos',
                    'errors' => $validator->errors()
                ], 400);
            }

            $sql = $request->input('sql');

            // CORREÇÃO #3: Validar segurança da query ANTES de executar
            $securityCheck = $this->validateQuerySecurity($sql);
            if (!$securityCheck['valid']) {
                // CORREÇÃO #5: Registrar tentativa rejeitada para auditoria
                Log::warning('Query rejeitada por validação de segurança', [
                    'user_id' => $request->user()->id ?? null,
                    'user_email' => $request->user()->email ?? null,
                    'ip' => $request->ip(),
                    'sql_preview' => substr($sql, 0, 100) . '...',
                    'error' => $securityCheck['error'],
                    'timestamp' => now()->toIso8601String()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Query rejeitada por validação de segurança',
                    'error' => $securityCheck['error']
                ], 403);  // 403 Forbidden
            }

            // CORREÇÃO #5: Registrar auditoria ANTES de executar
            Log::info('Executando query customizada', [
                'user_id' => $request->user()->id ?? null,
                'user_email' => $request->user()->email ?? null,
                'ip' => $request->ip(),
                'sql_preview' => substr($sql, 0, 200) . (strlen($sql) > 200 ? '...' : ''),
                'timestamp' => now()->toIso8601String()
            ]);

            $bindings = $request->input('bindings', []);

            $result = $this->progressService->executeCustomQuery($sql, $bindings);

            // CORREÇÃO #5: Registrar resultado da auditoria
            Log::info('Query executada com sucesso', [
                'user_id' => $request->user()->id ?? null,
                'total_registros' => $result['data']['total'] ?? 0
            ]);

            return response()->json($result, $result['success'] ? 200 : 400);

        } catch (\Exception $e) {
            // CORREÇÃO #5: Incluir user_id e IP em logs de erro
            Log::error('Erro na execução de consulta customizada', [
                'user_id' => $request->user()->id ?? null,
                'user_email' => $request->user()->email ?? null,
                'ip' => $request->ip(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erro interno na execução da consulta'
            ], 500);
        }
    }

    /**
     * CORREÇÃO #3: Valida segurança da query customizada
     *
     * Regras de segurança:
     * - Apenas tabelas whitelisted podem ser acessadas
     * - Não pode usar SELECT * (deve especificar colunas)
     * - Não pode acessar colunas sensíveis (CPF, CNPJ, senhas)
     * - Limite de 100 registros por query
     *
     * @param string $sql SQL query a ser validada
     * @return array ['valid' => bool, 'error' => string|null]
     */
    private function validateQuerySecurity(string $sql): array
    {
        $sql_upper = strtoupper($sql);

        // Regra 1: Whitelist de tabelas permitidas para usuários autenticados
        $allowedTables = [
            'PUB.TRANSPORTE',
            'PUB.PACOTE',
            'PUB.INTROT',       // Rotas
            'PUB.SEMPARATOT',   // Rotas SemParar (apenas leitura de metadados)
            'PUB.MUNICIPIO',
            'PUB.ESTADO'
        ];

        $tablesInQuery = [];
        foreach ($allowedTables as $table) {
            if (str_contains($sql_upper, $table)) {
                $tablesInQuery[] = $table;
            }
        }

        // Se não encontrou nenhuma tabela permitida, verificar se está tentando acessar tabela não permitida
        if (empty($tablesInQuery)) {
            // Detectar se está tentando acessar tabela proibida
            $forbiddenTables = ['TRNMOT', 'USUARIO', 'SPARARVIAGEM', 'FUNCIONARIO', 'CONTAPAGAR'];
            foreach ($forbiddenTables as $forbidden) {
                if (str_contains($sql_upper, $forbidden)) {
                    return [
                        'valid' => false,
                        'error' => 'Acesso negado: Tabela não permitida para usuários. Contate o administrador.'
                    ];
                }
            }

            return [
                'valid' => false,
                'error' => 'Nenhuma tabela permitida encontrada na query. Tabelas permitidas: ' . implode(', ', $allowedTables)
            ];
        }

        // Regra 2: Proibir SELECT * (deve especificar colunas)
        if (preg_match('/SELECT\s+\*/i', $sql)) {
            return [
                'valid' => false,
                'error' => 'SELECT * não é permitido. Especifique as colunas desejadas.'
            ];
        }

        // Regra 3: Detectar acesso a colunas sensíveis (mesmo que whitelisted)
        $sensitiveCols = ['CODCNPJCPF', 'CODCPF', 'SENHA', 'PASSWORD', 'TOKEN'];
        foreach ($sensitiveCols as $col) {
            if (str_contains($sql_upper, $col)) {
                return [
                    'valid' => false,
                    'error' => "Acesso à coluna sensível '{$col}' não é permitido."
                ];
            }
        }

        // Regra 4: Limitar quantidade de registros (máximo 100)
        if (!preg_match('/TOP\s+\d+/i', $sql)) {
            return [
                'valid' => false,
                'error' => 'Query deve incluir TOP N (máximo 100 registros). Exemplo: SELECT TOP 100 ...'
            ];
        }

        // Extrair número do TOP
        preg_match('/TOP\s+(\d+)/i', $sql, $matches);
        $topLimit = (int)($matches[1] ?? 0);
        if ($topLimit > 100) {
            return [
                'valid' => false,
                'error' => 'TOP não pode ser maior que 100. Use paginação para grandes volumes.'
            ];
        }

        return ['valid' => true];
    }
}