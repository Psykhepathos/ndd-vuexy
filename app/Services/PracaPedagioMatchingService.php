<?php

namespace App\Services;

use App\Models\PracaPedagio;
use Illuminate\Support\Facades\Log;

/**
 * Service para fazer matching entre pracas de pedagio do SemParar/NDD
 * e os dados da ANTT (com coordenadas)
 */
class PracaPedagioMatchingService
{
    /**
     * Faz matching das pracas retornadas pelo SemParar com dados da ANTT
     * para adicionar coordenadas (latitude/longitude)
     *
     * @param array $pracasSemParar Array de pracas retornadas pelo SemParar
     *        Formato: [['id' => 1, 'praca' => 'Nome', 'rodovia' => 'BR-381', 'km' => 500, ...]]
     * @return array Pracas com coordenadas adicionadas
     */
    public function matchPracasComANTT(array $pracasSemParar): array
    {
        $resultado = [];
        $totalMatched = 0;
        $totalUnmatched = 0;

        foreach ($pracasSemParar as $praca) {
            $pracaEnriquecida = $this->enrichPracaWithCoordinates($praca);
            $resultado[] = $pracaEnriquecida;

            if (!empty($pracaEnriquecida['lat']) && !empty($pracaEnriquecida['lon'])) {
                $totalMatched++;
            } else {
                $totalUnmatched++;
            }
        }

        Log::info('[PracaMatching] Matching concluido', [
            'total' => count($pracasSemParar),
            'matched' => $totalMatched,
            'unmatched' => $totalUnmatched
        ]);

        return $resultado;
    }

    /**
     * Enriquece uma praca com coordenadas da ANTT
     *
     * @param array $praca Praca do SemParar
     * @return array Praca com coordenadas
     */
    protected function enrichPracaWithCoordinates(array $praca): array
    {
        $rodovia = $praca['rodovia'] ?? null;
        $km = $praca['km'] ?? null;
        $nome = $praca['praca'] ?? '';

        // SemParar às vezes retorna km=0, mas o km está no nome da praça
        // Ex: "BR-040, KM487,268, NORTE, CAPIM BRANCO" -> km=487.268
        if (($km === null || $km == 0) && $nome) {
            $kmExtraido = $this->extractKmFromName($nome);
            if ($kmExtraido !== null) {
                $km = $kmExtraido;
                Log::debug('[PracaMatching] KM extraído do nome', [
                    'nome' => $nome,
                    'km_extraido' => $km
                ]);
            }
        }

        // Tenta match por rodovia + km (mais preciso)
        if ($rodovia && $km !== null && $km > 0) {
            $matched = $this->matchByRodoviaKm($rodovia, $km);
            if ($matched) {
                return $this->mergeWithMatch($praca, $matched, false);
            }
        }

        // Fallback: tenta match por nome similar
        if ($nome) {
            $matched = $this->matchByNome($nome, $rodovia);
            if ($matched) {
                return $this->mergeWithMatch($praca, $matched, true);
            }
        }

        // Última tentativa: buscar por município extraído do nome
        $municipioExtraido = $this->extractMunicipioFromName($nome);
        if ($municipioExtraido && $rodovia) {
            $matched = $this->matchByMunicipio($municipioExtraido, $rodovia);
            if ($matched) {
                Log::debug('[PracaMatching] Match por município extraído', [
                    'nome' => $nome,
                    'municipio' => $municipioExtraido
                ]);
                return $this->mergeWithMatch($praca, $matched, true);
            }
        }

        // Última tentativa: geocodificar o município extraído usando Google Geocoding
        if ($municipioExtraido) {
            $geocoded = $this->geocodeMunicipio($municipioExtraido);
            if ($geocoded) {
                Log::debug('[PracaMatching] Match por geocoding do município', [
                    'nome' => $nome,
                    'municipio' => $municipioExtraido,
                    'lat' => $geocoded['latitude'],
                    'lon' => $geocoded['longitude']
                ]);

                return array_merge($praca, [
                    'lat' => (float) $geocoded['latitude'],
                    'lon' => (float) $geocoded['longitude'],
                    'nome' => $praca['praca'] ?? 'Pedágio',
                    'cidade' => $municipioExtraido,
                    'uf' => $geocoded['uf'] ?? '',
                    'valor' => $praca['valor'] ?? 0,
                    'match_incerto' => true,
                    'match_source' => 'geocoding'
                ]);
            }
        }

        // Sem match - retorna praca original sem coordenadas
        // Normaliza campos para evitar erros no frontend
        Log::debug('[PracaMatching] Sem match para praca', [
            'praca' => $nome,
            'rodovia' => $rodovia,
            'km' => $km
        ]);

        return array_merge($praca, [
            'lat' => null,
            'lon' => null,
            'nome' => $praca['praca'] ?? 'Pedágio',
            'cidade' => '',
            'uf' => '',
            'valor' => $praca['valor'] ?? 0,
            'match_incerto' => true,
            'match_source' => 'none'
        ]);
    }

    /**
     * Geocodifica um município para obter coordenadas aproximadas
     * Usa o cache de município_coordenadas primeiro, depois Google Geocoding API
     */
    protected function geocodeMunicipio(string $municipio): ?array
    {
        try {
            // Buscar no cache de municípios (MunicipioCoordenada)
            $cached = \App\Models\MunicipioCoordenada::where(function ($query) use ($municipio) {
                $munNorm = strtoupper(trim($municipio));
                $query->whereRaw('UPPER(nome_municipio) = ?', [$munNorm])
                      ->orWhereRaw('UPPER(nome_municipio) LIKE ?', ['%' . $munNorm . '%']);
            })->first();

            if ($cached && $cached->latitude && $cached->longitude) {
                Log::debug('[PracaMatching] Município encontrado no cache', [
                    'municipio' => $municipio,
                    'lat' => $cached->latitude,
                    'lon' => $cached->longitude
                ]);
                return [
                    'latitude' => $cached->latitude,
                    'longitude' => $cached->longitude,
                    'uf' => $cached->uf ?? ''
                ];
            }

            // Fallback: chamar Google Geocoding API diretamente
            $apiKey = config('services.google_maps.api_key');
            if (!$apiKey) {
                Log::warning('[PracaMatching] Google Maps API Key não configurada');
                return null;
            }

            $address = $municipio . ', Brasil';
            $response = \Illuminate\Support\Facades\Http::timeout(10)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => $address,
                'key' => $apiKey,
                'region' => 'br',
                'language' => 'pt-BR'
            ]);

            if (!$response->successful()) {
                Log::warning('[PracaMatching] Erro na requisição Google Geocoding', [
                    'municipio' => $municipio,
                    'status' => $response->status()
                ]);
                return null;
            }

            $data = $response->json();

            if ($data['status'] === 'OK' && count($data['results']) > 0) {
                $location = $data['results'][0]['geometry']['location'];

                Log::info('[PracaMatching] Geocoding via Google bem-sucedido', [
                    'municipio' => $municipio,
                    'lat' => $location['lat'],
                    'lng' => $location['lng']
                ]);

                return [
                    'latitude' => $location['lat'],
                    'longitude' => $location['lng'],
                    'uf' => ''
                ];
            }

            Log::warning('[PracaMatching] Google Geocoding não encontrou resultados', [
                'municipio' => $municipio,
                'status' => $data['status'] ?? 'unknown'
            ]);

            return null;
        } catch (\Exception $e) {
            Log::warning('[PracaMatching] Erro ao geocodificar município', [
                'municipio' => $municipio,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Extrai o km do nome da praça SemParar
     * Ex: "BR-040, KM487,268, NORTE, CAPIM BRANCO" -> 487.268
     * Ex: "ALEXÂNIA KM 43 SUL" -> 43
     */
    protected function extractKmFromName(string $nome): ?float
    {
        // Padrão 1: "KM487,268" ou "KM 487.268" ou "KM487"
        if (preg_match('/KM\s*(\d+)[,.]?(\d*)/i', $nome, $matches)) {
            $kmInteiro = $matches[1];
            $kmDecimal = $matches[2] ?? '';
            if ($kmDecimal) {
                return floatval($kmInteiro . '.' . $kmDecimal);
            }
            return floatval($kmInteiro);
        }

        return null;
    }

    /**
     * Extrai o município do nome da praça SemParar
     * Ex: "BR-040, KM487,268, NORTE, CAPIM BRANCO" -> "CAPIM BRANCO"
     * Ex: "ALEXÂNIA KM 43 SUL" -> "ALEXÂNIA"
     */
    protected function extractMunicipioFromName(string $nome): ?string
    {
        // Padrão 1: Último elemento após vírgula (sem "NORTE", "SUL", "KM", etc)
        $partes = explode(',', $nome);
        if (count($partes) > 1) {
            // Pega a última parte que não seja direção ou KM
            for ($i = count($partes) - 1; $i >= 0; $i--) {
                $parte = trim($partes[$i]);
                // Ignorar direções e KM
                if (!preg_match('/^(NORTE|SUL|LESTE|OESTE|KM\s*\d+|BR[- ]\d+)$/i', $parte)) {
                    // Remove números do início/fim
                    $parte = preg_replace('/^\d+\s*|\s*\d+$/', '', $parte);
                    if (strlen($parte) > 2) {
                        return strtoupper(trim($parte));
                    }
                }
            }
        }

        // Padrão 2: Início do nome até "KM" (ex: "ALEXÂNIA KM 43 SUL")
        if (preg_match('/^([A-ZÀ-ÿ\s]+)\s+KM/i', $nome, $matches)) {
            return strtoupper(trim($matches[1]));
        }

        return null;
    }

    /**
     * Match por município na rodovia
     */
    protected function matchByMunicipio(string $municipio, string $rodovia): ?array
    {
        $rodoviaNorm = $this->normalizeRodovia($rodovia);
        $municipioNorm = $this->normalizeNome($municipio);

        $pracaANTT = PracaPedagio::where('situacao', 'Ativo')
            ->where(function ($query) use ($rodoviaNorm) {
                $query->whereRaw('UPPER(REPLACE(rodovia, " ", "")) = ?', [$rodoviaNorm])
                    ->orWhereRaw('UPPER(REPLACE(rodovia, " ", "")) = ?', ['BR-' . ltrim($rodoviaNorm, 'BR-')])
                    ->orWhereRaw('UPPER(REPLACE(rodovia, " ", "")) = ?', [ltrim($rodoviaNorm, 'BR-')]);
            })
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get()
            ->filter(function ($p) use ($municipioNorm) {
                $pracaMunNorm = $this->normalizeNome($p->municipio ?? '');
                // Match exato ou contém
                return $pracaMunNorm === $municipioNorm ||
                       str_contains($pracaMunNorm, $municipioNorm) ||
                       str_contains($municipioNorm, $pracaMunNorm);
            })
            ->first();

        if ($pracaANTT) {
            return [
                'latitude' => $pracaANTT->latitude,
                'longitude' => $pracaANTT->longitude,
                'praca_antt' => $pracaANTT->praca,
                'municipio' => $pracaANTT->municipio,
                'uf' => $pracaANTT->uf,
                'concessionaria' => $pracaANTT->concessionaria,
                'km_antt' => $pracaANTT->km
            ];
        }

        return null;
    }

    /**
     * Match por rodovia e km (tolerancia de 10km)
     */
    protected function matchByRodoviaKm(string $rodovia, $km): ?array
    {
        // Normalizar rodovia (remover espacos, uppercase)
        $rodoviaNorm = $this->normalizeRodovia($rodovia);
        $kmFloat = floatval($km);

        // Buscar com tolerancia de 10km
        $pracaANTT = PracaPedagio::where('situacao', 'Ativo')
            ->where(function ($query) use ($rodoviaNorm) {
                // Match exato ou com BR- prefixado
                $query->whereRaw('UPPER(REPLACE(rodovia, " ", "")) = ?', [$rodoviaNorm])
                    ->orWhereRaw('UPPER(REPLACE(rodovia, " ", "")) = ?', ['BR-' . ltrim($rodoviaNorm, 'BR-')])
                    ->orWhereRaw('UPPER(REPLACE(rodovia, " ", "")) = ?', [ltrim($rodoviaNorm, 'BR-')]);
            })
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get()
            ->filter(function ($p) use ($kmFloat) {
                // Filtrar por km com tolerancia de 10km
                $pracaKm = floatval($p->km);
                return abs($pracaKm - $kmFloat) <= 10;
            })
            ->sortBy(function ($p) use ($kmFloat) {
                // Ordenar pelo mais proximo
                return abs(floatval($p->km) - $kmFloat);
            })
            ->first();

        if ($pracaANTT) {
            return [
                'latitude' => $pracaANTT->latitude,
                'longitude' => $pracaANTT->longitude,
                'praca_antt' => $pracaANTT->praca,
                'municipio' => $pracaANTT->municipio,
                'uf' => $pracaANTT->uf,
                'concessionaria' => $pracaANTT->concessionaria,
                'km_antt' => $pracaANTT->km
            ];
        }

        return null;
    }

    /**
     * Match por nome similar (fuzzy matching)
     */
    protected function matchByNome(string $nome, ?string $rodovia = null): ?array
    {
        $nomeNorm = $this->normalizeNome($nome);

        $query = PracaPedagio::where('situacao', 'Ativo')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        // Se temos rodovia, filtrar por ela primeiro
        if ($rodovia) {
            $rodoviaNorm = $this->normalizeRodovia($rodovia);
            $query->where(function ($q) use ($rodoviaNorm) {
                $q->whereRaw('UPPER(REPLACE(rodovia, " ", "")) LIKE ?', ['%' . $rodoviaNorm . '%'])
                    ->orWhereRaw('UPPER(REPLACE(rodovia, " ", "")) LIKE ?', ['%' . ltrim($rodoviaNorm, 'BR-') . '%']);
            });
        }

        $pracas = $query->get();

        $melhorMatch = null;
        $melhorScore = 0;

        foreach ($pracas as $praca) {
            $pracaNomeNorm = $this->normalizeNome($praca->praca);

            // Calcular similaridade
            $score = $this->calculateSimilarity($nomeNorm, $pracaNomeNorm);

            if ($score > $melhorScore && $score >= 0.5) { // Minimo 50% similaridade
                $melhorScore = $score;
                $melhorMatch = $praca;
            }
        }

        if ($melhorMatch) {
            return [
                'latitude' => $melhorMatch->latitude,
                'longitude' => $melhorMatch->longitude,
                'praca_antt' => $melhorMatch->praca,
                'municipio' => $melhorMatch->municipio,
                'uf' => $melhorMatch->uf,
                'concessionaria' => $melhorMatch->concessionaria,
                'km_antt' => $melhorMatch->km,
                'match_score' => $melhorScore
            ];
        }

        return null;
    }

    /**
     * Normaliza nome de rodovia para comparacao
     */
    protected function normalizeRodovia(string $rodovia): string
    {
        // Remove espacos, uppercase
        $norm = strtoupper(str_replace(' ', '', trim($rodovia)));

        // Garantir formato BR-XXX
        if (preg_match('/^(\d{3})$/', $norm, $m)) {
            $norm = 'BR-' . $m[1];
        } elseif (preg_match('/^BR(\d{3})$/', $norm, $m)) {
            $norm = 'BR-' . $m[1];
        }

        return $norm;
    }

    /**
     * Normaliza nome de praca para comparacao
     */
    protected function normalizeNome(string $nome): string
    {
        // Uppercase, remove acentos, remove caracteres especiais
        $nome = strtoupper(trim($nome));
        $nome = $this->removeAcentos($nome);
        $nome = preg_replace('/[^A-Z0-9\s]/', '', $nome);
        $nome = preg_replace('/\s+/', ' ', $nome);

        return $nome;
    }

    /**
     * Remove acentos de string
     */
    protected function removeAcentos(string $str): string
    {
        $acentos = [
            'À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Æ', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï',
            'Ð', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ø', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'Þ', 'ß',
            'à', 'á', 'â', 'ã', 'ä', 'å', 'æ', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï',
            'ð', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ø', 'ù', 'ú', 'û', 'ü', 'ý', 'þ', 'ÿ'
        ];
        $semAcentos = [
            'A', 'A', 'A', 'A', 'A', 'A', 'AE', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I',
            'D', 'N', 'O', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y', 'TH', 'SS',
            'A', 'A', 'A', 'A', 'A', 'A', 'AE', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I',
            'D', 'N', 'O', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y', 'TH', 'Y'
        ];

        return str_replace($acentos, $semAcentos, $str);
    }

    /**
     * Calcula similaridade entre duas strings (0 a 1)
     */
    protected function calculateSimilarity(string $str1, string $str2): float
    {
        // Se iguais, 100%
        if ($str1 === $str2) {
            return 1.0;
        }

        // Usar similar_text do PHP
        similar_text($str1, $str2, $percent);

        // Tambem verificar se uma contem a outra
        if (str_contains($str1, $str2) || str_contains($str2, $str1)) {
            $percent = max($percent, 70); // Minimo 70% se uma contem a outra
        }

        return $percent / 100;
    }

    /**
     * Merge praca original com dados do match
     * Normaliza campos para o formato esperado pelo frontend
     */
    protected function mergeWithMatch(array $praca, array $match, bool $incerto): array
    {
        // Normalizar campos para o formato esperado pelo frontend
        // Frontend espera: nome, cidade, uf, lat, lon, valor
        return array_merge($praca, [
            // Coordenadas do match ANTT
            'lat' => (float) $match['latitude'],
            'lon' => (float) $match['longitude'],

            // Nome: usar campo 'praca' do SemParar ou 'praca_antt' se nao existir
            'nome' => $praca['praca'] ?? $match['praca_antt'] ?? 'Pedágio',

            // Cidade e UF do match ANTT
            'cidade' => $match['municipio'] ?? '',
            'uf' => $match['uf'] ?? '',

            // Valor: manter se existir, senao 0 (sera calculado depois)
            'valor' => $praca['valor'] ?? 0,

            // Metadados do matching
            'match_incerto' => $incerto,
            'match_source' => $incerto ? 'nome' : 'rodovia_km',
            'praca_antt' => $match['praca_antt'] ?? null,
            'concessionaria_antt' => $match['concessionaria'] ?? null
        ]);
    }

    /**
     * Busca todas as pracas proximas a uma coordenada (para debug/mapa)
     *
     * @param float $lat Latitude
     * @param float $lon Longitude
     * @param float $raioKm Raio em km
     * @return array
     */
    public function buscarPracasProximas(float $lat, float $lon, float $raioKm = 50): array
    {
        // Aproximacao: 1 grau = ~111km
        $deltaGraus = $raioKm / 111;

        return PracaPedagio::where('situacao', 'Ativo')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereBetween('latitude', [$lat - $deltaGraus, $lat + $deltaGraus])
            ->whereBetween('longitude', [$lon - $deltaGraus, $lon + $deltaGraus])
            ->get()
            ->map(function ($p) use ($lat, $lon) {
                $distancia = $this->calcularDistanciaKm($lat, $lon, $p->latitude, $p->longitude);
                return [
                    'id' => $p->id,
                    'praca' => $p->praca,
                    'rodovia' => $p->rodovia,
                    'km' => $p->km,
                    'municipio' => $p->municipio,
                    'uf' => $p->uf,
                    'lat' => (float) $p->latitude,
                    'lon' => (float) $p->longitude,
                    'distancia_km' => round($distancia, 2)
                ];
            })
            ->sortBy('distancia_km')
            ->values()
            ->toArray();
    }

    /**
     * Calcula distancia entre dois pontos em km (formula Haversine)
     */
    protected function calcularDistanciaKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
