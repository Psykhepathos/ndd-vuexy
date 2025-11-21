<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProgressService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

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

        $result = $this->progressService->getPacotesPaginated($filters);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
                'data' => null
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pacotes obtidos com sucesso',
            'data' => $result['data'],
            'pagination' => $result['pagination'] ?? null
        ]);
    }

    /**
     * Busca pacote específico por ID com relacionamentos
     */
    public function show($id): JsonResponse
    {
        $result = $this->progressService->getPacoteById($id);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Pacote não encontrado',
                'data' => null
            ], $result['error'] ? 500 : 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detalhes do pacote obtidos com sucesso',
            'data' => $result['data']
        ]);
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
        
        $result = $this->progressService->getItinerarioPacote($codPac);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Erro ao buscar itinerário',
                'data' => null
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Itinerário obtido com sucesso',
            'data' => $result['data']
        ]);
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
            // Buscar pacotes que contenham o código digitado
            $sql = "SELECT TOP 20 p.codpac, p.codrot, p.datforpac, p.sitpac, p.nroped, t.nomtrn FROM PUB.pacote p LEFT JOIN PUB.transporte t ON p.codtrn = t.codtrn WHERE 1=1";

            if (!empty($search)) {
                // Se for número, buscar por código exato ou range
                if (is_numeric($search)) {
                    $searchInt = (int)$search;
                    $nextInt = $searchInt + 1;

                    // Usar range para simular LIKE em integer
                    // Ex: search=304 -> codpac >= 304 AND codpac < 305
                    $multiplier = pow(10, 7 - strlen($search)); // Ajustar para tamanho do código
                    $rangeStart = $searchInt * $multiplier;
                    $rangeEnd = $nextInt * $multiplier;

                    $sql .= " AND p.codpac >= " . $rangeStart . " AND p.codpac < " . $rangeEnd;
                }
            }

            $sql .= " ORDER BY p.datforpac DESC, p.codpac DESC";

            $result = $this->progressService->executeCustomQuery($sql);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro ao buscar pacotes: ' . ($result['error'] ?? 'Erro desconhecido'),
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
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar pacotes: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Obtém estatísticas dos pacotes
     */
    public function statistics(): JsonResponse
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

            // Query para estatísticas básicas
            $sql = "SELECT COUNT(*) as total, SUM(valpac) as valor_total, SUM(pespac) as peso_total, SUM(volpac) as volume_total, SUM(nroped) as pedidos_total FROM PUB.pacote WHERE datforpac >= '2024-01-01'";

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
            $sqlSituacao = "SELECT sitpac, COUNT(*) as quantidade FROM PUB.pacote WHERE datforpac >= '2024-01-01' GROUP BY sitpac";
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
            return response()->json([
                'success' => false,
                'message' => 'Erro ao obter estatísticas: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
}