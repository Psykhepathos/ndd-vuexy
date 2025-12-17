<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VeiculoSemPararCache;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Controller para gerenciamento do cache de veículos SemParar
 *
 * Endpoints:
 * GET    /api/veiculos-cache              - Listar veículos em cache
 * GET    /api/veiculos-cache/{placa}      - Buscar veículo por placa
 * POST   /api/veiculos-cache              - Criar/Atualizar veículo no cache
 * PUT    /api/veiculos-cache/{id}         - Atualizar veículo
 * DELETE /api/veiculos-cache/{id}         - Remover do cache
 * GET    /api/veiculos-cache/transportador/{codtrn} - Veículos por transportador
 */
class VeiculoCacheController extends Controller
{
    /**
     * Lista veículos em cache com filtros
     *
     * GET /api/veiculos-cache
     *
     * Query params:
     * - page: Página (default: 1)
     * - per_page: Itens por página (default: 15, max: 100)
     * - search: Busca por placa, descrição ou proprietário
     * - status: Filtrar por status (ATIVO, INATIVO, etc)
     * - codtrn: Filtrar por transportador
     * - tipo_veiculo: Filtrar por tipo
     * - dados_reais: Filtrar apenas dados reais (true/false)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = min($request->input('per_page', 15), 100);

            $query = VeiculoSemPararCache::query();

            // Filtro por busca
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('placa', 'like', "%{$search}%")
                      ->orWhere('descricao', 'like', "%{$search}%")
                      ->orWhere('proprietario', 'like', "%{$search}%");
                });
            }

            // Filtro por status
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            // Filtro por transportador
            if ($request->filled('codtrn')) {
                $query->where('codtrn', $request->input('codtrn'));
            }

            // Filtro por tipo de veículo
            if ($request->filled('tipo_veiculo')) {
                $query->where('tipo_veiculo', $request->input('tipo_veiculo'));
            }

            // Filtro por dados reais
            if ($request->has('dados_reais')) {
                $query->where('dados_semparar_reais', $request->boolean('dados_reais'));
            }

            // Ordenação
            $sortBy = $request->input('sort_by', 'updated_at');
            $sortDir = $request->input('sort_dir', 'desc');
            $query->orderBy($sortBy, $sortDir);

            $veiculos = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $veiculos->items(),
                'meta' => [
                    'current_page' => $veiculos->currentPage(),
                    'last_page' => $veiculos->lastPage(),
                    'per_page' => $veiculos->perPage(),
                    'total' => $veiculos->total(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao listar veículos cache', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erro ao listar veículos',
            ], 500);
        }
    }

    /**
     * Busca veículo por placa
     *
     * GET /api/veiculos-cache/{placa}
     */
    public function show(string $placa): JsonResponse
    {
        try {
            $veiculo = VeiculoSemPararCache::findByPlaca($placa);

            if (!$veiculo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Veículo não encontrado no cache',
                    'placa' => strtoupper($placa),
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $veiculo->toFrontendArray(),
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar veículo', ['placa' => $placa, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar veículo',
            ], 500);
        }
    }

    /**
     * Cria ou atualiza veículo no cache
     *
     * POST /api/veiculos-cache
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'placa' => 'required|string|min:7|max:10',
            'descricao' => 'nullable|string|max:100',
            'eixos' => 'nullable|integer|min:2|max:10',
            'proprietario' => 'nullable|string|max:150',
            'tag' => 'nullable|string|max:50',
            'status' => 'nullable|string|in:ATIVO,INATIVO,PENDENTE,BLOQUEADO',
            'tipo_veiculo' => 'nullable|string|max:50',
            'modelo' => 'nullable|string|max:100',
            'marca' => 'nullable|string|max:50',
            'ano_fabricacao' => 'nullable|integer|min:1900|max:2100',
            'renavam' => 'nullable|string|max:20',
            'chassi' => 'nullable|string|max:30',
            'codtrn' => 'nullable|integer',
            'codmot' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $placa = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $request->placa));
            $veiculo = VeiculoSemPararCache::findByPlaca($placa);

            $data = $validator->validated();
            $data['placa'] = $placa;

            if ($veiculo) {
                $data['usuario_atualizacao_id'] = auth()->id();
                $data['editado_manualmente'] = true;
                $veiculo->update($data);
                $message = 'Veículo atualizado com sucesso';
            } else {
                $data['usuario_criacao_id'] = auth()->id();
                $data['editado_manualmente'] = true;
                $veiculo = VeiculoSemPararCache::create($data);
                $message = 'Veículo cadastrado com sucesso';
            }

            Log::info('Veículo salvo no cache', [
                'placa' => $placa,
                'id' => $veiculo->id,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $veiculo->toFrontendArray(),
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao salvar veículo', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erro ao salvar veículo',
            ], 500);
        }
    }

    /**
     * Atualiza veículo existente
     *
     * PUT /api/veiculos-cache/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'descricao' => 'nullable|string|max:100',
            'eixos' => 'nullable|integer|min:2|max:10',
            'proprietario' => 'nullable|string|max:150',
            'tag' => 'nullable|string|max:50',
            'status' => 'nullable|string|in:ATIVO,INATIVO,PENDENTE,BLOQUEADO',
            'tipo_veiculo' => 'nullable|string|max:50',
            'modelo' => 'nullable|string|max:100',
            'marca' => 'nullable|string|max:50',
            'ano_fabricacao' => 'nullable|integer|min:1900|max:2100',
            'renavam' => 'nullable|string|max:20',
            'chassi' => 'nullable|string|max:30',
            'codtrn' => 'nullable|integer',
            'codmot' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $veiculo = VeiculoSemPararCache::find($id);

            if (!$veiculo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Veículo não encontrado',
                ], 404);
            }

            $data = $validator->validated();
            $data['usuario_atualizacao_id'] = auth()->id();
            $data['editado_manualmente'] = true;

            $veiculo->update($data);

            Log::info('Veículo atualizado', [
                'id' => $id,
                'placa' => $veiculo->placa,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Veículo atualizado com sucesso',
                'data' => $veiculo->fresh()->toFrontendArray(),
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao atualizar veículo', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar veículo',
            ], 500);
        }
    }

    /**
     * Remove veículo do cache
     *
     * DELETE /api/veiculos-cache/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $veiculo = VeiculoSemPararCache::find($id);

            if (!$veiculo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Veículo não encontrado',
                ], 404);
            }

            $placa = $veiculo->placa;
            $veiculo->delete();

            Log::info('Veículo removido do cache', [
                'id' => $id,
                'placa' => $placa,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Veículo removido do cache',
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao remover veículo', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erro ao remover veículo',
            ], 500);
        }
    }

    /**
     * Lista veículos de um transportador
     *
     * GET /api/veiculos-cache/transportador/{codtrn}
     */
    public function byTransportador(int $codtrn): JsonResponse
    {
        try {
            $veiculos = VeiculoSemPararCache::where('codtrn', $codtrn)
                ->orderBy('ultimo_uso', 'desc')
                ->get()
                ->map(fn ($v) => $v->toFrontendArray());

            return response()->json([
                'success' => true,
                'data' => $veiculos,
                'total' => $veiculos->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar veículos do transportador', [
                'codtrn' => $codtrn,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar veículos',
            ], 500);
        }
    }

    /**
     * Revalida veículo no SemParar
     *
     * POST /api/veiculos-cache/{id}/revalidar
     */
    public function revalidar(int $id): JsonResponse
    {
        try {
            $veiculo = VeiculoSemPararCache::find($id);

            if (!$veiculo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Veículo não encontrado',
                ], 404);
            }

            // Chamar API SemParar para revalidar
            $progressService = app(\App\Services\ProgressService::class);
            $allowSoapQueries = config('semparar.allow_soap_queries', false);

            $result = $progressService->validateVehicleStatusSemParar(
                $veiculo->placa,
                !$allowSoapQueries
            );

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Falha na revalidação: ' . ($result['error'] ?? 'Erro desconhecido'),
                ], 400);
            }

            // Atualizar cache com novos dados (sem sobrescrever edições manuais)
            if (!$veiculo->editado_manualmente) {
                $veiculo->update([
                    'descricao' => $result['data']['descricao'] ?? $veiculo->descricao,
                    'eixos' => $result['data']['eixos'] ?? $veiculo->eixos,
                    'proprietario' => $result['data']['proprietario'] ?? $veiculo->proprietario,
                    'tag' => $result['data']['tag'] ?? $veiculo->tag,
                    'status' => $result['data']['status'] ?? $veiculo->status,
                    'ultima_validacao_semparar' => now(),
                    'dados_semparar_reais' => $allowSoapQueries,
                ]);
            } else {
                // Apenas atualizar timestamp
                $veiculo->update([
                    'ultima_validacao_semparar' => now(),
                    'dados_semparar_reais' => $allowSoapQueries,
                ]);
            }

            Log::info('Veículo revalidado', [
                'id' => $id,
                'placa' => $veiculo->placa,
                'dados_reais' => $allowSoapQueries,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Veículo revalidado com sucesso',
                'data' => $veiculo->fresh()->toFrontendArray(),
                'dados_semparar_reais' => $allowSoapQueries,
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao revalidar veículo', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erro ao revalidar veículo',
            ], 500);
        }
    }

    /**
     * Vincula veículo a transportador
     *
     * POST /api/veiculos-cache/{id}/vincular
     */
    public function vincular(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'codtrn' => 'required|integer',
            'codmot' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $veiculo = VeiculoSemPararCache::find($id);

            if (!$veiculo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Veículo não encontrado',
                ], 404);
            }

            $veiculo->update([
                'codtrn' => $request->codtrn,
                'codmot' => $request->codmot,
                'usuario_atualizacao_id' => auth()->id(),
            ]);

            Log::info('Veículo vinculado', [
                'id' => $id,
                'placa' => $veiculo->placa,
                'codtrn' => $request->codtrn,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Veículo vinculado com sucesso',
                'data' => $veiculo->fresh()->toFrontendArray(),
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao vincular veículo', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erro ao vincular veículo',
            ], 500);
        }
    }
}
