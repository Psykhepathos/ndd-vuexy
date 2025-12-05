<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Map\MapService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * MapController - Unified map API endpoints
 *
 * Endpoints:
 * - POST /api/map/route - Calculate route
 * - POST /api/map/geocode-batch - Batch geocoding
 * - POST /api/map/cluster-points - Cluster points
 * - GET /api/map/cache-stats - Cache statistics
 */
class MapController extends Controller
{
    private MapService $mapService;

    public function __construct()
    {
        // NOTE: Embora dependency injection via constructor parameter seja preferível
        // (ex: __construct(MapService $mapService)), a instanciação direta é aceitável
        // aqui pois MapService não tem dependências complexas e não requer mocking em testes.
        // Se MapService crescer em complexidade, considerar migrar para DI.
        $this->mapService = new MapService();
    }

    /**
     * Calculate route between waypoints using OSRM
     *
     * POST /api/map/route
     *
     * Body:
     * {
     *   "waypoints": [[lat, lon], [lat, lon], ...],
     *   "options": {
     *     "use_cache": true,
     *     "fallback_to_straight": true
     *   }
     * }
     */
    public function calculateRoute(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'waypoints' => 'required|array|min:2',
            'waypoints.*' => 'required|array|size:2',
            'waypoints.*.0' => 'required|numeric|between:-90,90',
            'waypoints.*.1' => 'required|numeric|between:-180,180',
            'options' => 'sometimes|array',
            'options.use_cache' => 'sometimes|boolean',
            'options.fallback_to_straight' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $waypoints = $request->input('waypoints');
            $options = $request->input('options', []);

            $result = $this->mapService->calculateRoute($waypoints, $options);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'],
                    'provider' => $result['provider']
                ], 400);
            }

            return response()->json([
                'success' => true,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Route calculation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Geocode municipalities in batch
     *
     * POST /api/map/geocode-batch
     *
     * Body:
     * {
     *   "municipalities": [
     *     {"cdibge": "3550308", "desmun": "SÃO PAULO", "desest": "SP"},
     *     ...
     *   ],
     *   "options": {
     *     "use_cache": true,
     *     "source": "google|auto"
     *   }
     * }
     */
    public function geocodeBatch(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            // CORREÇÃO BUG #57: Adicionar max limit para prevenir DoS
            'municipalities' => 'required|array|min:1|max:100',
            'municipalities.*.cdibge' => 'required|string',
            'municipalities.*.desmun' => 'required|string',
            'municipalities.*.desest' => 'required|string|size:2',
            'options' => 'sometimes|array',
            'options.use_cache' => 'sometimes|boolean',
            'options.source' => 'sometimes|string|in:google,auto'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $municipalities = $request->input('municipalities');
            $options = $request->input('options', []);

            $result = $this->mapService->geocodeBatch($municipalities, $options);

            // Calculate stats
            $total = count($municipalities);
            $geocoded = count(array_filter($result, function($coord) {
                return $coord !== null;
            }));
            $cached = count(array_filter($result, function($coord) {
                return isset($coord['cached']) && $coord['cached'];
            }));
            $failed = $total - $geocoded;

            return response()->json([
                'success' => true,
                'data' => $result,
                'stats' => [
                    'total' => $total,
                    'geocoded' => $geocoded,
                    'cached' => $cached,
                    'failed' => $failed
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Geocoding failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cluster points by proximity
     *
     * POST /api/map/cluster-points
     *
     * Body:
     * {
     *   "points": [
     *     {"lat": -23.550, "lon": -46.633, "type": "delivery", "label": "Cliente A"},
     *     ...
     *   ],
     *   "options": {
     *     "radius": 5,
     *     "min_points": 2,
     *     "algorithm": "proximity",
     *     "exclude_types": ["municipality"]
     *   }
     * }
     */
    public function clusterPoints(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            // CORREÇÃO BUG #58: Adicionar max limit para prevenir DoS
            'points' => 'required|array|min:1|max:100',
            'points.*.lat' => 'required|numeric|between:-90,90',
            'points.*.lon' => 'required|numeric|between:-180,180',
            'points.*.type' => 'sometimes|string',
            'points.*.label' => 'sometimes|string',
            'options' => 'sometimes|array',
            'options.radius' => 'sometimes|numeric|min:0.1|max:100',
            'options.min_points' => 'sometimes|integer|min:2|max:100',
            'options.algorithm' => 'sometimes|string|in:proximity,kmeans',
            'options.exclude_types' => 'sometimes|array',
            'options.exclude_types.*' => 'string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $points = $request->input('points');
            $options = $request->input('options', []);

            $result = $this->mapService->clusterPoints($points, $options);

            return response()->json([
                'success' => true,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Clustering failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get cache statistics
     *
     * GET /api/map/cache-stats
     */
    public function cacheStats(): JsonResponse
    {
        try {
            $stats = $this->mapService->getCacheStats();

            // Add provider info
            $stats['providers'] = $this->mapService->getAvailableProviders();

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get cache stats: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear expired cache entries
     *
     * POST /api/map/clear-expired-cache
     */
    public function clearExpiredCache(): JsonResponse
    {
        try {
            $deleted = $this->mapService->clearExpiredCache();

            return response()->json([
                'success' => true,
                'message' => "Cleared {$deleted} expired cache entries",
                'deleted_count' => $deleted
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to clear cache: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available providers info
     *
     * GET /api/map/providers
     */
    public function providers(): JsonResponse
    {
        try {
            $providers = $this->mapService->getAvailableProviders();

            return response()->json([
                'success' => true,
                'data' => $providers
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get providers: ' . $e->getMessage()
            ], 500);
        }
    }
}
