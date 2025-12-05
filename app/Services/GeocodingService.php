<?php

namespace App\Services;

use App\Models\MunicipioCoordenada;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

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
            // CORREÇÃO BUG #67: Usar config() em vez de env() no runtime
            $apiKey = config('services.google_maps.api_key');

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
     *
     * @param array $municipios Array de municípios com estrutura:
     *   - cdibge (required): Código IBGE do município
     *   - desmun (required): Nome do município
     *   - desest (required): UF do município
     *   - cod_mun (optional): Código Progress do município
     *   - cod_est (optional): Código Progress do estado
     *
     * @return array Array de resultados com estrutura:
     *   - codigo_ibge: Código IBGE
     *   - nome_municipio: Nome do município
     *   - uf: UF
     *   - coordenadas: null|array{lat: float, lon: float, fonte: string, cached: bool}
     */
    public function getCoordenadasLote(array $municipios): array
    {
        // CORREÇÃO BUG #69: Validação de max limit para prevenir DoS
        if (count($municipios) > 100) {
            Log::warning('getCoordenadasLote: Array muito grande (DoS prevention)', [
                'size' => count($municipios),
                'max_allowed' => 100
            ]);
            throw new \Exception('Máximo de 100 municípios permitidos por requisição');
        }

        // Validação de entrada: verificar se array não está vazio
        if (empty($municipios)) {
            Log::warning('getCoordenadasLote chamado com array vazio');
            return [];
        }

        $resultado = [];

        foreach ($municipios as $index => $municipio) {
            // Validação de campos obrigatórios
            if (!isset($municipio['cdibge']) || !isset($municipio['desmun']) || !isset($municipio['desest'])) {
                Log::warning('Município com dados incompletos no índice ' . $index, [
                    'municipio' => $municipio,
                    'campos_faltantes' => [
                        'cdibge' => !isset($municipio['cdibge']),
                        'desmun' => !isset($municipio['desmun']),
                        'desest' => !isset($municipio['desest']),
                    ]
                ]);

                // Adicionar ao resultado com coordenadas null (não quebra o frontend)
                $resultado[] = [
                    'codigo_ibge' => $municipio['cdibge'] ?? 'DESCONHECIDO',
                    'nome_municipio' => $municipio['desmun'] ?? 'DESCONHECIDO',
                    'uf' => $municipio['desest'] ?? 'DESCONHECIDO',
                    'coordenadas' => null
                ];

                continue;
            }

            $codigoIbge = (string) $municipio['cdibge'];
            $nomeMunicipio = trim($municipio['desmun']);
            $uf = trim($municipio['desest']);

            $coordenadas = $this->getCoordenadasByIbge($codigoIbge, $nomeMunicipio, $uf);

            // Se conseguiu coordenadas e tem cod_mun/cod_est, salvar no cache Progress também
            // IMPORTANTE: Wrapped em try/catch para não crashar todo o lote se falhar
            if ($coordenadas && isset($municipio['cod_mun']) && isset($municipio['cod_est'])) {
                try {
                    \App\Models\ProgressMunicipioGps::findOrCreateByProgress(
                        intval($municipio['cod_mun']),
                        intval($municipio['cod_est']),
                        [
                            'des_mun' => $nomeMunicipio,
                            'des_est' => $uf,
                            'cdibge' => $codigoIbge,
                            'latitude' => $coordenadas['lat'],
                            'longitude' => $coordenadas['lon'],
                            'fonte' => 'google',
                            'geocoded_at' => now(),
                        ]
                    );

                    Log::info('Coordenadas salvas no cache Progress', [
                        'cod_mun' => $municipio['cod_mun'],
                        'cod_est' => $municipio['cod_est'],
                        'municipio' => $nomeMunicipio
                    ]);
                } catch (\Exception $e) {
                    // Log do erro mas continua processando outros municípios
                    Log::error('Erro ao salvar coordenadas no cache Progress (não crítico, continuando)', [
                        'cod_mun' => $municipio['cod_mun'],
                        'cod_est' => $municipio['cod_est'],
                        'municipio' => $nomeMunicipio,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            $resultado[] = [
                'codigo_ibge' => $codigoIbge,
                'nome_municipio' => $nomeMunicipio,
                'uf' => $uf,
                'coordenadas' => $coordenadas
            ];

            // CORREÇÃO BUG IMPORTANTE #1: Rate limiting global sincronizado (fix race condition)
            // Ordem correta: 1) Verificar limite, 2) Aguardar se necessário, 3) Registrar hit
            if ($coordenadas && !$coordenadas['cached']) {
                $key = 'google_geocoding_api';

                // Verificar se limite foi excedido ANTES de registrar hit
                if (RateLimiter::tooManyAttempts($key, 5)) {
                    usleep(200000); // 200ms backoff
                }

                // Registrar hit no rate limiter (máximo 5 req/segundo global)
                RateLimiter::hit($key, 1);
            }
        }

        return $resultado;
    }
}
