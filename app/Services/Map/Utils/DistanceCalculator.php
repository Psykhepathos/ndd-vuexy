<?php

namespace App\Services\Map\Utils;

/**
 * DistanceCalculator - Geographic distance calculations
 *
 * Implements:
 * - Haversine formula (great-circle distance)
 * - Bounding box calculations
 * - Point-in-polygon checks
 */
class DistanceCalculator
{
    /**
     * Earth radius in kilometers
     */
    private const EARTH_RADIUS_KM = 6371;

    /**
     * Earth radius in meters
     */
    private const EARTH_RADIUS_M = 6371000;

    /**
     * Calculate distance between two points using Haversine formula
     *
     * @param float $lat1 Latitude point 1
     * @param float $lon1 Longitude point 1
     * @param float $lat2 Latitude point 2
     * @param float $lon2 Longitude point 2
     * @param string $unit Unit ('km', 'm', 'mi')
     * @return float Distance in specified unit
     */
    public static function haversine(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2,
        string $unit = 'km'
    ): float {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        $distance = self::EARTH_RADIUS_KM * $c;

        // Convert to requested unit
        switch ($unit) {
            case 'm':
                return $distance * 1000;
            case 'mi':
                return $distance * 0.621371;
            default:
                return $distance;
        }
    }

    /**
     * Calculate total distance for a route (array of waypoints)
     *
     * @param array $waypoints Array of [lat, lon] coordinates
     * @param string $unit Unit ('km', 'm', 'mi')
     * @return float Total distance
     */
    public static function routeDistance(array $waypoints, string $unit = 'km'): float
    {
        if (count($waypoints) < 2) {
            return 0;
        }

        $totalDistance = 0;

        for ($i = 0; $i < count($waypoints) - 1; $i++) {
            $totalDistance += self::haversine(
                $waypoints[$i][0],
                $waypoints[$i][1],
                $waypoints[$i + 1][0],
                $waypoints[$i + 1][1],
                $unit
            );
        }

        return round($totalDistance, 2);
    }

    /**
     * Calculate bounding box for array of coordinates
     *
     * @param array $coordinates Array of [lat, lon] coordinates
     * @param float $paddingKm Optional padding in km (default: 0)
     * @return array [[minLat, minLon], [maxLat, maxLon]]
     */
    public static function calculateBounds(array $coordinates, float $paddingKm = 0): array
    {
        if (empty($coordinates)) {
            return [[-35, -75], [6, -33]]; // Brazil default
        }

        $lats = array_column($coordinates, 0);
        $lons = array_column($coordinates, 1);

        $minLat = min($lats);
        $maxLat = max($lats);
        $minLon = min($lons);
        $maxLon = max($lons);

        // Apply padding if specified
        if ($paddingKm > 0) {
            $latPadding = $paddingKm / 111; // 1 degree ≈ 111 km
            $lonPadding = $paddingKm / (111 * cos(deg2rad(($minLat + $maxLat) / 2)));

            $minLat -= $latPadding;
            $maxLat += $latPadding;
            $minLon -= $lonPadding;
            $maxLon += $lonPadding;
        }

        return [
            [round($minLat, 6), round($minLon, 6)],
            [round($maxLat, 6), round($maxLon, 6)]
        ];
    }

    /**
     * Calculate center point of multiple coordinates
     *
     * @param array $coordinates Array of [lat, lon] coordinates
     * @return array [lat, lon] center point
     */
    public static function calculateCenter(array $coordinates): array
    {
        if (empty($coordinates)) {
            return [-14.2350, -51.9253]; // Brazil center
        }

        $lats = array_column($coordinates, 0);
        $lons = array_column($coordinates, 1);

        return [
            round(array_sum($lats) / count($lats), 6),
            round(array_sum($lons) / count($lons), 6)
        ];
    }

    /**
     * Check if two points are within specified distance
     *
     * @param float $lat1 Latitude point 1
     * @param float $lon1 Longitude point 1
     * @param float $lat2 Latitude point 2
     * @param float $lon2 Longitude point 2
     * @param float $maxDistanceKm Maximum distance in km
     * @return bool True if within distance
     */
    public static function isWithinDistance(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2,
        float $maxDistanceKm
    ): bool {
        $distance = self::haversine($lat1, $lon1, $lat2, $lon2, 'km');
        return $distance <= $maxDistanceKm;
    }

    /**
     * Find nearest point to a reference point
     *
     * @param array $referencePoint [lat, lon]
     * @param array $points Array of [lat, lon] points
     * @return array|null Nearest point with distance: ['point' => [lat, lon], 'distance' => float]
     */
    public static function findNearest(array $referencePoint, array $points): ?array
    {
        if (empty($points)) {
            return null;
        }

        $nearest = null;
        $minDistance = PHP_FLOAT_MAX;

        foreach ($points as $point) {
            $distance = self::haversine(
                $referencePoint[0],
                $referencePoint[1],
                $point[0],
                $point[1],
                'km'
            );

            if ($distance < $minDistance) {
                $minDistance = $distance;
                $nearest = $point;
            }
        }

        return [
            'point' => $nearest,
            'distance' => round($minDistance, 2)
        ];
    }

    /**
     * Calculate bearing (direction) from point 1 to point 2
     *
     * @param float $lat1 Latitude point 1
     * @param float $lon1 Longitude point 1
     * @param float $lat2 Latitude point 2
     * @param float $lon2 Longitude point 2
     * @return float Bearing in degrees (0-360)
     */
    public static function calculateBearing(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2
    ): float {
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $lonDiff = deg2rad($lon2 - $lon1);

        $y = sin($lonDiff) * cos($lat2Rad);
        $x = cos($lat1Rad) * sin($lat2Rad) -
            sin($lat1Rad) * cos($lat2Rad) * cos($lonDiff);

        $bearing = atan2($y, $x);
        $bearing = rad2deg($bearing);
        $bearing = fmod(($bearing + 360), 360);

        return round($bearing, 2);
    }

    /**
     * Get compass direction from bearing
     *
     * @param float $bearing Bearing in degrees
     * @return string Compass direction (N, NE, E, SE, S, SW, W, NW)
     */
    public static function bearingToCompass(float $bearing): string
    {
        $directions = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'];
        $index = round($bearing / 45) % 8;
        return $directions[$index];
    }
}
