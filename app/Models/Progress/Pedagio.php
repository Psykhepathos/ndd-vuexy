<?php

namespace App\Models\Progress;

use Illuminate\Database\Eloquent\Casts\Attribute;

class Pedagio extends BaseProgressModel
{
    /**
     * Nome da tabela no Progress
     */
    protected $table = 'PUB.pedagio';
    
    /**
     * Chave primária
     */
    protected $primaryKey = 'codped';
    
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
        'codped',
        'nomped',
        'vlrped',
        'km',
        'cidade',
        'uf',
        'rodovia',
        'sentido',
        'flgati',
        'concessionaria',
        'tipocobranca'
    ];
    
    /**
     * Campos que devem ser convertidos para tipos nativos
     */
    protected $casts = [
        'codped' => 'integer',
        'vlrped' => 'decimal:2',
        'km' => 'decimal:3',
        'flgati' => 'boolean',
    ];
    
    /**
     * Accessors
     */
    
    /**
     * Accessor para valor formatado
     */
    protected function valorFormatado(): Attribute
    {
        return Attribute::make(
            get: fn () => 'R$ ' . number_format($this->vlrped, 2, ',', '.')
        );
    }
    
    /**
     * Accessor para localização completa
     */
    protected function localizacaoCompleta(): Attribute
    {
        return Attribute::make(
            get: function () {
                $localizacao = [];
                
                if ($this->rodovia) {
                    $localizacao[] = $this->rodovia;
                }
                
                if ($this->km) {
                    $localizacao[] = "KM {$this->km}";
                }
                
                if ($this->cidade && $this->uf) {
                    $localizacao[] = "{$this->cidade}/{$this->uf}";
                }
                
                return implode(' - ', $localizacao);
            }
        );
    }
    
    /**
     * Scopes
     */
    
    /**
     * Scope para pedágios por rodovia
     */
    public function scopePorRodovia($query, $rodovia)
    {
        return $query->where('rodovia', 'LIKE', "%{$rodovia}%");
    }
    
    /**
     * Scope para pedágios por UF
     */
    public function scopePorUf($query, $uf)
    {
        return $query->where('uf', $uf);
    }
    
    /**
     * Scope para pedágios por cidade
     */
    public function scopePorCidade($query, $cidade)
    {
        return $query->where('cidade', 'LIKE', "%{$cidade}%");
    }
    
    /**
     * Scope para pedágios por faixa de valor
     */
    public function scopePorFaixaValor($query, $valorMinimo = null, $valorMaximo = null)
    {
        if ($valorMinimo) {
            $query->where('vlrped', '>=', $valorMinimo);
        }
        
        if ($valorMaximo) {
            $query->where('vlrped', '<=', $valorMaximo);
        }
        
        return $query;
    }
    
    /**
     * Scope para pedágios por concessionária
     */
    public function scopePorConcessionaria($query, $concessionaria)
    {
        return $query->where('concessionaria', 'LIKE', "%{$concessionaria}%");
    }
}