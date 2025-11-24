<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PracaPedagio;
use App\Services\PracaPedagioImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PracaPedagioController extends Controller
{
    protected PracaPedagioImportService $importService;

    public function __construct(PracaPedagioImportService $importService)
    {
        $this->importService = $importService;
    }

    /**
     * Listar praças com filtros e paginação
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = PracaPedagio::query();

            // Filtro por situação
            if ($request->has('situacao')) {
                if ($request->situacao === 'Ativo') {
                    $query->ativas();
                } else {
                    $query->where('situacao', $request->situacao);
                }
            }

            // Filtro por rodovia
            if ($request->filled('rodovia')) {
                $query->porRodovia($request->rodovia);
            }

            // Filtro por UF
            if ($request->filled('uf')) {
                $query->porUf($request->uf);
            }

            // Busca por nome da praça ou município
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('praca', 'LIKE', "%{$search}%")
                      ->orWhere('municipio', 'LIKE', "%{$search}%")
                      ->orWhere('concessionaria', 'LIKE', "%{$search}%");
                });
            }

            // Filtro de proximidade (requer lat, lon, raio_km)
            if ($request->filled(['lat', 'lon'])) {
                $raioKm = $request->input('raio_km', 50);
                $query->proximasDe($request->lat, $request->lon, $raioKm);
            }

            // Ordenação
            $sortBy = $request->input('sort_by', 'rodovia');
            $sortOrder = $request->input('sort_order', 'asc');
            $query->orderBy($sortBy, $sortOrder);

            // Paginação
            $perPage = $request->input('per_page', 15);
            $pracas = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $pracas->items(),
                'pagination' => [
                    'current_page' => $pracas->currentPage(),
                    'total' => $pracas->total(),
                    'per_page' => $pracas->perPage(),
                    'last_page' => $pracas->lastPage()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao listar praças', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'Erro ao listar praças de pedágio'
            ], 500);
        }
    }

    /**
     * Obter praça específica
     */
    public function show(int $id): JsonResponse
    {
        try {
            $praca = PracaPedagio::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $praca
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Praça não encontrada'
            ], 404);
        }
    }

    /**
     * Importar CSV da ANTT
     */
    public function importar(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'file' => 'required|file|mimes:csv,txt|max:10240' // 10MB max
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $file = $request->file('file');
            $filePath = $file->getRealPath();

            Log::info('Iniciando importação de praças', [
                'filename' => $file->getClientOriginalName(),
                'size' => $file->getSize()
            ]);

            $result = $this->importService->importarCSV($filePath);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => "Importação concluída com sucesso!",
                    'data' => [
                        'imported' => $result['imported'],
                        'errors' => count($result['errors']),
                        'duration' => $result['duration'] . 's',
                        'error_details' => $result['errors']
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'],
                    'data' => [
                        'imported' => $result['imported'],
                        'errors' => count($result['errors'])
                    ]
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Erro fatal na importação', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erro ao processar arquivo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obter estatísticas
     */
    public function estatisticas(): JsonResponse
    {
        try {
            $stats = $this->importService->getEstatisticas();

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao obter estatísticas', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'Erro ao obter estatísticas'
            ], 500);
        }
    }

    /**
     * Limpar todas as praças (ADMIN ONLY)
     */
    public function limpar(): JsonResponse
    {
        try {
            $success = $this->importService->limparTudo();

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Todas as praças foram removidas do banco de dados'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro ao limpar praças'
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Erro ao limpar praças', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'Erro ao limpar praças'
            ], 500);
        }
    }

    /**
     * Obter praças próximas a uma coordenada
     */
    public function proximidade(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'lat' => 'required|numeric|between:-90,90',
                'lon' => 'required|numeric|between:-180,180',
                'raio_km' => 'nullable|numeric|min:1|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $raioKm = $request->input('raio_km', 50);
            $pracas = PracaPedagio::ativas()
                ->proximasDe($request->lat, $request->lon, $raioKm)
                ->orderBy('rodovia')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $pracas,
                'meta' => [
                    'lat' => $request->lat,
                    'lon' => $request->lon,
                    'raio_km' => $raioKm,
                    'total' => $pracas->count()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar praças próximas', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'Erro ao buscar praças próximas'
            ], 500);
        }
    }
}
