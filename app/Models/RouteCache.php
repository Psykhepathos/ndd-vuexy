<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RouteCache extends Model
{
    use HasFactory;

    protected $table = 'route_cache';

    protected $fillable = [
        'cache_key',
        'waypoints',
        'route_coordinates',
        'total_distance',
        'duration_seconds',
        'waypoints_count',
        'source',
        'expires_at'
    ];

    protected $casts = [
        'waypoints' => 'array',
        'total_distance' => 'decimal:3',
        'expires_at' => 'datetime'
    ];

    /**
     * Gerar chave de cache baseada nos waypoints
     */
    public static function generateCacheKey(array $waypoints): string
    {
        // Ordenar waypoints e criar hash para garantir consistência
        $sortedWaypoints = $waypoints;
        sort($sortedWaypoints);
        
        return hash('sha256', json_encode($sortedWaypoints));
    }

    /**
     * Buscar rota em cache
     */
    public static function findCachedRoute(array $waypoints): ?self
    {
        $cacheKey = self::generateCacheKey($waypoints);
        
        return self::where('cache_key', $cacheKey)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();
    }

    /**
     * Salvar rota no cache
     */
    public static function cacheRoute(
        array $waypoints, 
        array $routeCoordinates, 
        float $totalDistance, 
        string $source = 'google_maps'
    ): self {
        $cacheKey = self::generateCacheKey($waypoints);
        
        return self::updateOrCreate(
            ['cache_key' => $cacheKey],
            [
                'waypoints' => $waypoints,
                'route_coordinates' => json_encode($routeCoordinates),
                'total_distance' => $totalDistance,
                'waypoints_count' => count($waypoints),
                'source' => $source,
                'expires_at' => now()->addDays(30) // Cache por 30 dias
            ]
        );
    }

    /**
     * Obter coordenadas da rota decodificadas
     */
    public function getRouteCoordinatesDecoded(): array
    {
        return json_decode($this->route_coordinates, true) ?? [];
    }

    /**
     * Limpar cache expirado
     */
    public static function clearExpired(): int
    {
        return self::where('expires_at', '<', now())->delete();
    }

    /**
     * Estatísticas do cache
     */
    public static function getStats(): array
    {
        return [
            'total_routes' => self::count(),
            'total_size_mb' => round(self::sum(\DB::raw('LENGTH(route_coordinates)')) / 1024 / 1024, 2),
            'expired_routes' => self::where('expires_at', '<', now())->count(),
            'avg_waypoints' => round(self::avg('waypoints_count'), 1),
            'sources' => self::groupBy('source')->selectRaw('source, count(*) as count')->pluck('count', 'source')->toArray()
        ];
    }
}
