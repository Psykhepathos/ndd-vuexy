<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Motorista extends Model
{
    use HasFactory;

    protected $fillable = [
        'codigo_progress',
        'nome',
        'cpf',
        'cnh',
        'vencimento_cnh',
        'telefone',
        'email',
        'status',
        'dados_progress'
    ];

    protected $casts = [
        'vencimento_cnh' => 'date',
        'dados_progress' => 'array'
    ];

    /**
     * Relacionamento com Vale Pedágio
     */
    public function valePedagios(): HasMany
    {
        return $this->hasMany(ValePedagio::class, 'motorista_id');
    }

    /**
     * Relacionamento com CIOT
     */
    public function ciots(): HasMany
    {
        return $this->hasMany(Ciot::class, 'motorista_id');
    }

    /**
     * Relacionamento com Veículos (através de vale pedágio)
     */
    public function veiculos(): HasMany
    {
        return $this->hasMany(Veiculo::class, 'motorista_id');
    }

    /**
     * Scopes para consultas
     */
    public function scopeAtivo($query)
    {
        return $query->where('status', 'ativo');
    }

    public function scopePorCodigoProgress($query, $codigo)
    {
        return $query->where('codigo_progress', $codigo);
    }

    /**
     * Retorna dados formatados para API
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'codigo_progress' => $this->codigo_progress,
            'nome' => $this->nome,
            'cpf' => $this->cpf,
            'cnh' => $this->cnh,
            'telefone' => $this->telefone,
            'email' => $this->email,
            'data_nascimento' => $this->data_nascimento?->format('Y-m-d'),
            'endereco' => $this->endereco,
            'cidade' => $this->cidade,
            'uf' => $this->uf,
            'cep' => $this->cep,
            'status' => $this->status,
            'observacoes' => $this->observacoes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}