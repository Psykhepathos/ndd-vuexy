<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Progress\Transporte;
use App\Services\ProgressEloquentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class EloquentTransporteController extends Controller
{
    protected ProgressEloquentService $eloquentService;

    public function __construct(ProgressEloquentService $eloquentService)
    {
        $this->eloquentService = $eloquentService;
    }

    /**
     * Lista transportes usando Eloquent ORM
     */
    public function index(Request $request): JsonResponse
    {
        // CORREÇÃO BUG #62: Adicionar nullable ao campo search
        // CORREÇÃO BUG #66: Validação consistente - max:50 (limite de performance)
        // Limite de 50 registros por página previne sobrecarga do Progress JDBC
        // e melhora tempo de resposta. Para datasets grandes, usar paginação.
        $request->validate([
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:5|max:50',
            'search' => 'nullable|string|max:255',
            'tipo' => 'string|in:autonomo,empresa,todos',
            'natureza' => 'string|in:T,A',
            'status_ativo' => 'nullable|string|in:true,false'
        ]);

        $filters = [
            'page' => $request->get('page', 1),
            'per_page' => $request->get('per_page', 10),
            'search' => $request->get('search', ''),
            'tipo' => $request->get('tipo', 'todos'),
            'natureza' => $request->get('natureza', ''),
            'ativo' => $request->get('status_ativo')
        ];

        $result = $this->eloquentService->getTransportesPaginated($filters);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
                'data' => null
            ], 500);
        }

        // CORREÇÃO BUG #61: LGPD logging de listagem de transportes (Eloquent)
        Log::info('Transportes listados (Eloquent)', [
            'filters' => $filters,
            'total_results' => $result['pagination']['total'] ?? 0,
            'user_id' => auth()->id() ?? null,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toIso8601String()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Transportes obtidos com sucesso (Eloquent)',
            'data' => $result['data'],
            'pagination' => $result['pagination'] ?? null
        ]);
    }

    /**
     * Busca transporte específico usando Eloquent com relacionamentos
     */
    public function show($id, Request $request): JsonResponse
    {
        $result = $this->eloquentService->getTransporteById($id);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Transporte não encontrado',
                'data' => null
            ], $result['error'] ? 500 : 404);
        }

        // CORREÇÃO BUG #63: LGPD logging de acesso a detalhes de transporte (Eloquent)
        Log::info('Transporte acessado (Eloquent)', [
            'transporte_id' => $id,
            'user_id' => auth()->id() ?? null,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toIso8601String()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Transporte encontrado (Eloquent)',
            'data' => $result['data']
        ]);
    }

    /**
     * Obtém estatísticas usando Eloquent
     */
    public function statistics(): JsonResponse
    {
        $result = $this->eloquentService->getTransportesStatistics();

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    /**
     * Exemplo de uso avançado: Busca transportes com veículos e motoristas
     */
    public function withRelacionamentos(Request $request): JsonResponse
    {
        try {
            // CORREÇÃO BUG #65: Otimizar with() para evitar N+1 query massivo
            // Carregar apenas campos essenciais em vez de SELECT *
            $transportes = Transporte::with([
                    'veiculos:placa,tipo_veiculo,capacidade,codtrn',
                    'motoristas:nome,cpf,codtrn',
                    'ciots:numero,codtrn'
                ])
                ->ativos()
                ->when($request->filled('tipo'), function ($query) use ($request) {
                    return $request->tipo === 'autonomo'
                        ? $query->autonomos()
                        : $query->empresas();
                })
                ->when($request->filled('search'), function ($query) use ($request) {
                    return $query->buscar($request->search);
                })
                ->paginate($request->get('per_page', 10));

            // Formatar dados com accessors
            $data = $transportes->getCollection()->map(function ($transporte) {
                return [
                    'codigo' => $transporte->codtrn,
                    'nome' => $transporte->nomtrn,
                    'tipo' => $transporte->tipo_transportador,
                    'telefone' => $transporte->telefone_formatado,
                    'endereco' => $transporte->endereco_completo,
                    'status' => $transporte->status_ativo,
                    'veiculos_count' => $transporte->veiculos->count(),
                    'motoristas_count' => $transporte->motoristas->count(),
                    'ciots_count' => $transporte->ciots->count(),
                    'veiculos' => $transporte->veiculos->map(function ($veiculo) {
                        return [
                            'placa' => $veiculo->placa_formatada,
                            'tipo' => $veiculo->tipo_veiculo,
                            'capacidade' => $veiculo->capacidade_formatada
                        ];
                    }),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Transportes com relacionamentos obtidos',
                'data' => $data,
                'meta' => [
                    'current_page' => $transportes->currentPage(),
                    'last_page' => $transportes->lastPage(),
                    'per_page' => $transportes->perPage(),
                    'total' => $transportes->total()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar transportes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exemplo de busca avançada com múltiplos filtros
     */
    public function buscaAvancada(Request $request): JsonResponse
    {
        // CORREÇÃO BUG #66: Validação consistente - max:50 (limite de performance)
        $request->validate([
            'nome' => 'string|max:255',
            'codigo' => 'integer',
            'ativo' => 'boolean',
            'tipo' => 'in:autonomo,empresa',
            'natureza' => 'in:T,A,F',
            'com_cd' => 'boolean',
            'com_veiculo' => 'boolean',
            'limite' => 'integer|min:1|max:50'
        ]);

        try {
            $query = Transporte::query();

            // Aplicar filtros usando scopes
            if ($request->filled('nome')) {
                $query->buscar($request->nome);
            }

            if ($request->filled('codigo')) {
                $query->porCodigo($request->codigo);
            }

            if ($request->filled('ativo')) {
                $request->ativo ? $query->ativos() : $query->inativos();
            }

            if ($request->filled('tipo')) {
                $request->tipo === 'autonomo' ? $query->autonomos() : $query->empresas();
            }

            if ($request->filled('natureza')) {
                $query->porNatureza($request->natureza);
            }

            if ($request->filled('com_cd') && $request->com_cd) {
                $query->comCD();
            }

            if ($request->filled('com_veiculo') && $request->com_veiculo) {
                $query->has('veiculos');
            }

            $transportes = $query->limit($request->get('limite', 50))->get();

            return response()->json([
                'success' => true,
                'message' => 'Busca avançada realizada',
                'data' => $transportes,
                'filtros_aplicados' => $request->only([
                    'nome', 'codigo', 'ativo', 'tipo', 'natureza', 'com_cd', 'com_veiculo'
                ]),
                'total_encontrado' => $transportes->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro na busca avançada: ' . $e->getMessage()
            ], 500);
        }
    }
}