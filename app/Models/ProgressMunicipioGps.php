<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cache de coordenadas GPS para municípios do Progress
 *
 * Evita chamadas repetidas ao Google Geocoding API
 * Relaciona municípios do Progress (cod_mun, cod_est) com suas coordenadas
 *
 * @property int $id
 * @property int $cod_mun Código do município no Progress
 * @property int $cod_est Código do estado no Progress
 * @property string $des_mun Nome do município
 * @property string $des_est Nome do estado
 * @property string|null $sigla_est UF (SP, RJ, etc)
 * @property string|null $cdibge Código IBGE
 * @property float|null $latitude Latitude (-90 a 90)
 * @property float|null $longitude Longitude (-180 a 180)
 * @property string $fonte Origem: google, manual, progress, ibge
 * @property int|null $precisao Precisão do Google (0-9)
 * @property \Carbon\Carbon|null $geocoded_at Data do último geocoding
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class ProgressMunicipioGps extends Model
{
    protected $table = 'progress_municipios_gps';

    protected $fillable = [
        'cod_mun',
        'cod_est',
        'des_mun',
        'des_est',
        'sigla_est',
        'cdibge',
        'latitude',
        'longitude',
        'fonte',
        'precisao',
        'geocoded_at',
    ];

    protected $casts = [
        'cod_mun' => 'integer',
        'cod_est' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
        'precisao' => 'integer',
        'geocoded_at' => 'datetime',
    ];

    /**
     * Busca município por código Progress
     */
    public static function findByProgress(int $codMun, int $codEst): ?self
    {
        return self::where('cod_mun', $codMun)
            ->where('cod_est', $codEst)
            ->first();
    }

    /**
     * Busca município por código IBGE
     */
    public static function findByIBGE(string $cdibge): ?self
    {
        return self::where('cdibge', $cdibge)->first();
    }

    /**
     * Busca ou cria município
     */
    public static function findOrCreateByProgress(int $codMun, int $codEst, array $data = []): self
    {
        $municipio = self::findByProgress($codMun, $codEst);

        if ($municipio) {
            // Atualizar se dados fornecidos
            if (!empty($data)) {
                $municipio->update($data);
            }
            return $municipio;
        }

        // Criar novo
        return self::create(array_merge([
            'cod_mun' => $codMun,
            'cod_est' => $codEst,
        ], $data));
    }

    /**
     * Verifica se tem coordenadas válidas
     */
    public function hasValidCoordinates(): bool
    {
        return $this->latitude !== null
            && $this->longitude !== null
            && $this->latitude >= -90
            && $this->latitude <= 90
            && $this->longitude >= -180
            && $this->longitude <= 180;
    }

    /**
     * Atualiza coordenadas do Google
     */
    public function updateFromGoogle(float $lat, float $lng, int $precisao = null): self
    {
        $this->update([
            'latitude' => $lat,
            'longitude' => $lng,
            'fonte' => 'google',
            'precisao' => $precisao,
            'geocoded_at' => now(),
        ]);

        return $this;
    }

    /**
     * Scope: Apenas com coordenadas
     */
    public function scopeWithCoordinates($query)
    {
        return $query->whereNotNull('latitude')
            ->whereNotNull('longitude');
    }

    /**
     * Scope: Sem coordenadas
     */
    public function scopeWithoutCoordinates($query)
    {
        return $query->whereNull('latitude')
            ->orWhereNull('longitude');
    }

    /**
     * Scope: Por fonte
     */
    public function scopeByFonte($query, string $fonte)
    {
        return $query->where('fonte', $fonte);
    }
}
