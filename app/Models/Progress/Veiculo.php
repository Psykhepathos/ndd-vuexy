<?php

namespace App\Models\Progress;

use Illuminate\Database\Eloquent\Casts\Attribute;

class Veiculo extends BaseProgressModel
{
    /**
     * Nome da tabela no Progress
     * Nota: Assumindo que existe uma tabela de veículos separada
     * Se os veículos estão na tabela transporte, usar essa para referência
     */
    protected $table = 'PUB.veiculo';
    
    /**
     * Chave primária
     */
    protected $primaryKey = 'codvei';
    
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
        'codvei',
        'codtrn', // FK para transportador
        'numpla',
        'desvei',
        'fabmod',
        'marvei',
        'corvei',
        'ufvei',
        'renavam',
        'numcha',
        'tipcam',
        'natcam',
        'flgati',
        'pesmax',
        'volmax',
        'altmax',
        'larmax',
        'commax',
        'placar',
        'placar2',
        'rencar',
        'rencar2',
        'chacar',
        'chacar2',
        'ufcar',
        'ufcar2'
    ];
    
    /**
     * Campos que devem ser convertidos para tipos nativos
     */
    protected $casts = [
        'codvei' => 'integer',
        'codtrn' => 'integer',
        'tipcam' => 'integer',
        'flgati' => 'boolean',
        'pesmax' => 'decimal:2',
        'volmax' => 'decimal:2',
        'altmax' => 'decimal:2',
        'larmax' => 'decimal:2',
        'commax' => 'decimal:2',
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
     * Accessors
     */
    
    /**
     * Accessor para placa formatada
     */
    protected function placaFormatada(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->numpla) {
                    return null;
                }
                
                $placa = strtoupper($this->numpla);
                
                // Formato Mercosul (ABC1D23) ou antigo (ABC-1234)
                if (preg_match('/^[A-Z]{3}[0-9][A-Z][0-9]{2}$/', $placa)) {
                    return substr($placa, 0, 3) . '-' . substr($placa, 3);
                } elseif (preg_match('/^[A-Z]{3}[0-9]{4}$/', $placa)) {
                    return substr($placa, 0, 3) . '-' . substr($placa, 3);
                }
                
                return $placa;
            }
        );
    }
    
    /**
     * Accessor para tipo de veículo
     */
    protected function tipoVeiculo(): Attribute
    {
        return Attribute::make(
            get: function () {
                return match($this->natcam) {
                    'T' => 'Transporte',
                    'A' => 'Apoio',
                    'F' => 'Frota',
                    default => 'Não informado'
                };
            }
        );
    }
    
    /**
     * Accessor para capacidade formatada
     */
    protected function capacidadeFormatada(): Attribute
    {
        return Attribute::make(
            get: function () {
                $capacidade = [];
                
                if ($this->pesmax) {
                    $capacidade[] = "Peso: {$this->pesmax}t";
                }
                
                if ($this->volmax) {
                    $capacidade[] = "Volume: {$this->volmax}m³";
                }
                
                if ($this->altmax || $this->larmax || $this->commax) {
                    $dimensoes = [];
                    if ($this->commax) $dimensoes[] = $this->commax . 'm';
                    if ($this->larmax) $dimensoes[] = $this->larmax . 'm';
                    if ($this->altmax) $dimensoes[] = $this->altmax . 'm';
                    
                    if (!empty($dimensoes)) {
                        $capacidade[] = 'Dim: ' . implode(' x ', $dimensoes);
                    }
                }
                
                return implode(' | ', $capacidade) ?: 'Não informado';
            }
        );
    }
    
    /**
     * Scopes
     */
    
    /**
     * Scope para veículos por placa
     */
    public function scopePorPlaca($query, $placa)
    {
        return $query->where('numpla', 'LIKE', "%{$placa}%");
    }
    
    /**
     * Scope para veículos por transportador
     */
    public function scopeDoTransportador($query, $codigoTransportador)
    {
        return $query->where('codtrn', $codigoTransportador);
    }
    
    /**
     * Scope para veículos por tipo
     */
    public function scopePorTipo($query, $tipo)
    {
        return $query->where('tipcam', $tipo);
    }
    
    /**
     * Scope para veículos por natureza
     */
    public function scopePorNatureza($query, $natureza)
    {
        return $query->where('natcam', $natureza);
    }
    
    /**
     * Scope para veículos com carreta
     */
    public function scopeComCarreta($query)
    {
        return $query->whereNotNull('placar')
                    ->where('placar', '!=', '');
    }
}