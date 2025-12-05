<?php

namespace App\Services\Map;

use App\Services\Map\CacheManager;
use App\Services\Map\Providers\OsrmProvider;
use App\Services\Map\Utils\CoordinateConverter;
use App\Services\Map\Utils\DistanceCalculator;
use App\Services\GeocodingService;
use Illuminate\Support\Facades\Log;

/**
 * MapService - OSRM-only orchestrator for map operations
 *
 * Handles:
 * - Route calculation using OSRM (free, open-source)
 * - Geocoding with cache
 * - Point clustering
 * - Unified API for all map operations
 */
class MapService
{
    private CacheManager $cacheManager;
    private GeocodingService $geocodingService;
    private OsrmProvider $osrmProvider;

    public function __construct()
    {
        $this->cacheManager = new CacheManager();
        $this->geocodingService = app(GeocodingService::class);
        $this->osrmProvider = new OsrmProvider();

        Log::info("MapService initialized (OSRM-only mode)");
    }

    // ============================================================================
    // ROUTE CALCULATION (OSRM-only)
    // ============================================================================

    /**
     * Calculate route between waypoints using OSRM
     *
     * @param array $waypoints Array of [lat, lon] waypoints
     * @param array $options Calculation options:
     * - use_cache: bool (default: true)
     * - fallback_to_straight: bool (default: true)
     * @return array Result with coordinates, distance, duration
     */
    public function calculateRoute(array $waypoints, array $options = []): array
    {
        // Validate waypoints
        if (count($waypoints) < 2) {
            return [
                'success' => false,
                'error' => 'At least 2 waypoints required',
                'provider' => 'osrm'
            ];
        }

        // Default options
        $options = array_merge([
            'use_cache' => true,
            'fallback_to_straight' => true
        ], $options);

        Log::info('Calculating route with OSRM', [
            'waypoints_count' => count($waypoints),
            'use_cache' => $options['use_cache']
        ]);

        // Try cache first
        if ($options['use_cache']) {
            $cached = $this->cacheManager->findCachedRoute($waypoints, 'osrm');

            if ($cached) {
                Log::info('Route found in cache');
                return $cached;
            }
        }

        // Check if need to chunk waypoints (OSRM max 10 waypoints)
        $maxWaypoints = $this->osrmProvider->getMaxWaypoints();

        if (count($waypoints) > $maxWaypoints) {
            Log::info("Route has waypoints, chunking into segments", [
                'waypoint_count' => count($waypoints),
                'max_waypoints' => $maxWaypoints
            ]);
            return $this->calculateMultiSegmentRoute($waypoints, $options);
        }

        // Calculate route with OSRM (single segment)
        $result = $this->osrmProvider->calculateRoute($waypoints);

        // Add bounds if successful
        if ($result['success']) {
            $result['bounds'] = DistanceCalculator::calculateBounds($result['coordinates'], 5);

            // Save to cache
            if ($options['use_cache']) {
                $this->cacheManager->saveRoute(
                    $waypoints,
                    $result['coordinates'],
                    $result['distance_km'],
                    'osrm',
                    $result['duration_seconds'] ?? null
                );
            }
        }

        return $result;
    }

    /**
     * Calculate route with multiple segments (for routes > 10 waypoints)
     *
     * @param array $waypoints All waypoints
     * @param array $options Calculation options
     * @return array Combined result
     */
    private function calculateMultiSegmentRoute(array $waypoints, array $options): array
    {
        $maxWaypoints = $this->osrmProvider->getMaxWaypoints();
        $allCoordinates = [];
        $totalDistance = 0;
        $totalDuration = 0;
        $segments = [];

        // Split waypoints into chunks with overlap
        // Example: [1,2,3,4,5,6,7,8,9,10,11,12] with max 10
        // Chunk 1: [1,2,3,4,5,6,7,8,9,10]
        // Chunk 2: [10,11,12] (overlap last point)
        $chunkSize = $maxWaypoints;
        $numWaypoints = count($waypoints);

        for ($i = 0; $i < $numWaypoints; $i += $chunkSize - 1) {
            $chunk = array_slice($waypoints, $i, $chunkSize);

            // Stop if we only have 1 point left (already included in previous chunk)
            if (count($chunk) < 2) {
                break;
            }

            Log::info("Calculating segment", [
                'segment' => count($segments) + 1,
                'waypoints' => count($chunk),
                'start_index' => $i,
                'end_index' => $i + count($chunk) - 1
            ]);

            // Calculate this segment
            $segmentResult = $this->osrmProvider->calculateRoute($chunk);

            if (!$segmentResult['success']) {
                // If any segment fails, return error
                Log::error("Segment failed", ['segment' => count($segments) + 1]);
                return $segmentResult;
            }

            // Merge coordinates (skip first coordinate of subsequent segments to avoid duplicates)
            if (count($allCoordinates) > 0) {
                $segmentCoordinates = array_slice($segmentResult['coordinates'], 1);
            } else {
                $segmentCoordinates = $segmentResult['coordinates'];
            }

            $allCoordinates = array_merge($allCoordinates, $segmentCoordinates);
            $totalDistance += $segmentResult['distance_km'];
            $totalDuration += $segmentResult['duration_seconds'] ?? 0;

            $segments[] = [
                'waypoints' => count($chunk),
                'distance_km' => $segmentResult['distance_km'],
                'duration_seconds' => $segmentResult['duration_seconds'] ?? 0
            ];
        }

        $result = [
            'success' => true,
            'coordinates' => $allCoordinates,
            'distance_km' => $totalDistance,
            'duration_seconds' => $totalDuration,
            'provider' => 'osrm',
            'cached' => false,
            'segments' => $segments,
            'total_segments' => count($segments)
        ];

        // Add bounds
        $result['bounds'] = DistanceCalculator::calculateBounds($allCoordinates, 5);

        // Save to cache
        if ($options['use_cache']) {
            $this->cacheManager->saveRoute(
                $waypoints,
                $allCoordinates,
                $totalDistance,
                'osrm',
                $totalDuration
            );
        }

        Log::info("Multi-segment route calculated successfully", [
            'total_segments' => count($segments),
            'total_distance_km' => $totalDistance,
            'total_coordinates' => count($allCoordinates)
        ]);

        return $result;
    }

    // ============================================================================
    // GEOCODING
    // ============================================================================

    /**
     * Geocode municipalities by IBGE codes (batch)
     *
     * @param array $municipalities Array of municipality data with cdibge, desmun, desest
     * @param array $options Geocoding options:
     * - use_cache: bool (default: true)
     * - source: 'google' | 'auto' (default: 'auto')
     * @return array Map of cdibge => coordinates
     */
    public function geocodeBatch(array $municipalities, array $options = []): array
    {
        $options = array_merge([
            'use_cache' => true,
            'source' => 'auto'
        ], $options);

        Log::info('Batch geocoding', [
            'count' => count($municipalities),
            'use_cache' => $options['use_cache']
        ]);

        // Use existing GeocodingService
        return $this->geocodingService->getCoordenadasLote($municipalities, $options['use_cache']);
    }

    // ============================================================================
    // CLUSTERING
    // ============================================================================

    /**
     * Cluster points by proximity
     *
     * @param array $points Array of points with lat, lon, type
     * @param array $options Clustering options:
     * - radius: float (km, default: 5)
     * - min_points: int (default: 2)
     * - algorithm: 'proximity' | 'kmeans' (default: 'proximity')
     * - exclude_types: array (types to exclude from clustering)
     * @return array Clusters and ungrouped points
     */
    public function clusterPoints(array $points, array $options = []): array
    {
        $options = array_merge([
            'radius' => 5,
            'min_points' => 2,
            'algorithm' => 'proximity',
            'exclude_types' => []
        ], $options);

        Log::info('Clustering points', [
            'total_points' => count($points),
            'radius_km' => $options['radius'],
            'algorithm' => $options['algorithm']
        ]);

        // Filter out excluded types
        $pointsToCluster = array_filter($points, function($point) use ($options) {
            $type = $point['type'] ?? 'unknown';
            return !in_array($type, $options['exclude_types']);
        });

        $excludedPoints = array_diff_key($points, $pointsToCluster);

        // Simple proximity clustering
        $clusters = $this->proximityCluster($pointsToCluster, $options['radius'], $options['min_points']);

        return [
            'clusters' => $clusters['clusters'],
            'ungrouped' => array_merge($clusters['ungrouped'], array_values($excludedPoints)),
            'stats' => [
                'total_points' => count($points),
                'excluded_points' => count($excludedPoints),
                'clustered_points' => count($pointsToCluster) - count($clusters['ungrouped']),
                'total_clusters' => count($clusters['clusters']),
                'ungrouped_count' => count($clusters['ungrouped']) + count($excludedPoints)
            ]
        ];
    }

    /**
     * Proximity-based clustering algorithm
     */
    private function proximityCluster(array $points, float $radiusKm, int $minPoints): array
    {
        $clusters = [];
        $remaining = array_values($points);
        $clusterId = 1;

        while (count($remaining) > 0) {
            $base = array_shift($remaining);
            $cluster = [
                'id' => "cluster_{$clusterId}",
                'center' => ['lat' => $base['lat'], 'lon' => $base['lon']],
                'points' => [$base],
                'count' => 1,
                'radius' => 0
            ];

            // Find nearby points
            for ($i = count($remaining) - 1; $i >= 0; $i--) {
                $point = $remaining[$i];

                if (DistanceCalculator::isWithinDistance(
                    $cluster['center']['lat'],
                    $cluster['center']['lon'],
                    $point['lat'],
                    $point['lon'],
                    $radiusKm
                )) {
                    $cluster['points'][] = $point;
                    $cluster['count']++;

                    // Recalculate center (average)
                    // CORREÇÃO BUG MODERADO #2: Prevenir division by zero
                    $lats = array_column($cluster['points'], 'lat');
                    $lons = array_column($cluster['points'], 'lon');

                    $countLats = count($lats);
                    if ($countLats > 0) {
                        $cluster['center'] = [
                            'lat' => array_sum($lats) / $countLats,
                            'lon' => array_sum($lons) / $countLats
                        ];
                    }

                    array_splice($remaining, $i, 1);
                }
            }

            // Only create cluster if meets minimum points
            if ($cluster['count'] >= $minPoints) {
                // Generate label
                $municipality = $cluster['points'][0]['desmun'] ?? 'Unknown';
                $state = $cluster['points'][0]['desest'] ?? '';
                $cluster['label'] = "{$cluster['count']} entregas em {$municipality}";
                if ($state) {
                    $cluster['label'] .= " - {$state}";
                }

                $clusters[] = $cluster;
                $clusterId++;
            } else {
                // Return to ungrouped
                $remaining = array_merge($remaining, $cluster['points']);
            }
        }

        return [
            'clusters' => $clusters,
            'ungrouped' => $remaining
        ];
    }

    // ============================================================================
    // CACHE MANAGEMENT
    // ============================================================================

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        return $this->cacheManager->getStatistics();
    }

    /**
     * Clear expired cache entries
     */
    public function clearExpiredCache(): int
    {
        return $this->cacheManager->clearExpired();
    }

    // ============================================================================
    // PROVIDERS INFO
    // ============================================================================

    /**
     * Get list of available providers (OSRM only)
     */
    public function getAvailableProviders(): array
    {
        return [[
            'name' => $this->osrmProvider->getName(),
            'priority' => $this->osrmProvider->getPriority(),
            'max_waypoints' => $this->osrmProvider->getMaxWaypoints(),
            'cost_per_request' => $this->osrmProvider->getCostPerRequest(),
            'available' => $this->osrmProvider->isAvailable()
        ]];
    }
}
