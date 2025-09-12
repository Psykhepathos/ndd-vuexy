<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ciot extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'codigo_progress',
        'numero_ciot',
        'motorista_id',
        'veiculo_id',
        'contratante',
        'cpf_cnpj_contratante',
        'valor_frete',
        'origem',
        'destino',
        'descricao_carga',
        'peso_carga',
        'data_emissao',
        'data_vencimento',
        'status',
        'dados_progress'
    ];

    protected $casts = [
        'valor_frete' => 'decimal:2',
        'peso_carga' => 'decimal:2',
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

    public function cancelar(string $motivo = null): bool
    {
        $this->status = 'cancelado';
        $dadosProgress = $this->dados_progress ?? [];
        $dadosProgress['motivo_cancelamento'] = $motivo;
        $dadosProgress['data_cancelamento'] = now();
        $this->dados_progress = $dadosProgress;
        
        return $this->save();
    }

    public function isCancelado(): bool
    {
        return $this->status === 'cancelado';
    }

    public function scopeAtivos($query)
    {
        return $query->where('status', 'ativo');
    }

    public function scopeCancelados($query)
    {
        return $query->where('status', 'cancelado');
    }
}