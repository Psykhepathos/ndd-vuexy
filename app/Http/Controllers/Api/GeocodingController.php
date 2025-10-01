<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GeocodingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class GeocodingController extends Controller
{
    protected GeocodingService $geocodingService;

    public function __construct(GeocodingService $geocodingService)
    {
        $this->geocodingService = $geocodingService;
    }

    /**
     * Busca coordenadas de um município pelo código IBGE
     */
    public function getCoordenadasByIbge(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'codigo_ibge' => 'required|string|size:7',
                'nome_municipio' => 'required|string|max:100',
                'uf' => 'required|string|size:2'
            ]);

            Log::info('API: Buscando coordenadas por IBGE', $validated);

            $coordenadas = $this->geocodingService->getCoordenadasByIbge(
                $validated['codigo_ibge'],
                $validated['nome_municipio'],
                $validated['uf']
            );

            if (!$coordenadas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Não foi possível obter coordenadas para este município',
                    'data' => null
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Coordenadas obtidas com sucesso',
                'data' => $coordenadas
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Erro na API ao buscar coordenadas', [
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
     * Busca coordenadas em lote para múltiplos municípios
     */
    public function getCoordenadasLote(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'municipios' => 'required|array|min:1',
                'municipios.*.cdibge' => 'required|string',
                'municipios.*.desmun' => 'required|string',
                'municipios.*.desest' => 'required|string'
            ]);

            Log::info('API: Buscando coordenadas em lote', [
                'total_municipios' => count($validated['municipios'])
            ]);

            $resultado = $this->geocodingService->getCoordenadasLote($validated['municipios']);

            return response()->json([
                'success' => true,
                'message' => 'Coordenadas obtidas com sucesso',
                'data' => $resultado
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Erro na API ao buscar coordenadas em lote', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno do servidor',
                'data' => []
            ], 500);
        }
    }
}
