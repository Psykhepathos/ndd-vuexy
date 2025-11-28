<?php

namespace App\Services\Map;

use App\Models\RouteCache;
use App\Models\MunicipioCoordenada;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * CacheManager - Unified cache management for map services
 *
 * Handles:
 * - Route caching (30 days TTL)
 * - Geocoding caching (permanent)
 * - Cluster caching (24 hours TTL)
 */
class CacheManager
{
    /**
     * Route cache TTL in days
     */
    private int $routeCacheTtlDays = 30;

    /**
     * Cluster cache TTL in hours
     */
    private int $clusterCacheTtlHours = 24;

    /**
     * Tolerance for waypoint matching in meters
     */
    private float $waypointToleranceMeters = 100;

    // ============================================================================
    // ROUTE CACHE
    // ============================================================================

    /**
     * Find cached route by waypoints
     *
     * @param array $waypoints Array of [lat, lon] coordinates
     * @param string $provider Provider name (optional filter)
     * @return array|null Cached route data or null
     */
    public function findCachedRoute(array $waypoints, ?string $provider = null): ?array
    {
        $cacheKey = $this->generateRouteCacheKey($waypoints, $provider);

        // Try exact match first
        $cached = RouteCache::where('cache_key', $cacheKey)
            ->where('expires_at', '>', now())
            ->first();

        if ($cached) {
            Log::info('Route cache HIT (exact)', ['cache_key' => $cacheKey]);
            return $this->formatRouteCacheEntry($cached, true);
        }

        // Try fuzzy match with tolerance
        $cached = $this->findRouteBySimilarWaypoints($waypoints, $provider);

        if ($cached) {
            Log::info('Route cache HIT (fuzzy)', ['cache_key' => $cacheKey]);
            return $this->formatRouteCacheEntry($cached, true);
        }

        Log::info('Route cache MISS', ['cache_key' => $cacheKey]);
        return null;
    }

    /**
     * Save route to cache
     *
     * @param array $waypoints Waypoints used
     * @param array $coordinates Route coordinates
     * @param float $distanceKm Total distance
     * @param string $provider Provider name
     * @param int|null $durationSeconds Duration in seconds
     * @return bool Success
     */
    public function saveRoute(
        array $waypoints,
        array $coordinates,
        float $distanceKm,
        string $provider,
        ?int $durationSeconds = null
    ): bool {
        try {
            $cacheKey = $this->generateRouteCacheKey($waypoints, $provider);

            RouteCache::updateOrCreate(
                ['cache_key' => $cacheKey],
                [
                    'waypoints' => json_encode($waypoints),
                    'waypoints_count' => count($waypoints),
                    'route_coordinates' => json_encode($coordinates),
                    'total_distance' => $distanceKm,
                    'duration_seconds' => $durationSeconds,
                    'source' => $provider,
                    'expires_at' => now()->addDays($this->routeCacheTtlDays)
                ]
            );

            Log::info('Route cached', [
                'cache_key' => $cacheKey,
                'waypoints_count' => count($waypoints),
                'distance_km' => $distanceKm,
                'provider' => $provider
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to save route to cache', [
                'error' => $e->getMessage(),
                'waypoints_count' => count($waypoints)
            ]);
            return false;
        }
    }

    /**
     * Generate cache key for route
     */
    private function generateRouteCacheKey(array $waypoints, ?string $provider = null): string
    {
        $hash = md5(json_encode($waypoints));
        return $provider ? "route_{$hash}_{$provider}" : "route_{$hash}";
    }

    /**
     * Find route by similar waypoints (fuzzy match)
     */
    private function findRouteBySimilarWaypoints(array $waypoints, ?string $provider = null): ?RouteCache
    {
        $query = RouteCache::where('waypoints_count', count($waypoints))
            ->where('expires_at', '>', now());

        if ($provider) {
            $query->where('source', $provider);
        }

        $candidates = $query->get();

        foreach ($candidates as $candidate) {
            $cachedWaypoints = json_decode($candidate->waypoints, true);

            if ($this->areWaypointsSimilar($waypoints, $cachedWaypoints)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Check if two waypoint sets are similar (within tolerance)
     */
    private function areWaypointsSimilar(array $waypoints1, array $waypoints2): bool
    {
        if (count($waypoints1) !== count($waypoints2)) {
            return false;
        }

        for ($i = 0; $i < count($waypoints1); $i++) {
            $distance = $this->calculateDistance(
                $waypoints1[$i][0], $waypoints1[$i][1],
                $waypoints2[$i][0], $waypoints2[$i][1]
            );

            if ($distance > $this->waypointToleranceMeters) {
                return false;
            }
        }

        return true;
    }

    /**
     * Format cache entry for API response
     */
    private function formatRouteCacheEntry(RouteCache $cached, bool $isCached = false): array
    {
        return [
            'success' => true,
            'coordinates' => json_decode($cached->route_coordinates, true),
            'distance_km' => $cached->total_distance,
            'duration_seconds' => $cached->duration_seconds ?? null,
            'provider' => $cached->source,
            'cached' => $isCached,
            'cache_age_hours' => $cached->created_at->diffInHours(now()),
            'error' => null
        ];
    }

    // ============================================================================
    // GEOCODING CACHE
    // ============================================================================

    /**
     * Find cached coordinates by IBGE code
     *
     * @param string $cdibge IBGE code
     * @return array|null Coordinates [lat, lon] or null
     */
    public function findCachedCoordinates(string $cdibge): ?array
    {
        $cached = MunicipioCoordenada::where('cdibge', $cdibge)->first();

        if ($cached) {
            Log::debug('Geocoding cache HIT', ['cdibge' => $cdibge]);
            return [
                'lat' => $cached->lat,
                'lon' => $cached->lon,
                'fonte' => $cached->fonte,
                'cached' => true
            ];
        }

        Log::debug('Geocoding cache MISS', ['cdibge' => $cdibge]);
        return null;
    }

    /**
     * Save coordinates to cache
     *
     * @param string $cdibge IBGE code
     * @param float $lat Latitude
     * @param float $lon Longitude
     * @param string $desmun Municipality name
     * @param string $desest State abbreviation
     * @param string $fonte Source ('google', 'progress', 'manual')
     * @return bool Success
     */
    public function saveCoordinates(
        string $cdibge,
        float $lat,
        float $lon,
        string $desmun,
        string $desest,
        string $fonte = 'google'
    ): bool {
        try {
            MunicipioCoordenada::updateOrCreate(
                ['cdibge' => $cdibge],
                [
                    'desmun' => $desmun,
                    'desest' => $desest,
                    'lat' => $lat,
                    'lon' => $lon,
                    'fonte' => $fonte
                ]
            );

            Log::debug('Coordinates cached', [
                'cdibge' => $cdibge,
                'desmun' => $desmun,
                'fonte' => $fonte
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to save coordinates to cache', [
                'error' => $e->getMessage(),
                'cdibge' => $cdibge
            ]);
            return false;
        }
    }

    // ============================================================================
    // CACHE STATISTICS
    // ============================================================================

    /**
     * Get cache statistics
     *
     * @return array Statistics data
     */
    public function getStatistics(): array
    {
        try {
            // Route cache stats
            $routeStats = [
                'total_entries' => RouteCache::count(),
                'active_entries' => RouteCache::where('expires_at', '>', now())->count(),
                'expired_entries' => RouteCache::where('expires_at', '<=', now())->count(),
                'size_mb' => $this->calculateTableSize('route_cache'),
                'providers' => DB::table('route_cache')
                    ->select('source', DB::raw('count(*) as count'))
                    ->groupBy('source')
                    ->get()
                    ->pluck('count', 'source')
                    ->toArray(),
                'avg_distance_km' => round(RouteCache::avg('total_distance'), 2)
            ];

            // Geocoding cache stats
            $geocodingStats = [
                'total_entries' => MunicipioCoordenada::count(),
                'size_mb' => $this->calculateTableSize('municipio_coordenadas'),
                'sources' => DB::table('municipio_coordenadas')
                    ->select('fonte', DB::raw('count(*) as count'))
                    ->groupBy('fonte')
                    ->get()
                    ->pluck('count', 'fonte')
                    ->toArray()
            ];

            return [
                'route_cache' => $routeStats,
                'geocoding_cache' => $geocodingStats
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get cache statistics', ['error' => $e->getMessage()]);
            return [
                'route_cache' => [],
                'geocoding_cache' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Clear expired cache entries
     *
     * @return int Number of deleted entries
     */
    public function clearExpired(): int
    {
        $deleted = RouteCache::where('expires_at', '<=', now())->delete();

        Log::info('Cleared expired route cache entries', ['deleted' => $deleted]);

        return $deleted;
    }

    // ============================================================================
    // UTILITIES
    // ============================================================================

    /**
     * Calculate distance between two points (Haversine formula)
     *
     * @param float $lat1 Latitude 1
     * @param float $lon1 Longitude 1
     * @param float $lat2 Latitude 2
     * @param float $lon2 Longitude 2
     * @return float Distance in meters
     */
    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $R = 6371000; // Earth radius in meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $R * $c;
    }

    /**
     * Calculate approximate table size in MB
     */
    private function calculateTableSize(string $tableName): float
    {
        try {
            $path = database_path('database.sqlite');
            if (!file_exists($path)) {
                return 0;
            }

            // Approximate: count rows and multiply by average row size
            $count = DB::table($tableName)->count();
            $avgRowSize = 1024; // 1KB average row size estimate

            return round(($count * $avgRowSize) / 1048576, 2);

        } catch (\Exception $e) {
            return 0;
        }
    }
}
