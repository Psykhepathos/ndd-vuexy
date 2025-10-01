<?php

namespace App\Services;

use App\Models\MunicipioCoordenada;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class GeocodingService
{
    /**
     * Busca coordenadas de um município pelo código IBGE
     * Primeiro tenta cache local, depois Google Geocoding API
     */
    public function getCoordenadasByIbge(
        string $codigoIbge,
        string $nomeMunicipio,
        string $uf
    ): ?array {
        // 1. Tenta buscar no cache local primeiro
        $cached = MunicipioCoordenada::findByCodigoIbge($codigoIbge);
        if ($cached) {
            Log::info('Coordenadas encontradas no cache local', [
                'codigo_ibge' => $codigoIbge,
                'municipio' => $nomeMunicipio,
                'lat' => $cached->latitude,
                'lon' => $cached->longitude
            ]);

            return [
                'lat' => (float) $cached->latitude,
                'lon' => (float) $cached->longitude,
                'fonte' => $cached->fonte,
                'cached' => true
            ];
        }

        // 2. Se não encontrou, faz geocoding via Google
        Log::info('Coordenadas não encontradas no cache, fazendo geocoding', [
            'codigo_ibge' => $codigoIbge,
            'municipio' => $nomeMunicipio,
            'uf' => $uf
        ]);

        $coordenadas = $this->geocodeByGoogle($nomeMunicipio, $uf);

        // 3. Se encontrou via Google, salva no cache
        if ($coordenadas) {
            MunicipioCoordenada::salvarCoordenadas(
                $codigoIbge,
                $nomeMunicipio,
                $uf,
                $coordenadas['lat'],
                $coordenadas['lon'],
                'google_geocoding'
            );

            Log::info('Coordenadas salvas no cache local', [
                'codigo_ibge' => $codigoIbge,
                'lat' => $coordenadas['lat'],
                'lon' => $coordenadas['lon']
            ]);

            return [
                'lat' => $coordenadas['lat'],
                'lon' => $coordenadas['lon'],
                'fonte' => 'google_geocoding',
                'cached' => false
            ];
        }

        return null;
    }

    /**
     * Faz geocoding usando Google Geocoding API
     */
    private function geocodeByGoogle(string $nomeMunicipio, string $uf): ?array
    {
        try {
            $address = "{$nomeMunicipio}, {$uf}, Brasil";
            $apiKey = env('GOOGLE_MAPS_API_KEY');

            if (!$apiKey) {
                Log::warning('Google Maps API Key não configurada');
                return null;
            }

            $response = Http::timeout(10)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => $address,
                'key' => $apiKey,
                'region' => 'br',
                'language' => 'pt-BR'
            ]);

            if (!$response->successful()) {
                Log::error('Erro na requisição Google Geocoding API', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return null;
            }

            $data = $response->json();

            if ($data['status'] === 'OK' && count($data['results']) > 0) {
                $location = $data['results'][0]['geometry']['location'];

                Log::info('Geocoding via Google bem-sucedido', [
                    'municipio' => $nomeMunicipio,
                    'uf' => $uf,
                    'lat' => $location['lat'],
                    'lng' => $location['lng']
                ]);

                return [
                    'lat' => $location['lat'],
                    'lon' => $location['lng']
                ];
            }

            Log::warning('Google Geocoding não encontrou resultados', [
                'municipio' => $nomeMunicipio,
                'uf' => $uf,
                'status' => $data['status']
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Erro ao fazer geocoding via Google', [
                'municipio' => $nomeMunicipio,
                'uf' => $uf,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Busca coordenadas em lote para múltiplos municípios
     */
    public function getCoordenadasLote(array $municipios): array
    {
        $resultado = [];

        foreach ($municipios as $municipio) {
            $codigoIbge = (string) $municipio['cdibge'];
            $nomeMunicipio = trim($municipio['desmun']);
            $uf = trim($municipio['desest']);

            $coordenadas = $this->getCoordenadasByIbge($codigoIbge, $nomeMunicipio, $uf);

            $resultado[] = [
                'codigo_ibge' => $codigoIbge,
                'nome_municipio' => $nomeMunicipio,
                'uf' => $uf,
                'coordenadas' => $coordenadas
            ];

            // Rate limiting: aguarda 200ms entre requisições para não ultrapassar limites da API
            if ($coordenadas && !$coordenadas['cached']) {
                usleep(200000); // 200ms
            }
        }

        return $resultado;
    }
}
