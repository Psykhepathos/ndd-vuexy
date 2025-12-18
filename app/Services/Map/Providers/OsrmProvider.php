<?php

namespace App\Services\Map\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OsrmProvider - Free OSRM routing via Laravel proxy
 *
 * Uses existing RoutingController proxy (POST /api/routing/route)
 * Supports up to 10-15 waypoints per request
 */
class OsrmProvider implements RouteProviderInterface
{
    /**
     * OSRM servers to try (in order) - carregados do config
     */
    private array $servers;

    /**
     * Request timeout in seconds
     * CORREÇÃO BUG IMPORTANTE #5: Aumentado para 15s para rotas longas
     * 5s era insuficiente para rotas com muitos waypoints ou distâncias grandes
     * 15s é adequado para rotas brasileiras (SP-RJ ~450km, SP-BA ~1900km)
     */
    private int $timeout;

    public function __construct()
    {
        // Carregar servidores do config (com defaults públicos)
        $configServers = config('services.osrm.servers', []);
        $this->servers = array_map(function($server) {
            // Adicionar /route/v1 se não estiver presente
            return rtrim($server, '/') . '/route/v1';
        }, $configServers);

        // Fallback se config vazio
        if (empty($this->servers)) {
            $this->servers = [
                'https://routing.openstreetmap.de/routed-car/route/v1',
                'http://router.project-osrm.org/route/v1'
            ];
        }

        $this->timeout = (int) config('services.osrm.timeout', 15);
    }

    public function getName(): string
    {
        return 'osrm';
    }

    public function calculateRoute(array $waypoints, array $options = []): array
    {
        if (count($waypoints) < 2) {
            return [
                'success' => false,
                'error' => 'At least 2 waypoints required',
                'provider' => $this->getName()
            ];
        }

        // Note: MapService handles chunking for routes >10 waypoints
        // OsrmProvider only processes individual segments (≤10 waypoints)

        // For 2 waypoints, use existing proxy endpoint
        if (count($waypoints) === 2) {
            return $this->calculateSegment($waypoints[0], $waypoints[1]);
        }

        // For multiple waypoints, calculate segment by segment
        return $this->calculateMultiSegment($waypoints);
    }

    /**
     * Calculate route for a single segment (2 points)
     *
     * Calls OSRM servers directly (not via proxy)
     */
    private function calculateSegment(array $start, array $end): array
    {
        // Try multiple OSRM servers
        foreach ($this->servers as $server) {
            try {
                $url = "{$server}/driving/{$start[1]},{$start[0]};{$end[1]},{$end[0]}?geometries=geojson&overview=full";

                Log::debug("Trying OSRM server: {$server}");

                $response = Http::timeout($this->timeout)
                    ->withHeaders([
                        'User-Agent' => 'Laravel-NDD-MapService/1.0',
                        'Accept' => 'application/json'
                    ])
                    ->retry(2, 1000)
                    ->get($url);

                if (!$response->successful()) {
                    Log::warning("OSRM server failed: {$server}");
                    continue;
                }

                $data = $response->json();

                if (!isset($data['routes'][0]['geometry']['coordinates'])) {
                    Log::warning("OSRM response missing coordinates: {$server}");
                    continue;
                }

                // Convert from [lng,lat] to [lat,lng]
                $coords = $data['routes'][0]['geometry']['coordinates'];
                $coordinates = array_map(function($coord) {
                    return [$coord[1], $coord[0]];
                }, $coords);

                $distance = ($data['routes'][0]['distance'] ?? 0) / 1000; // meters to km
                $duration = $data['routes'][0]['duration'] ?? null;

                Log::info("OSRM route calculated successfully", [
                    'server' => $server,
                    'distance_km' => $distance,
                    'points' => count($coordinates)
                ]);

                return [
                    'success' => true,
                    'coordinates' => $coordinates,
                    'distance_km' => round($distance, 2),
                    'duration_seconds' => $duration ? (int)$duration : null,
                    'provider' => $this->getName(),
                    'error' => null
                ];

            } catch (\Exception $e) {
                Log::warning("OSRM server error: {$server}", [
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }

        // All servers failed
        return [
            'success' => false,
            'error' => 'All OSRM servers failed',
            'provider' => $this->getName()
        ];
    }

    /**
     * Calculate route with multiple waypoints (segment by segment)
     */
    private function calculateMultiSegment(array $waypoints): array
    {
        $allCoordinates = [];
        $totalDistance = 0;
        $totalDuration = 0;

        for ($i = 0; $i < count($waypoints) - 1; $i++) {
            $segment = $this->calculateSegment($waypoints[$i], $waypoints[$i + 1]);

            if (!$segment['success']) {
                // If any segment fails, return error
                return [
                    'success' => false,
                    'error' => "Segment {$i} failed: " . $segment['error'],
                    'provider' => $this->getName()
                ];
            }

            // Add coordinates (skip first point of subsequent segments to avoid duplicates)
            if ($i === 0) {
                $allCoordinates = array_merge($allCoordinates, $segment['coordinates']);
            } else {
                $allCoordinates = array_merge($allCoordinates, array_slice($segment['coordinates'], 1));
            }

            $totalDistance += $segment['distance_km'];
            $totalDuration += $segment['duration_seconds'] ?? 0;
        }

        return [
            'success' => true,
            'coordinates' => $allCoordinates,
            'distance_km' => $totalDistance,
            'duration_seconds' => $totalDuration > 0 ? $totalDuration : null,
            'provider' => $this->getName(),
            'error' => null
        ];
    }

    public function getMaxWaypoints(): int
    {
        // OSRM public servers support 10-15 waypoints
        // We limit to 10 for safety
        return 10;
    }

    public function isAvailable(): bool
    {
        // OSRM is always available (free, no API key required)
        return true;
    }

    public function getPriority(): int
    {
        // Priority 10 (high) - prefer OSRM for free routing
        return 10;
    }

    public function getCostPerRequest(): float
    {
        // Free!
        return 0.0;
    }
}
