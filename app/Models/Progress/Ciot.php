<?php

namespace App\Models\Progress;

use Illuminate\Database\Eloquent\Casts\Attribute;

class Ciot extends BaseProgressModel
{
    /**
     * Nome da tabela no Progress
     */
    protected $table = 'PUB.ciot';
    
    /**
     * Chave primária
     */
    protected $primaryKey = 'codciot';
    
    /**
     * Indica se a chave primária é auto-incrementável
     */
    public $incrementing = true;
    
    /**
     * Tipo da chave primária
     */
    protected $keyType = 'int';
    
    /**
     * Campos que podem ser preenchidos em massa
     */
    protected $fillable = [
        'codciot',
        'numciot',
        'codtrn',
        'codmot',
        'numpla',
        'origem',
        'destino',
        'datcri',
        'datini',
        'datfim',
        'vlrfrete',
        'status',
        'observacoes',
        'flgati'
    ];
    
    /**
     * Campos que devem ser convertidos para tipos nativos
     */
    protected $casts = [
        'codciot' => 'integer',
        'codtrn' => 'integer',
        'codmot' => 'integer',
        'datcri' => 'date',
        'datini' => 'date',
        'datfim' => 'date',
        'vlrfrete' => 'decimal:2',
        'flgati' => 'boolean',
    ];
    
    /**
     * Relacionamentos
     */
    
    /**
     * Relacionamento com Transportador
     */
    public function transportador()
    {
        return $this->belongsTo(Transporte::class, 'codtrn', 'codtrn');
    }
    
    /**
     * Relacionamento com Motorista
     */
    public function motorista()
    {
        return $this->belongsTo(Motorista::class, 'codmot', 'codtrn');
    }
    
    /**
     * Accessors
     */
    
    /**
     * Accessor para valor do frete formatado
     */
    protected function freteFormatado(): Attribute
    {
        return Attribute::make(
            get: fn () => 'R$ ' . number_format($this->vlrfrete, 2, ',', '.')
        );
    }
    
    /**
     * Accessor para rota completa
     */
    protected function rotaCompleta(): Attribute
    {
        return Attribute::make(
            get: fn () => "{$this->origem} → {$this->destino}"
        );
    }
    
    /**
     * Accessor para status formatado
     */
    protected function statusFormatado(): Attribute
    {
        return Attribute::make(
            get: function () {
                return match($this->status) {
                    'A' => 'Aguardando',
                    'I' => 'Iniciado',
                    'T' => 'Em trânsito',
                    'F' => 'Finalizado',
                    'C' => 'Cancelado',
                    default => 'Desconhecido'
                };
            }
        );
    }
    
    /**
     * Accessor para duração da viagem
     */
    protected function duracaoViagem(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->datini || !$this->datfim) {
                    return null;
                }
                
                $inicio = $this->datini;
                $fim = $this->datfim;
                
                $dias = $inicio->diffInDays($fim);
                
                return $dias === 1 ? '1 dia' : "{$dias} dias";
            }
        );
    }
    
    /**
     * Scopes
     */
    
    /**
     * Scope para CIOTs por transportador
     */
    public function scopeDoTransportador($query, $codigoTransportador)
    {
        return $query->where('codtrn', $codigoTransportador);
    }
    
    /**
     * Scope para CIOTs por motorista
     */
    public function scopeDoMotorista($query, $codigoMotorista)
    {
        return $query->where('codmot', $codigoMotorista);
    }
    
    /**
     * Scope para CIOTs por período
     */
    public function scopePorPeriodo($query, $dataInicio = null, $dataFim = null)
    {
        if ($dataInicio) {
            $query->where('datini', '>=', $dataInicio);
        }
        
        if ($dataFim) {
            $query->where('datfim', '<=', $dataFim);
        }
        
        return $query;
    }
    
    /**
     * Scope para CIOTs por status
     */
    public function scopePorStatus($query, $status)
    {
        return $query->where('status', $status);
    }
    
    /**
     * Scope para CIOTs em andamento
     */
    public function scopeEmAndamento($query)
    {
        return $query->whereIn('status', ['I', 'T']);
    }
    
    /**
     * Scope para CIOTs finalizados
     */
    public function scopeFinalizados($query)
    {
        return $query->where('status', 'F');
    }
    
    /**
     * Scope para CIOTs por rota
     */
    public function scopePorRota($query, $origem = null, $destino = null)
    {
        if ($origem) {
            $query->where('origem', 'LIKE', "%{$origem}%");
        }
        
        if ($destino) {
            $query->where('destino', 'LIKE', "%{$destino}%");
        }
        
        return $query;
    }
    
    /**
     * Scope para CIOTs por placa
     */
    public function scopePorPlaca($query, $placa)
    {
        return $query->where('numpla', 'LIKE', "%{$placa}%");
    }
    
    /**
     * Scope para CIOTs por número CIOT
     */
    public function scopePorNumeroCiot($query, $numero)
    {
        return $query->where('numciot', 'LIKE', "%{$numero}%");
    }
}