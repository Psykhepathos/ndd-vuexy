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
            // CORREÇÃO BUG #37: Escapar wildcards LIKE para prevenir injection
            if ($request->filled('search')) {
                $search = str_replace(['%', '_'], ['\\%', '\\_'], $request->search);
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

            // CORREÇÃO BUG #38: SQL injection na ordenação - validar campos permitidos
            $sortBy = $request->input('sort_by', 'rodovia');
            $sortOrder = $request->input('sort_order', 'asc');

            // CORREÇÃO BUG #38: Whitelist de campos permitidos para ordenação
            $allowedSortFields = ['rodovia', 'praca', 'municipio', 'uf', 'km', 'sentido', 'situacao', 'concessionaria', 'created_at', 'updated_at'];
            if (!in_array($sortBy, $allowedSortFields, true)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Campo de ordenação inválido'
                ], 400);
            }

            // CORREÇÃO BUG #38: Validar direção de ordenação
            $sortOrder = strtolower($sortOrder);
            if (!in_array($sortOrder, ['asc', 'desc'], true)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Direção de ordenação inválida'
                ], 400);
            }

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
    public function show(int $id, Request $request): JsonResponse
    {
        try {
            $praca = PracaPedagio::findOrFail($id);

            // CORREÇÃO BUG #39: LGPD logging de acesso a detalhes de praça de pedágio
            Log::info('Praça de pedágio acessada', [
                'praca_id' => $id,
                'user_id' => auth()->id() ?? null,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toIso8601String()
            ]);

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
        // CORREÇÃO BUG #40: Apenas administradores podem importar praças
        if (!$request->user() || $request->user()->role !== 'admin') {
            Log::warning('Tentativa de importar praças sem permissão', [
                'user_id' => $request->user()?->id,
                'user_email' => $request->user()?->email,
                'ip' => $request->ip(),
                'timestamp' => now()->toIso8601String()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Acesso negado. Apenas administradores podem importar praças.'
            ], 403);
        }

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
    public function limpar(Request $request): JsonResponse
    {
        // CORREÇÃO BUG #41: Apenas administradores podem limpar praças
        if (!$request->user() || $request->user()->role !== 'admin') {
            Log::warning('Tentativa de limpar praças sem permissão', [
                'user_id' => $request->user()?->id,
                'user_email' => $request->user()?->email,
                'ip' => $request->ip(),
                'timestamp' => now()->toIso8601String()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Acesso negado. Apenas administradores podem limpar praças.'
            ], 403);
        }

        // CORREÇÃO BUG #72: Validar confirmation code
        $validated = $request->validate([
            'confirmation_code' => 'required|string'
        ]);

        try {
            // CORREÇÃO BUG #73: Passar contexto de usuário para logging LGPD no service
            $userContext = [
                'user_id' => $request->user()->id,
                'user_email' => $request->user()->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ];

            $success = $this->importService->limparTudo($validated['confirmation_code'], $userContext);

            if ($success) {
                // Logging redundante removido - agora está completo no service layer
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
            Log::error('Erro ao limpar praças', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
                'ip' => $request->ip(),
                'timestamp' => now()->toIso8601String()
            ]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
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

            // CORREÇÃO BUG #42: LGPD logging de consulta de localização geográfica
            Log::info('Consulta de praças por proximidade', [
                'lat' => round($request->lat, 2),  // Truncar precisão para privacidade
                'lon' => round($request->lon, 2),
                'raio_km' => $raioKm,
                'total_results' => $pracas->count(),
                'user_id' => auth()->id() ?? null,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toIso8601String()
            ]);

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
