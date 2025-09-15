<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RouteCache;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class RouteCacheController extends Controller
{
    /**
     * Buscar rota no cache
     */
    public function findRoute(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'waypoints' => 'required|array|min:2',
            'waypoints.*' => 'required|array|size:2',
            'waypoints.*.*' => 'required|numeric'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Waypoints inválidos',
                'details' => $validator->errors()
            ], 400);
        }

        try {
            $waypoints = $request->input('waypoints');
            $cachedRoute = RouteCache::findCachedRoute($waypoints);

            if ($cachedRoute) {
                Log::info('Cache hit for route', [
                    'waypoints_count' => count($waypoints),
                    'cache_id' => $cachedRoute->id
                ]);

                return response()->json([
                    'success' => true,
                    'cache_hit' => true,
                    'route' => [
                        'coordinates' => $cachedRoute->getRouteCoordinatesDecoded(),
                        'total_distance' => $cachedRoute->total_distance,
                        'waypoints_count' => $cachedRoute->waypoints_count,
                        'source' => $cachedRoute->source,
                        'cached_at' => $cachedRoute->created_at->toISOString()
                    ]
                ]);
            }

            Log::info('Cache miss for route', [
                'waypoints_count' => count($waypoints)
            ]);

            return response()->json([
                'success' => true,
                'cache_hit' => false,
                'message' => 'Rota não encontrada no cache'
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao buscar rota no cache', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erro interno ao buscar cache'
            ], 500);
        }
    }

    /**
     * Salvar rota no cache
     */
    public function saveRoute(Request $request): JsonResponse
    {
        set_time_limit(300); // 5 minutos para cache de rotas grandes
        
        $validator = Validator::make($request->all(), [
            'waypoints' => 'required|array|min:2',
            'waypoints.*' => 'required|array|size:2',
            'waypoints.*.*' => 'required|numeric',
            'route_coordinates' => 'required|array|min:2',
            'route_coordinates.*' => 'required|array|size:2',
            'route_coordinates.*.*' => 'required|numeric',
            'total_distance' => 'required|numeric|min:0',
            'source' => 'sometimes|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Dados inválidos',
                'details' => $validator->errors()
            ], 400);
        }

        try {
            $waypoints = $request->input('waypoints');
            $routeCoordinates = $request->input('route_coordinates');
            $totalDistance = $request->input('total_distance');
            $source = $request->input('source', 'google_maps');

            $cachedRoute = RouteCache::cacheRoute(
                $waypoints,
                $routeCoordinates,
                $totalDistance,
                $source
            );

            Log::info('Rota salva no cache', [
                'cache_id' => $cachedRoute->id,
                'waypoints_count' => count($waypoints),
                'coordinates_count' => count($routeCoordinates),
                'distance' => $totalDistance,
                'source' => $source
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Rota salva no cache com sucesso',
                'cache_id' => $cachedRoute->id,
                'expires_at' => $cachedRoute->expires_at?->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao salvar rota no cache', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erro interno ao salvar cache'
            ], 500);
        }
    }

    /**
     * Obter estatísticas do cache
     */
    public function getStats(): JsonResponse
    {
        try {
            $stats = RouteCache::getStats();

            return response()->json([
                'success' => true,
                'stats' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao obter estatísticas do cache', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erro ao obter estatísticas'
            ], 500);
        }
    }

    /**
     * Limpar cache expirado
     */
    public function clearExpired(): JsonResponse
    {
        try {
            $deletedCount = RouteCache::clearExpired();

            Log::info('Cache expirado limpo', [
                'deleted_routes' => $deletedCount
            ]);

            return response()->json([
                'success' => true,
                'message' => "Cache limpo: {$deletedCount} rotas expiradas removidas",
                'deleted_count' => $deletedCount
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao limpar cache expirado', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erro ao limpar cache'
            ], 500);
        }
    }
}
