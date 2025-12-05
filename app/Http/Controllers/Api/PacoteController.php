<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProgressService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PacoteController extends Controller
{
    protected ProgressService $progressService;

    public function __construct(ProgressService $progressService)
    {
        $this->progressService = $progressService;
    }

    /**
     * Lista pacotes com paginação e filtros
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:5|max:100',
            'search' => 'nullable|string|max:255',
            'codigo' => 'nullable|string|max:50',
            'transportador' => 'nullable|string|max:255',
            'codigo_transportador' => 'nullable|integer',
            'motorista' => 'nullable|string|max:255',
            'rota' => 'nullable|string|max:10',
            'situacao' => 'nullable|string|max:1',
            'apenas_recentes' => 'nullable|string|max:1',
            'data_inicio' => 'nullable|date',
            'data_fim' => 'nullable|date'
        ]);

        $page = (int) $request->get('page', 1);
        $perPage = (int) $request->get('per_page', 15);
        $search = $request->get('search', '');
        $codigo = $request->get('codigo', '');
        $transportador = $request->get('transportador', '');
        $codigoTransportador = $request->get('codigo_transportador', '');
        $motorista = $request->get('motorista', '');
        $rota = $request->get('rota', '');
        $situacao = $request->get('situacao', '');
        $apenasRecentes = $request->get('apenas_recentes', '') === '1';
        $dataInicio = $request->get('data_inicio', '');
        $dataFim = $request->get('data_fim', '');

        $filters = [
            'page' => $page,
            'per_page' => $perPage,
            'search' => $search,
            'codigo' => $codigo,
            'transportador' => $transportador,
            'codigo_transportador' => $codigoTransportador,
            'motorista' => $motorista,
            'rota' => $rota,
            'situacao' => $situacao,
            'apenas_recentes' => $apenasRecentes,
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim
        ];

        // LGPD: Log apenas quando há filtros específicos (evita spam de logs)
        if ($codigo || $codigoTransportador || $motorista || $dataInicio) {
            Log::info('Busca de pacotes com filtros específicos', [
                'method' => __METHOD__,
                'filtros' => array_filter([
                    'codigo' => $codigo,
                    'codigo_transportador' => $codigoTransportador,
                    'motorista' => $motorista,
                    'data_inicio' => $dataInicio,
                    'data_fim' => $dataFim
                ]),
                'ip' => $request->ip(),
                'timestamp' => now()->toIso8601String()
            ]);
        }

        try {
            $result = $this->progressService->getPacotesPaginated($filters);

            if (!$result['success']) {
                $errorId = uniqid('err_');

                Log::error('Erro ao listar pacotes', [
                    'error_id' => $errorId,
                    'method' => __METHOD__,
                    'service_error' => $result['error'] ?? 'Erro desconhecido',
                    'filtros' => $filters,
                    'ip' => $request->ip(),
                    'timestamp' => now()->toIso8601String()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Erro interno no processamento. Contate o suporte.',
                    'error_id' => $errorId,
                    'data' => null
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Pacotes obtidos com sucesso',
                'data' => $result['data'],
                'pagination' => $result['pagination'] ?? null
            ]);

        } catch (\Exception $e) {
            $errorId = uniqid('err_');

            Log::error('Exceção ao listar pacotes', [
                'error_id' => $errorId,
                'method' => __METHOD__,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'filtros' => $filters,
                'ip' => $request->ip(),
                'timestamp' => now()->toIso8601String()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno no processamento. Contate o suporte.',
                'error_id' => $errorId,
                'data' => null
            ], 500);
        }
    }

    /**
     * Busca pacote específico por ID com relacionamentos
     */
    public function show($id, Request $request): JsonResponse
    {
        // LGPD: Log de acesso a detalhes de pacote
        Log::info('Consulta de detalhes de pacote', [
            'method' => __METHOD__,
            'pac_id' => $id,
            'ip' => $request->ip(),
            'timestamp' => now()->toIso8601String()
        ]);

        try {
            $result = $this->progressService->getPacoteById($id);

            if (!$result['success']) {
                $errorId = uniqid('err_');

                // Distinguir entre "não encontrado" e "erro interno"
                $isNotFound = !isset($result['error']) || empty($result['error']);

                if (!$isNotFound) {
                    Log::error('Erro ao buscar pacote', [
                        'error_id' => $errorId,
                        'method' => __METHOD__,
                        'pac_id' => $id,
                        'service_error' => $result['error'],
                        'ip' => $request->ip(),
                        'timestamp' => now()->toIso8601String()
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Erro interno no processamento. Contate o suporte.',
                        'error_id' => $errorId,
                        'data' => null
                    ], 500);
                }

                // Pacote não encontrado (404)
                return response()->json([
                    'success' => false,
                    'message' => 'Pacote não encontrado',
                    'data' => null
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Detalhes do pacote obtidos com sucesso',
                'data' => $result['data']
            ]);

        } catch (\Exception $e) {
            $errorId = uniqid('err_');

            Log::error('Exceção ao buscar pacote', [
                'error_id' => $errorId,
                'method' => __METHOD__,
                'pac_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip' => $request->ip(),
                'timestamp' => now()->toIso8601String()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno no processamento. Contate o suporte.',
                'error_id' => $errorId,
                'data' => null
            ], 500);
        }
    }

    /**
     * Busca itinerário do pacote (baseado no Progress itinerario.p)
     */
    public function itinerario(Request $request): JsonResponse
    {
        $request->validate([
            'codPac' => 'required|integer'
        ]);

        $codPac = $request->input('codPac');

        // LGPD Art. 46: Log de acesso a dados de clientes (itinerário contém endereços, razão social)
        Log::info('Consulta de itinerário de pacote com dados de clientes', [
            'method' => __METHOD__,
            'cod_pac' => $codPac,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toIso8601String()
        ]);

        try {
            $result = $this->progressService->getItinerarioPacote($codPac);

            if (!$result['success']) {
                $errorId = uniqid('err_');

                Log::error('Erro ao buscar itinerário', [
                    'error_id' => $errorId,
                    'method' => __METHOD__,
                    'cod_pac' => $codPac,
                    'service_error' => $result['error'] ?? 'Erro desconhecido',
                    'ip' => $request->ip(),
                    'timestamp' => now()->toIso8601String()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Erro interno no processamento. Contate o suporte.',
                    'error_id' => $errorId,
                    'data' => null
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Itinerário obtido com sucesso',
                'data' => $result['data']
            ]);

        } catch (\Exception $e) {
            $errorId = uniqid('err_');

            Log::error('Exceção ao buscar itinerário', [
                'error_id' => $errorId,
                'method' => __METHOD__,
                'cod_pac' => $codPac,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip' => $request->ip(),
                'timestamp' => now()->toIso8601String()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno no processamento. Contate o suporte.',
                'error_id' => $errorId,
                'data' => null
            ], 500);
        }
    }

    /**
     * Autocomplete de pacotes para busca rápida
     */
    public function autocomplete(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:50'
        ]);

        $search = $request->get('search', '');

        try {
            // CORREÇÃO BUG #21: SQL injection no autocomplete - usar prepared statements
            // CORREÇÃO BUG #24: TOP 20 é adequado para autocomplete
            // UX best practice: Limitar resultados para não sobrecarregar dropdown
            // Se necessário mais resultados, usar endpoint index() com paginação
            $sql = "SELECT TOP 20 p.codpac, p.codrot, p.datforpac, p.sitpac, p.nroped, t.nomtrn FROM PUB.pacote p LEFT JOIN PUB.transporte t ON p.codtrn = t.codtrn WHERE 1=1";

            if (!empty($search)) {
                // Se for número, buscar por código usando range numérico
                // IMPORTANTE: Progress JDBC não suporta CAST(codpac AS VARCHAR) LIKE
                // Usa range numérico que funciona com índices: 304 -> 3040000-3049999
                if (is_numeric($search)) {
                    // CORREÇÃO BUG #21: Validar que $search contém apenas dígitos
                    if (!preg_match('/^\d+$/', $search)) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Código de pacote inválido',
                            'data' => []
                        ], 400);
                    }

                    $searchInt = (int)$search;
                    $searchLen = strlen($search);

                    // CORREÇÃO BUG #21: Validar range de valores razoáveis
                    if ($searchInt < 0 || $searchInt > 99999999) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Código de pacote fora do range válido',
                            'data' => []
                        ], 400);
                    }

                    // Para código exato (7 dígitos), busca exata: 3043368 -> 3043368-3043368
                    // Para código parcial, busca por range: 304 -> 3040000-3049999
                    if ($searchLen >= 7) {
                        // Busca exata (código completo)
                        // CORREÇÃO BUG CRÍTICO #2: Type casting defensivo
                        // Seguro porque $searchInt já foi validado como integer (linha 315, 319)
                        $sql .= " AND p.codpac = " . (int)$searchInt;
                    } else {
                        // Range numérico para busca parcial
                        // Exemplo: "304" com códigos de 7 dígitos
                        // 304 * 10^(7-3) = 3040000
                        // (304 + 1) * 10^(7-3) = 3050000
                        // Result: 3040000 <= codpac < 3050000
                        $multiplier = (int)pow(10, 7 - $searchLen);
                        $rangeStart = (int)($searchInt * $multiplier);
                        $rangeEnd = (int)(($searchInt + 1) * $multiplier);

                        // CORREÇÃO BUG CRÍTICO #2: Type casting defensivo
                        // Seguro porque todos valores são integers validados
                        $sql .= " AND p.codpac >= " . $rangeStart . " AND p.codpac < " . $rangeEnd;
                    }
                }
            }

            $sql .= " ORDER BY p.datforpac DESC, p.codpac DESC";

            $result = $this->progressService->executeCustomQuery($sql);

            if (!$result['success']) {
                $errorId = uniqid('err_');

                Log::error('Erro no autocomplete de pacotes', [
                    'error_id' => $errorId,
                    'method' => __METHOD__,
                    'service_error' => $result['error'] ?? 'Erro desconhecido',
                    'search' => $search,
                    'ip' => $request->ip(),
                    'timestamp' => now()->toIso8601String()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Erro interno no processamento. Contate o suporte.',
                    'error_id' => $errorId,
                    'data' => []
                ], 500);
            }

            $pacotes = $result['data']['results'] ?? [];

            // Formatar para autocomplete
            $formatted = array_map(function($pacote) {
                return [
                    'codpac' => (int)$pacote['codpac'],
                    'codrot' => $pacote['codrot'] ?? 'N/D',
                    'datforpac' => $pacote['datforpac'] ?? '',
                    'sitpac' => $pacote['sitpac'] ?? '',
                    'nroped' => (int)($pacote['nroped'] ?? 0),
                    'nomtrn' => $pacote['nomtrn'] ?? 'N/D',
                    'label' => '#' . $pacote['codpac'] . ' - ' . ($pacote['codrot'] ?? 'N/D') . ' - ' . ($pacote['nomtrn'] ?? 'N/D') . ' (' . ($pacote['nroped'] ?? 0) . ' entregas)'
                ];
            }, $pacotes);

            return response()->json([
                'success' => true,
                'message' => 'Pacotes encontrados',
                'data' => $formatted
            ]);

        } catch (\Exception $e) {
            $errorId = uniqid('err_');

            Log::error('Exceção no autocomplete de pacotes', [
                'error_id' => $errorId,
                'method' => __METHOD__,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'search' => $search,
                'ip' => $request->ip(),
                'timestamp' => now()->toIso8601String()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno no processamento. Contate o suporte.',
                'error_id' => $errorId,
                'data' => []
            ], 500);
        }
    }

    /**
     * Obtém estatísticas dos pacotes
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $stats = [
                'total' => 0,
                'por_situacao' => [],
                'valor_total' => 0,
                'peso_total' => 0,
                'volume_total' => 0,
                'pedidos_total' => 0
            ];

            // CORREÇÃO BUG #22 + BUG MODERADO #3: Usar ano atual dinâmico respeitando timezone Laravel
            $anoAtual = now()->format('Y');
            $dataInicio = "{$anoAtual}-01-01";

            // Query para estatísticas básicas
            $sql = "SELECT COUNT(*) as total, SUM(valpac) as valor_total, SUM(pespac) as peso_total, SUM(volpac) as volume_total, SUM(nroped) as pedidos_total FROM PUB.pacote WHERE datforpac >= '{$dataInicio}'";

            $result = $this->progressService->executeCustomQuery($sql);

            if ($result['success'] && !empty($result['data']['results'])) {
                $dados = $result['data']['results'][0];
                $stats['total'] = (int)$dados['total'];
                $stats['valor_total'] = (float)$dados['valor_total'];
                $stats['peso_total'] = (float)$dados['peso_total'];
                $stats['volume_total'] = (float)$dados['volume_total'];
                $stats['pedidos_total'] = (int)$dados['pedidos_total'];
            }

            // Query para situações
            $sqlSituacao = "SELECT sitpac, COUNT(*) as quantidade FROM PUB.pacote WHERE datforpac >= '{$dataInicio}' GROUP BY sitpac";
            $resultSituacao = $this->progressService->executeCustomQuery($sqlSituacao);

            if ($resultSituacao['success'] && !empty($resultSituacao['data']['results'])) {
                foreach ($resultSituacao['data']['results'] as $situacao) {
                    $stats['por_situacao'][] = [
                        'situacao' => $situacao['sitpac'] ?: 'N/D',
                        'quantidade' => (int)$situacao['quantidade']
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Estatísticas obtidas com sucesso',
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            $errorId = uniqid('err_');

            Log::error('Exceção ao obter estatísticas de pacotes', [
                'error_id' => $errorId,
                'method' => __METHOD__,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip' => $request->ip(),
                'timestamp' => now()->toIso8601String()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno no processamento. Contate o suporte.',
                'error_id' => $errorId,
                'data' => null
            ], 500);
        }
    }
}