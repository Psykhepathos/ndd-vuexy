<?php

namespace App\Models\Progress;

use Illuminate\Database\Eloquent\Casts\Attribute;

class Motorista extends BaseProgressModel
{
    /**
     * Nome da tabela no Progress
     */
    protected $table = 'PUB.trnmot';
    
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
        'numrg',
        'orgrg',
        'exprg',
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
        'exprg' => 'date',
        'datnas' => 'date'
    ];
    
    /**
     * Campos ocultos na serialização (dados sensíveis)
     */
    protected $hidden = [
        'numhab',
        'venhab',
        'esthab',
        'cathab',
        'numrg',
        'orgrg',
        'exprg',
        'datnas',
        'codcnpjcpf'
    ];
    
    /**
     * Accessors e Mutators
     */
    
    /**
     * Accessor para nome completo (alias)
     */
    protected function nomeCompleto(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->nomtrn
        );
    }
    
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
                return "({$this->dddtel}) " . preg_replace('/(\d{4,5})(\d{4})/', '$1-$2', (string)$this->numtel);
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
                return "({$this->dddcel}) " . preg_replace('/(\d{4,5})(\d{4})/', '$1-$2', (string)$this->numcel);
            }
        );
    }
    
    /**
     * Accessor para tipo de motorista
     */
    public function getTipoMotoristaAttribute()
    {
        return $this->flgautonomo ? 'Autônomo' : 'Funcionário';
    }
    
    /**
     * Accessor para status da CNH
     */
    protected function statusCnh(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->venhab) {
                    return 'Não informado';
                }
                
                $hoje = now();
                $vencimento = $this->venhab;
                
                if ($vencimento < $hoje) {
                    return 'Vencida';
                }
                
                $diasRestantes = $hoje->diffInDays($vencimento, false);
                
                if ($diasRestantes <= 30) {
                    return 'Próximo ao vencimento';
                }
                
                return 'Válida';
            }
        );
    }
    
    /**
     * Accessor para idade
     */
    protected function idade(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->datnas) {
                    return null;
                }
                return now()->diffInYears($this->datnas);
            }
        );
    }
    
    /**
     * Relacionamentos
     */
    
    /**
     * Relacionamento com Transportador (se não for autônomo)
     */
    public function transportador()
    {
        return $this->belongsTo(Transporte::class, 'codtrn', 'codtrn');
    }
    
    /**
     * Relacionamento com CIOTs
     */
    public function ciots()
    {
        return $this->hasMany(Ciot::class, 'codmot', 'codtrn');
    }
    
    /**
     * Scopes
     */
    
    /**
     * Scope para motoristas ativos
     */
    public function scopeAtivos($query)
    {
        return $query->where('flgati', true);
    }
    
    /**
     * Scope para motoristas inativos
     */
    public function scopeInativos($query)
    {
        return $query->where('flgati', false);
    }
    
    /**
     * Scope para motoristas autônomos
     */
    public function scopeAutonomos($query)
    {
        return $query->where('flgautonomo', true);
    }
    
    /**
     * Scope para motoristas funcionários
     */
    public function scopeFuncionarios($query)
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
     * Scope para motoristas com CNH válida
     */
    public function scopeComCnhValida($query)
    {
        return $query->whereNotNull('venhab')
                    ->where('venhab', '>=', now());
    }
    
    /**
     * Scope para motoristas com CNH vencida
     */
    public function scopeComCnhVencida($query)
    {
        return $query->whereNotNull('venhab')
                    ->where('venhab', '<', now());
    }
    
    /**
     * Scope para motoristas com CNH próxima ao vencimento (30 dias)
     */
    public function scopeComCnhProximaVencimento($query)
    {
        return $query->whereNotNull('venhab')
                    ->whereBetween('venhab', [now(), now()->addDays(30)]);
    }
    
    /**
     * Scope para filtrar por categoria da CNH
     */
    public function scopePorCategoriaCnh($query, $categoria)
    {
        return $query->where('cathab', $categoria);
    }
}