<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ValePedagio extends Model
{
    protected $table = 'vale_pedagios';
    
    protected $fillable = [
        'codigo_progress',
        'motorista_id',
        'veiculo_id',
        'valor',
        'origem',
        'destino',
        'data_emissao',
        'data_vencimento',
        'status',
        'dados_progress'
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'data_emissao' => 'date',
        'data_vencimento' => 'date',
        'dados_progress' => 'array'
    ];

    public function motorista(): BelongsTo
    {
        return $this->belongsTo(Motorista::class);
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }
}