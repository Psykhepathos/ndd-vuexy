<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PracaPedagio extends Model
{
    protected $table = 'pracas_pedagio';

    protected $fillable = [
        'concessionaria',
        'praca',
        'rodovia',
        'uf',
        'km',
        'municipio',
        'ano_pnv',
        'tipo_pista',
        'sentido',
        'situacao',
        'data_inativacao',
        'latitude',
        'longitude',
        'fonte',
        'data_importacao'
    ];

    protected $casts = [
        'km' => 'decimal:3',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'data_inativacao' => 'date',
        'data_importacao' => 'date',
        'ano_pnv' => 'integer'
    ];

    // Scopes
    public function scopeAtivas($query)
    {
        return $query->where('situacao', 'Ativo');
    }

    public function scopePorRodovia($query, $rodovia)
    {
        return $query->where('rodovia', $rodovia);
    }

    public function scopePorUf($query, $uf)
    {
        return $query->where('uf', $uf);
    }

    public function scopeProximasDe($query, $lat, $lon, $raioKm = 50)
    {
        // Busca praças próximas usando Haversine formula
        // 1 grau ≈ 111km
        $delta = $raioKm / 111;

        return $query->whereBetween('latitude', [$lat - $delta, $lat + $delta])
                     ->whereBetween('longitude', [$lon - $delta, $lon + $delta]);
    }
}
