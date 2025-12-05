<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class RouteSegment extends Model
{
    protected $table = 'route_segments';

    protected $fillable = [
        'origin_lat',
        'origin_lng',
        'destination_lat',
        'destination_lng',
        'polyline',
        'distance_meters',
        'duration_seconds',
        'expires_at'
    ];

    protected $casts = [
        'origin_lat' => 'decimal:8',
        'origin_lng' => 'decimal:8',
        'destination_lat' => 'decimal:8',
        'destination_lng' => 'decimal:8',
        'distance_meters' => 'integer',
        'duration_seconds' => 'integer',
        'expires_at' => 'datetime',
    ];

    /**
     * Busca segmento em cache (tolerância de 0.001 graus ~= 100m)
     */
    public static function findCached(
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng
    ): ?self {
        $tolerance = 0.001; // ~100 metros

        return self::where('expires_at', '>', Carbon::now())
            ->whereBetween('origin_lat', [$originLat - $tolerance, $originLat + $tolerance])
            ->whereBetween('origin_lng', [$originLng - $tolerance, $originLng + $tolerance])
            ->whereBetween('destination_lat', [$destLat - $tolerance, $destLat + $tolerance])
            ->whereBetween('destination_lng', [$destLng - $tolerance, $destLng + $tolerance])
            ->first();
    }

    /**
     * Salva segmento no cache (30 dias de validade)
     */
    public static function saveSegment(
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng,
        string $polyline,
        int $distanceMeters,
        int $durationSeconds
    ): self {
        return self::create([
            'origin_lat' => $originLat,
            'origin_lng' => $originLng,
            'destination_lat' => $destLat,
            'destination_lng' => $destLng,
            'polyline' => $polyline,
            'distance_meters' => $distanceMeters,
            'duration_seconds' => $durationSeconds,
            'expires_at' => Carbon::now()->addDays(30)
        ]);
    }

    /**
     * Limpa segmentos expirados
     */
    public static function clearExpired(): int
    {
        return self::where('expires_at', '<', Carbon::now())->delete();
    }
}
