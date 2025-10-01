<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MunicipioCoordenada extends Model
{
    protected $table = 'municipio_coordenadas';

    protected $fillable = [
        'codigo_ibge',
        'nome_municipio',
        'uf',
        'latitude',
        'longitude',
        'fonte'
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    /**
     * Busca coordenadas por código IBGE
     */
    public static function findByCodigoIbge(string $codigoIbge): ?self
    {
        return self::where('codigo_ibge', $codigoIbge)->first();
    }

    /**
     * Salva coordenadas de um município
     */
    public static function salvarCoordenadas(
        string $codigoIbge,
        string $nomeMunicipio,
        string $uf,
        float $latitude,
        float $longitude,
        string $fonte = 'google_geocoding'
    ): self {
        return self::updateOrCreate(
            ['codigo_ibge' => $codigoIbge],
            [
                'nome_municipio' => $nomeMunicipio,
                'uf' => $uf,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'fonte' => $fonte
            ]
        );
    }
}
