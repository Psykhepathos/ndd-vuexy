<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProgressService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class SemPararRotaController extends Controller
{
    protected ProgressService $progressService;

    public function __construct(ProgressService $progressService)
    {
        $this->progressService = $progressService;
    }

    /**
     * Lista rotas SemParar com paginação e filtros
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->validate([
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:1|max:100',
                'search' => 'string|max:255',
                'codigo' => 'string|max:50',
                'descricao' => 'string|max:255',
                'flg_cd' => 'string|in:true,false,1,0',
                'flg_retorno' => 'string|in:true,false',
                'tempo_minimo' => 'integer|min:0',
                'tempo_maximo' => 'integer|min:0'
            ]);

            Log::info('API: Listando rotas SemParar', ['filters' => $filters]);

            $result = $this->progressService->getSemPararRotas($filters);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['error'],
                    'data' => []
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Rotas SemParar obtidas com sucesso',
                'data' => $result['data']['results'],
                'pagination' => $result['data']['pagination'] ?? null
            ]);

        } catch (\Exception $e) {
            Log::error('Erro na API ao listar rotas SemParar', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno do servidor',
                'data' => []
            ], 500);
        }
    }

    /**
     * Busca uma rota SemParar específica
     */
    public function show($id): JsonResponse
    {
        try {
            Log::info('API: Buscando rota SemParar específica', ['id' => $id]);

            $result = $this->progressService->getSemPararRota($id);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['error'],
                    'data' => null
                ], $result['error'] === 'Rota não encontrada' ? 404 : 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Rota SemParar obtida com sucesso',
                'data' => $result['data']
            ]);

        } catch (\Exception $e) {
            Log::error('Erro na API ao buscar rota SemParar específica', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno do servidor',
                'data' => null
            ], 500);
        }
    }

    /**
     * Cria uma nova rota SemParar
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'nome' => 'required|string|max:60',
                'tempo_viagem' => 'required|integer|min:1|max:15',
                'flg_cd' => 'boolean',
                'flg_retorno' => 'boolean',
                'municipios' => 'array',
                'municipios.*.cod_est' => 'required_with:municipios|integer',
                'municipios.*.cod_mun' => 'required_with:municipios|integer',
                'municipios.*.des_est' => 'required_with:municipios|string|max:60',
                'municipios.*.des_mun' => 'required_with:municipios|string|max:60',
                'municipios.*.cdibge' => 'required_with:municipios|integer'
            ]);

            Log::info('API: Criando nova rota SemParar', ['data' => $data]);

            $result = $this->progressService->createSemPararRota($data);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['error'],
                    'data' => null
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['data']
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Erro na API ao criar rota SemParar', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno do servidor',
                'data' => null
            ], 500);
        }
    }

    /**
     * Atualiza uma rota SemParar existente
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $data = $request->validate([
                'nome' => 'required|string|max:60',
                'tempo_viagem' => 'required|integer|min:1|max:15',
                'flg_cd' => 'boolean',
                'flg_retorno' => 'boolean',
                'municipios' => 'array',
                'municipios.*.cod_est' => 'required_with:municipios|integer',
                'municipios.*.cod_mun' => 'required_with:municipios|integer',
                'municipios.*.des_est' => 'required_with:municipios|string|max:60',
                'municipios.*.des_mun' => 'required_with:municipios|string|max:60',
                'municipios.*.cdibge' => 'required_with:municipios|integer'
            ]);

            Log::info('API: Atualizando rota SemParar', ['id' => $id, 'data' => $data]);

            $result = $this->progressService->updateSemPararRota($id, $data);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['error'],
                    'data' => null
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => null
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Erro na API ao atualizar rota SemParar', [
                'id' => $id,
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno do servidor',
                'data' => null
            ], 500);
        }
    }

    /**
     * Remove uma rota SemParar
     */
    public function destroy($id): JsonResponse
    {
        try {
            Log::info('API: Removendo rota SemParar', ['id' => $id]);

            $result = $this->progressService->deleteSemPararRota($id);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['error'],
                    'data' => null
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => null
            ]);

        } catch (\Exception $e) {
            Log::error('Erro na API ao remover rota SemParar', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno do servidor',
                'data' => null
            ], 500);
        }
    }

    /**
     * Busca municípios para autocomplete
     */
    public function municipios(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'search' => 'nullable|string|max:255',
                'estado_id' => 'nullable|integer'
            ]);

            $search = $request->get('search', '');
            $estadoId = $request->get('estado_id');

            Log::info('API: Buscando municípios para autocomplete', [
                'search' => $search,
                'estado_id' => $estadoId
            ]);

            $result = $this->progressService->getMunicipiosForAutocomplete($search, $estadoId);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['error'],
                    'data' => []
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Municípios obtidos com sucesso',
                'data' => $result['data']
            ]);

        } catch (\Exception $e) {
            Log::error('Erro na API ao buscar municípios', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno do servidor',
                'data' => []
            ], 500);
        }
    }

    /**
     * Busca estados para autocomplete
     */
    public function estados(): JsonResponse
    {
        try {
            Log::info('API: Buscando estados para autocomplete');

            $result = $this->progressService->getEstadosForAutocomplete();

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['error'],
                    'data' => []
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Estados obtidos com sucesso',
                'data' => $result['data']
            ]);

        } catch (\Exception $e) {
            Log::error('Erro na API ao buscar estados', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno do servidor',
                'data' => []
            ], 500);
        }
    }

    /**
     * Busca uma rota com seus municípios
     */
    public function showWithMunicipios($id): JsonResponse
    {
        try {
            Log::info('API: Buscando rota SemParar com municípios', ['id' => $id]);

            $result = $this->progressService->getSemPararRotaWithMunicipios($id);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['error'],
                    'data' => null
                ], $result['error'] === 'Rota não encontrada' ? 404 : 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Rota com municípios obtida com sucesso',
                'data' => $result['data']
            ]);

        } catch (\Exception $e) {
            Log::error('Erro na API ao buscar rota com municípios', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno do servidor',
                'data' => null
            ], 500);
        }
    }

    /**
     * Atualiza a sequência de municípios de uma rota
     */
    public function updateMunicipios(Request $request, $id): JsonResponse
    {
        try {
            $data = $request->validate([
                'municipios' => 'required|array',
                'municipios.*.cod_est' => 'required|integer',
                'municipios.*.cod_mun' => 'required|integer',
                'municipios.*.des_est' => 'required|string|max:60',
                'municipios.*.des_mun' => 'required|string|max:60',
                'municipios.*.cdibge' => 'required|integer',
                'municipios.*.sequencia' => 'required|integer'
            ]);

            Log::info('API: Atualizando municípios da rota', ['id' => $id, 'municipios' => count($data['municipios'])]);

            $result = $this->progressService->updateSemPararRotaMunicipios($id, $data['municipios']);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['error']
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message']
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Erro na API ao atualizar municípios da rota', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno do servidor'
            ], 500);
        }
    }
}