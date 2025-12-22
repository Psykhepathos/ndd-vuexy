<?php

namespace App\Services;

use App\Models\PracaPedagio;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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

        // Tenta match por rodovia + km (mais preciso)
        if ($rodovia && $km !== null) {
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
