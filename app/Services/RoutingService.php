<?php

namespace App\Services;

use App\Models\RouteSegment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class RoutingService
{
    /**
     * Calcula rota entre múltiplos pontos (waypoints) usando Google Directions API
     * com cache inteligente de segmentos
     */
    public function calculateRoute(array $waypoints): array
    {
        if (count($waypoints) < 2) {
            return [
                'success' => false,
                'error' => 'É necessário pelo menos 2 pontos para calcular uma rota'
            ];
        }

        $segments = [];
        $totalDistance = 0;
        $totalDuration = 0;
        $allPolylines = [];

        // Calcular rota segmento por segmento (ponto A → ponto B)
        for ($i = 0; $i < count($waypoints) - 1; $i++) {
            $origin = $waypoints[$i];
            $destination = $waypoints[$i + 1];

            Log::info('Calculando segmento de rota', [
                'from' => $origin,
                'to' => $destination,
                'segment' => ($i + 1) . '/' . (count($waypoints) - 1)
            ]);

            // Buscar no cache primeiro
            $cached = RouteSegment::findCached(
                $origin['lat'],
                $origin['lng'],
                $destination['lat'],
                $destination['lng']
            );

            if ($cached) {
                Log::info('💾 Segmento encontrado no cache!', [
                    'distance' => $cached->distance_meters,
                    'duration' => $cached->duration_seconds
                ]);

                $segments[] = [
                    'origin' => $origin,
                    'destination' => $destination,
                    'polyline' => $cached->polyline,
                    'distance_meters' => $cached->distance_meters,
                    'duration_seconds' => $cached->duration_seconds,
                    'cached' => true
                ];

                $totalDistance += $cached->distance_meters;
                $totalDuration += $cached->duration_seconds;
                $allPolylines[] = $cached->polyline;
            } else {
                // Buscar via Google Directions API
                $result = $this->fetchDirections($origin, $destination);

                if (!$result['success']) {
                    Log::error('Erro ao buscar direções', [
                        'origin' => $origin,
                        'destination' => $destination,
                        'error' => $result['error']
                    ]);

                    return $result;
                }

                Log::info('🌐 Segmento calculado via Google Directions API', [
                    'distance' => $result['distance_meters'],
                    'duration' => $result['duration_seconds']
                ]);

                // Salvar no cache
                RouteSegment::saveSegment(
                    $origin['lat'],
                    $origin['lng'],
                    $destination['lat'],
                    $destination['lng'],
                    $result['polyline'],
                    $result['distance_meters'],
                    $result['duration_seconds']
                );

                $segments[] = [
                    'origin' => $origin,
                    'destination' => $destination,
                    'polyline' => $result['polyline'],
                    'distance_meters' => $result['distance_meters'],
                    'duration_seconds' => $result['duration_seconds'],
                    'cached' => false
                ];

                $totalDistance += $result['distance_meters'];
                $totalDuration += $result['duration_seconds'];
                $allPolylines[] = $result['polyline'];

                // Rate limiting: aguardar 200ms entre requisições
                usleep(200000);
            }
        }

        return [
            'success' => true,
            'data' => [
                'segments' => $segments,
                'total_distance_meters' => $totalDistance,
                'total_duration_seconds' => $totalDuration,
                'polylines' => $allPolylines,
                'cached_segments' => count(array_filter($segments, fn($s) => $s['cached'])),
                'new_segments' => count(array_filter($segments, fn($s) => !$s['cached']))
            ]
        ];
    }

    /**
     * Busca direções via Google Directions API
     */
    private function fetchDirections(array $origin, array $destination): array
    {
        try {
            $apiKey = env('GOOGLE_MAPS_API_KEY');

            if (!$apiKey) {
                return [
                    'success' => false,
                    'error' => 'Google Maps API Key não configurada'
                ];
            }

            $originStr = "{$origin['lat']},{$origin['lng']}";
            $destStr = "{$destination['lat']},{$destination['lng']}";

            $response = Http::timeout(15)->get('https://maps.googleapis.com/maps/api/directions/json', [
                'origin' => $originStr,
                'destination' => $destStr,
                'key' => $apiKey,
                'mode' => 'driving',
                'language' => 'pt-BR',
                'region' => 'br'
            ]);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'error' => 'Erro na requisição Google Directions API: ' . $response->status()
                ];
            }

            $data = $response->json();

            if ($data['status'] !== 'OK' || empty($data['routes'])) {
                return [
                    'success' => false,
                    'error' => 'Google Directions API retornou status: ' . ($data['status'] ?? 'UNKNOWN')
                ];
            }

            $route = $data['routes'][0];
            $leg = $route['legs'][0];

            return [
                'success' => true,
                'polyline' => $route['overview_polyline']['points'],
                'distance_meters' => $leg['distance']['value'],
                'duration_seconds' => $leg['duration']['value']
            ];

        } catch (\Exception $e) {
            Log::error('Exceção ao buscar direções', [
                'origin' => $origin,
                'destination' => $destination,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erro ao buscar direções: ' . $e->getMessage()
            ];
        }
    }
}
