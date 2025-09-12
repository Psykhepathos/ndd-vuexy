<?php

namespace App\Models\Progress;

use Illuminate\Database\Eloquent\Model;

abstract class BaseProgressModel extends Model
{
    /**
     * Conexão com banco Progress via JDBC
     */
    protected $connection = 'progress';
    
    /**
     * Desabilita timestamps por padrão (Progress não usa created_at/updated_at)
     */
    public $timestamps = false;
    
    /**
     * Prefixo das tabelas Progress
     */
    protected $tablePrefix = 'PUB.';
    
    /**
     * Método helper para formatar placa brasileira com estilo
     */
    protected function formatPlacaBrasileira($placa, $withStyle = false)
    {
        if (!$placa) {
            return null;
        }
        
        $placa = strtoupper(preg_replace('/[^A-Z0-9]/', '', $placa));
        
        // Formato Mercosul (ABC1D23)
        if (preg_match('/^[A-Z]{3}[0-9][A-Z][0-9]{2}$/', $placa)) {
            $formatted = substr($placa, 0, 3) . '-' . substr($placa, 3, 1) . substr($placa, 4, 1) . substr($placa, 5, 2);
        }
        // Formato antigo (ABC1234)
        elseif (preg_match('/^[A-Z]{3}[0-9]{4}$/', $placa)) {
            $formatted = substr($placa, 0, 3) . '-' . substr($placa, 3);
        }
        else {
            $formatted = $placa;
        }
        
        if ($withStyle) {
            return [
                'text' => $formatted,
                'class' => 'placa-brasileira',
                'style' => 'background: #0066cc; color: white; padding: 2px 6px; border-radius: 3px; font-family: monospace; font-size: 11px; font-weight: bold; letter-spacing: 1px; white-space: nowrap;'
            ];
        }
        
        return $formatted;
    }
    
    /**
     * Método para executar queries customizadas no Progress
     */
    public static function executeProgressQuery(string $sql, array $bindings = [])
    {
        return static::getConnectionResolver()
            ->connection('progress')
            ->select($sql, $bindings);
    }
    
    /**
     * Método helper para formatar telefone brasileiro
     */
    protected function formatTelefone($ddd, $numero)
    {
        if (!$ddd || !$numero) {
            return null;
        }
        
        $numeroStr = (string)$numero;
        
        // Celular (9 dígitos) ou fixo (8 dígitos)
        if (strlen($numeroStr) === 9) {
            return "({$ddd}) " . preg_replace('/(\d{5})(\d{4})/', '$1-$2', $numeroStr);
        } elseif (strlen($numeroStr) === 8) {
            return "({$ddd}) " . preg_replace('/(\d{4})(\d{4})/', '$1-$2', $numeroStr);
        }
        
        return "({$ddd}) {$numeroStr}";
    }
    
    /**
     * Método helper para formatar CPF/CNPJ
     */
    protected function formatCpfCnpj($documento)
    {
        if (!$documento) {
            return null;
        }
        
        $documento = preg_replace('/\D/', '', $documento);
        
        if (strlen($documento) === 11) {
            // CPF
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $documento);
        } elseif (strlen($documento) === 14) {
            // CNPJ
            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $documento);
        }
        
        return $documento;
    }
    
    /**
     * Scope genérico para busca por código
     */
    public function scopePorCodigo($query, $codigo)
    {
        return $query->where($this->primaryKey, $codigo);
    }
    
    /**
     * Scope genérico para registros ativos
     */
    public function scopeAtivos($query)
    {
        if (in_array('flgati', $this->fillable)) {
            return $query->where('flgati', true);
        }
        
        return $query;
    }
    
    /**
     * Método para obter estatísticas básicas da tabela
     */
    public static function getEstatisticas()
    {
        $model = new static;
        $tabela = $model->getTable();
        
        return [
            'total' => static::count(),
            'ativos' => static::where('flgati', true)->count(),
            'inativos' => static::where('flgati', false)->count(),
        ];
    }
}