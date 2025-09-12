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
            'nome' => 'string|max:255',
            'tipo' => 'string|in:autonomo,empresa,todos',
            'natureza' => 'string|in:T,A'
        ]);

        $page = (int) $request->get('page', 1);
        $perPage = (int) $request->get('per_page', 10);
        $search = $request->get('search', '');
        $codigo = $request->get('codigo', '');
        $nome = $request->get('nome', '');
        $tipo = $request->get('tipo', 'todos');
        $natureza = $request->get('natureza', '');
        $ativo = $request->get('status_ativo');

        // Preparar filtros para o service
        $filters = [
            'page' => $page,
            'per_page' => $perPage,
            'search' => $search,
            'codigo' => $codigo,
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
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = [
                'total' => 0,
                'autonomos' => 0,
                'empresas' => 0,
                'ativos' => 0,
                'inativos' => 0,
                'natureza_T' => 0,
                'natureza_A' => 0,
                'natureza_F' => 0,
                'com_placa' => 0,
                'com_telefone' => 0
            ];
            
            // Query simplificada para Progress
            $sql = "SELECT COUNT(*) as total FROM PUB.transporte";
            
            $result = $this->progressService->executeCustomQuery($sql);
            
            if ($result['success'] && !empty($result['data']['results'])) {
                $stats['total'] = (int)$result['data']['results'][0]['total'];
                
                // Queries adicionais para obter estatísticas específicas  
                $queries = [
                    'autonomos' => "SELECT COUNT(*) as total FROM PUB.transporte WHERE flgautonomo = 1",
                    'empresas' => "SELECT COUNT(*) as total FROM PUB.transporte WHERE flgautonomo = 0", 
                    'ativos' => "SELECT COUNT(*) as total FROM PUB.transporte WHERE flgati = 1",
                    'inativos' => "SELECT COUNT(*) as total FROM PUB.transporte WHERE flgati = 0"
                ];
                
                foreach ($queries as $key => $query) {
                    $result = $this->progressService->executeCustomQuery($query);
                    if ($result['success'] && !empty($result['data']['results'])) {
                        $stats[$key] = (int)$result['data']['results'][0]['total'];
                    }
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