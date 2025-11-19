<?php

namespace App\Services\Map\Providers;

/**
 * RouteProviderInterface - Common interface for all routing providers
 *
 * Providers: Google Maps, OSRM, HERE Maps, MapBox, etc
 */
interface RouteProviderInterface
{
    /**
     * Get provider name
     *
     * @return string Provider identifier (e.g., 'google', 'osrm', 'here')
     */
    public function getName(): string;

    /**
     * Calculate route between waypoints
     *
     * @param array $waypoints Array of waypoints [[lat, lon], [lat, lon], ...]
     * @param array $options Additional options (profile, alternatives, etc)
     * @return array Route result with structure:
     * [
     *   'success' => bool,
     *   'coordinates' => [[lat, lon], ...],
     *   'distance_km' => float,
     *   'duration_seconds' => int|null,
     *   'provider' => string,
     *   'error' => string|null
     * ]
     */
    public function calculateRoute(array $waypoints, array $options = []): array;

    /**
     * Get maximum waypoints per request for this provider
     *
     * @return int Maximum number of waypoints
     */
    public function getMaxWaypoints(): int;

    /**
     * Check if provider is available/configured
     *
     * @return bool True if provider can be used
     */
    public function isAvailable(): bool;

    /**
     * Get provider priority (lower = higher priority)
     *
     * Used for automatic provider selection
     *
     * @return int Priority (1-100)
     */
    public function getPriority(): int;

    /**
     * Get provider cost estimate per request
     *
     * @return float Cost in USD (0 for free providers)
     */
    public function getCostPerRequest(): float;
}
