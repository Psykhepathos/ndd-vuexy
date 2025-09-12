<?php

namespace App\Models\Progress;

use Illuminate\Database\Eloquent\Casts\Attribute;

class Transporte extends BaseProgressModel
{
    /**
     * Nome da tabela no Progress
     */
    protected $table = 'PUB.transporte';
    
    /**
     * Chave primária
     */
    protected $primaryKey = 'codtrn';
    
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
        'codtrn',
        'nomtrn',
        'codcnpjcpf',
        'flgautonomo',
        'flgati',
        'desend',
        'numend',
        'cplend',
        'tiplog',
        'numceptrn',
        'numtel',
        'dddtel',
        'numcel',
        'dddcel',
        'e-mail',
        'numpla',
        'natcam',
        'tipcam',
        'indcd',
        'numhab',
        'venhab',
        'esthab',
        'cathab',
        'datnas'
    ];
    
    /**
     * Campos que devem ser convertidos para tipos nativos
     */
    protected $casts = [
        'codtrn' => 'integer',
        'flgautonomo' => 'boolean',
        'flgati' => 'boolean',
        'numceptrn' => 'integer',
        'numtel' => 'integer',
        'dddtel' => 'integer',
        'numcel' => 'integer',
        'dddcel' => 'integer',
        'tipcam' => 'integer',
        'venhab' => 'date',
        'datnas' => 'date'
    ];
    
    /**
     * Campos ocultos na serialização
     */
    protected $hidden = [
        'numhab',
        'venhab',
        'esthab',
        'cathab',
        'datnas'
    ];
    
    /**
     * Accessors e Mutators
     */
    
    /**
     * Accessor para telefone formatado
     */
    protected function telefoneFormatado(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->dddtel || !$this->numtel) {
                    return null;
                }
                return $this->formatTelefone($this->dddtel, $this->numtel);
            }
        );
    }
    
    /**
     * Accessor para celular formatado
     */
    protected function celularFormatado(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->dddcel || !$this->numcel) {
                    return null;
                }
                return $this->formatTelefone($this->dddcel, $this->numcel);
            }
        );
    }
    
    /**
     * Accessor para nome em uppercase
     */
    protected function nomeTransportador(): Attribute
    {
        return Attribute::make(
            get: fn () => strtoupper($this->nomtrn)
        );
    }
    
    /**
     * Accessor para tipo de transportador
     */
    protected function tipoTransportador(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->flgautonomo ? 'AUTÔNOMO' : 'EMPRESA'
        );
    }
    
    /**
     * Accessor para status ativo/inativo
     */
    protected function statusAtivo(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->flgati ? 'ATIVO' : 'INATIVO'
        );
    }
    
    /**
     * Accessor para endereço completo
     */
    protected function enderecoCompleto(): Attribute
    {
        return Attribute::make(
            get: function () {
                $endereco = strtoupper($this->desend);
                if ($this->numend) {
                    $endereco .= ", {$this->numend}";
                }
                if ($this->cplend) {
                    $endereco .= ", " . strtoupper($this->cplend);
                }
                return $endereco;
            }
        );
    }
    
    /**
     * Relacionamentos
     */
    
    /**
     * Accessor para placa formatada como placa brasileira
     */
    protected function placaFormatada(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->formatPlacaBrasileira($this->numpla);
            }
        );
    }
    
    /**
     * Accessor para placa com estilo visual
     */
    protected function placaEstilizada(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->formatPlacaBrasileira($this->numpla, true);
            }
        );
    }
    
    /**
     * Relacionamento com Motoristas
     */
    public function motoristas()
    {
        return $this->hasMany(Motorista::class, 'codtrn', 'codtrn');
    }
    
    /**
     * Relacionamento com Veículos
     */
    public function veiculos()
    {
        return $this->hasMany(Veiculo::class, 'codtrn', 'codtrn');
    }
    
    /**
     * Relacionamento com CIOTs
     */
    public function ciots()
    {
        return $this->hasMany(Ciot::class, 'codtrn', 'codtrn');
    }
    
    /**
     * Scopes
     */
    
    /**
     * Scope para transportadores ativos
     */
    public function scopeAtivos($query)
    {
        return $query->where('flgati', true);
    }
    
    /**
     * Scope para transportadores inativos
     */
    public function scopeInativos($query)
    {
        return $query->where('flgati', false);
    }
    
    /**
     * Scope para transportadores autônomos
     */
    public function scopeAutonomos($query)
    {
        return $query->where('flgautonomo', true);
    }
    
    /**
     * Scope para empresas transportadoras
     */
    public function scopeEmpresas($query)
    {
        return $query->where('flgautonomo', false);
    }
    
    /**
     * Scope para busca por nome ou código
     */
    public function scopeBuscar($query, $termo)
    {
        if (empty($termo)) {
            return $query;
        }
        
        if (is_numeric($termo)) {
            return $query->where('codtrn', $termo);
        }
        
        return $query->where('nomtrn', 'LIKE', "{$termo}%");
    }
    
    /**
     * Scope para filtrar por natureza do transporte
     */
    public function scopePorNatureza($query, $natureza)
    {
        if (empty($natureza)) {
            return $query;
        }
        
        return $query->where('natcam', $natureza);
    }
    
    /**
     * Scope para transportadores que fazem transporte de CD
     */
    public function scopeComCD($query)
    {
        return $query->where('indcd', 'S');
    }
}