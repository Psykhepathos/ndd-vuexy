<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OSRMController extends Controller
{
    /**
     * Proxy para API do OSRM (Open Source Routing Machine)
     * Resolve problemas de CORS ao fazer requisição do backend
     */
    public function getRoute(Request $request)
    {
        try {
            // Validar parâmetros
            $request->validate([
                'coordinates' => 'required|string',
            ]);

            $coordinates = $request->input('coordinates');

            // CORREÇÃO BUG #53: SSRF vulnerability - validar formato de coordenadas
            // Formato esperado: "lon1,lat1;lon2,lat2;..." onde lon/lat são floats
            // Exemplo: "-46.633308,-23.550520;-43.172896,-22.906847"
            if (!preg_match('/^-?\d+(\.\d+)?,-?\d+(\.\d+)?(;-?\d+(\.\d+)?,-?\d+(\.\d+)?)*$/', $coordinates)) {
                Log::warning("OSRM Proxy: Formato de coordenadas inválido", [
                    'coordinates' => $coordinates,
                    'ip' => $request->ip()
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Formato de coordenadas inválido. Use: lon1,lat1;lon2,lat2'
                ], 400);
            }

            // CORREÇÃO BUG #53: Validar range de latitude/longitude
            $coordPairs = explode(';', $coordinates);
            foreach ($coordPairs as $pair) {
                list($lon, $lat) = explode(',', $pair);
                $lon = (float)$lon;
                $lat = (float)$lat;

                // Validar range válido: lat [-90, 90], lon [-180, 180]
                if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
                    Log::warning("OSRM Proxy: Coordenadas fora do range válido", [
                        'lat' => $lat,
                        'lon' => $lon,
                        'ip' => $request->ip()
                    ]);

                    return response()->json([
                        'success' => false,
                        'error' => 'Coordenadas fora do range válido (lat: -90 a 90, lon: -180 a 180)'
                    ], 400);
                }
            }

            // CORREÇÃO BUG #53: Limitar número de waypoints para prevenir DoS
            if (count($coordPairs) > 50) {
                return response()->json([
                    'success' => false,
                    'error' => 'Máximo de 50 waypoints permitidos'
                ], 400);
            }

            // Construir URL do OSRM (usa servidor configurado ou default)
            $osrmServer = config('services.osrm.servers.0', 'https://router.project-osrm.org');
            $url = "{$osrmServer}/route/v1/driving/{$coordinates}";
            $url .= "?overview=full&geometries=geojson";

            // NOTA: Coordinates são dados públicos (lat/lon) e não precisam sanitização para logging.
            // Não são dados sensíveis como CPF, senha, ou informações pessoais (LGPD).
            // Lat/lon são coordenadas geográficas públicas de municípios/estradas.
            Log::info("OSRM Proxy: Requisitando rota", ['url' => $url]);

            // Fazer requisição para OSRM
            $response = Http::timeout(10)->get($url);

            if ($response->successful()) {
                $data = $response->json();

                Log::info("OSRM Proxy: Rota obtida com sucesso", [
                    'code' => $data['code'] ?? 'unknown',
                    'routes' => count($data['routes'] ?? [])
                ]);

                return response()->json([
                    'success' => true,
                    'data' => $data
                ]);
            } else {
                Log::error("OSRM Proxy: Erro na resposta", [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'OSRM retornou erro: ' . $response->status()
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error("OSRM Proxy: Exceção", [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
