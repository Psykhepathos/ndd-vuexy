<?php

namespace App\Services\Map\Utils;

/**
 * CoordinateConverter - Convert coordinates between different formats
 *
 * Handles:
 * - Progress GPS format (e.g., "230876543" -> -23.0876543)
 * - Decimal degrees validation
 * - Brazil bounds checking
 */
class CoordinateConverter
{
    /**
     * Brazil geographic bounds
     */
    private const BRAZIL_BOUNDS = [
        'lat_min' => -35.0,
        'lat_max' => 6.0,
        'lon_min' => -75.0,
        'lon_max' => -33.0
    ];

    /**
     * Convert Progress GPS format to decimal
     *
     * Progress format: "230876543" means -23.0876543 (first 2-3 digits + decimal)
     *
     * @param string $progressGps Progress GPS string
     * @return float|null Decimal coordinate or null if invalid
     */
    public static function progressToDecimal(string $progressGps): ?float
    {
        if (empty($progressGps) || $progressGps === '0') {
            return null;
        }

        // Remove spaces and validate numeric
        $progressGps = trim($progressGps);
        if (!is_numeric($progressGps)) {
            return null;
        }

        // Convert to float and divide by 10^7
        $decimal = floatval($progressGps) / 10000000;

        // Validate result
        if (abs($decimal) > 180) {
            return null;
        }

        return round($decimal, 7);
    }

    /**
     * Convert decimal to Progress GPS format
     *
     * @param float $decimal Decimal coordinate
     * @return string Progress GPS format
     */
    public static function decimalToProgress(float $decimal): string
    {
        return (string)round($decimal * 10000000);
    }

    /**
     * Validate decimal coordinates
     *
     * @param float $lat Latitude
     * @param float $lon Longitude
     * @return bool True if valid
     */
    public static function isValid(float $lat, float $lon): bool
    {
        return $lat >= -90 && $lat <= 90 && $lon >= -180 && $lon <= 180;
    }

    /**
     * Check if coordinates are within Brazil bounds
     *
     * @param float $lat Latitude
     * @param float $lon Longitude
     * @return bool True if within Brazil
     */
    public static function isInBrazil(float $lat, float $lon): bool
    {
        return $lat >= self::BRAZIL_BOUNDS['lat_min']
            && $lat <= self::BRAZIL_BOUNDS['lat_max']
            && $lon >= self::BRAZIL_BOUNDS['lon_min']
            && $lon <= self::BRAZIL_BOUNDS['lon_max'];
    }

    /**
     * Sanitize coordinate value
     *
     * @param mixed $value Raw value
     * @return float|null Sanitized coordinate or null
     */
    public static function sanitize($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Convert to float
        $float = is_numeric($value) ? floatval($value) : null;

        if ($float === null) {
            return null;
        }

        // Check if it's a Progress format (large number)
        if (abs($float) > 180) {
            return self::progressToDecimal((string)$float);
        }

        return $float;
    }

    /**
     * Format coordinates for display
     *
     * @param float $lat Latitude
     * @param float $lon Longitude
     * @param int $precision Decimal places (default: 6)
     * @return string Formatted string "lat, lon"
     */
    public static function format(float $lat, float $lon, int $precision = 6): string
    {
        return round($lat, $precision) . ', ' . round($lon, $precision);
    }

    /**
     * Parse coordinate string
     *
     * Accepts: "lat, lon", "lat,lon", "lat lon"
     *
     * @param string $coordString Coordinate string
     * @return array|null [lat, lon] or null
     */
    public static function parse(string $coordString): ?array
    {
        // Replace comma or space with |
        $parts = preg_split('/[,\s]+/', trim($coordString));

        if (count($parts) !== 2) {
            return null;
        }

        $lat = self::sanitize($parts[0]);
        $lon = self::sanitize($parts[1]);

        if ($lat === null || $lon === null) {
            return null;
        }

        if (!self::isValid($lat, $lon)) {
            return null;
        }

        return [$lat, $lon];
    }
}
