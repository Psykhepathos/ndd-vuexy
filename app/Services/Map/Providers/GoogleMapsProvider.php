<?php

namespace App\Services\Map\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * GoogleMapsProvider - Google Maps Directions API routing
 *
 * Supports up to 25 waypoints per request (1 origin + 23 intermediates + 1 destination)
 * Requires GOOGLE_MAPS_API_KEY in .env
 * Cost: ~$0.005 per request
 */
class GoogleMapsProvider implements RouteProviderInterface
{
    /**
     * Google Maps Directions API base URL
     */
    private string $baseUrl = 'https://maps.googleapis.com/maps/api/directions/json';

    /**
     * API Key from .env
     */
    private ?string $apiKey;

    /**
     * Request timeout in seconds
     */
    private int $timeout = 30;

    public function __construct()
    {
        $this->apiKey = config('services.google_maps.api_key');
    }

    public function getName(): string
    {
        return 'google';
    }

    public function calculateRoute(array $waypoints, array $options = []): array
    {
        if (!$this->isAvailable()) {
            return [
                'success' => false,
                'error' => 'Google Maps API key not configured',
                'provider' => $this->getName()
            ];
        }

        if (count($waypoints) < 2) {
            return [
                'success' => false,
                'error' => 'At least 2 waypoints required',
                'provider' => $this->getName()
            ];
        }

        if (count($waypoints) > $this->getMaxWaypoints()) {
            return [
                'success' => false,
                'error' => "Google Maps supports max {$this->getMaxWaypoints()} waypoints",
                'provider' => $this->getName()
            ];
        }

        try {
            // Origin and destination
            $origin = $waypoints[0];
            $destination = $waypoints[count($waypoints) - 1];

            // Intermediates (if any)
            $intermediates = array_slice($waypoints, 1, count($waypoints) - 2);

            // Build request parameters
            $params = [
                'origin' => "{$origin[0]},{$origin[1]}",
                'destination' => "{$destination[0]},{$destination[1]}",
                'mode' => 'driving',
                'key' => $this->apiKey,
                'units' => 'metric'
            ];

            // Add waypoints if present
            if (count($intermediates) > 0) {
                $waypointStrings = array_map(function($wp) {
                    return "{$wp[0]},{$wp[1]}";
                }, $intermediates);
                $params['waypoints'] = implode('|', $waypointStrings);
            }

            Log::info('Google Maps API request', [
                'waypoints_count' => count($waypoints),
                'origin' => $params['origin'],
                'destination' => $params['destination']
            ]);

            $response = Http::timeout($this->timeout)->get($this->baseUrl, $params);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'error' => 'Google Maps API request failed',
                    'provider' => $this->getName()
                ];
            }

            $data = $response->json();

            if ($data['status'] !== 'OK') {
                return [
                    'success' => false,
                    'error' => "Google Maps API error: {$data['status']}",
                    'provider' => $this->getName()
                ];
            }

            // Extract route data
            $route = $data['routes'][0];
            $coordinates = $this->decodePolyline($route['overview_polyline']['points']);

            // Calculate total distance and duration
            $totalDistance = 0;
            $totalDuration = 0;

            foreach ($route['legs'] as $leg) {
                $totalDistance += $leg['distance']['value']; // meters
                $totalDuration += $leg['duration']['value']; // seconds
            }

            return [
                'success' => true,
                'coordinates' => $coordinates,
                'distance_km' => round($totalDistance / 1000, 2),
                'duration_seconds' => $totalDuration,
                'provider' => $this->getName(),
                'error' => null
            ];

        } catch (\Exception $e) {
            Log::error('Google Maps route calculation failed', [
                'error' => $e->getMessage(),
                'waypoints_count' => count($waypoints)
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'provider' => $this->getName()
            ];
        }
    }

    /**
     * Decode Google Maps polyline to coordinates
     *
     * @param string $encoded Encoded polyline string
     * @return array Array of [lat, lon] coordinates
     */
    private function decodePolyline(string $encoded): array
    {
        $coordinates = [];
        $index = 0;
        $len = strlen($encoded);
        $lat = 0;
        $lng = 0;

        while ($index < $len) {
            $b = 0;
            $shift = 0;
            $result = 0;

            do {
                $b = ord($encoded[$index++]) - 63;
                $result |= ($b & 0x1f) << $shift;
                $shift += 5;
            } while ($b >= 0x20);

            $dlat = (($result & 1) ? ~($result >> 1) : ($result >> 1));
            $lat += $dlat;

            $shift = 0;
            $result = 0;

            do {
                $b = ord($encoded[$index++]) - 63;
                $result |= ($b & 0x1f) << $shift;
                $shift += 5;
            } while ($b >= 0x20);

            $dlng = (($result & 1) ? ~($result >> 1) : ($result >> 1));
            $lng += $dlng;

            $coordinates[] = [
                round($lat / 1e5, 6),
                round($lng / 1e5, 6)
            ];
        }

        return $coordinates;
    }

    public function getMaxWaypoints(): int
    {
        // Google Maps: 1 origin + 23 waypoints + 1 destination = 25 total
        return 25;
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }

    public function getPriority(): int
    {
        // Priority 50 (medium) - use as fallback when OSRM fails
        return 50;
    }

    public function getCostPerRequest(): float
    {
        // ~$0.005 per request
        return 0.005;
    }
}
