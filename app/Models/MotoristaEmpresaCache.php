<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Cache de dados de motoristas para empresas (CNPJ)
 *
 * Esta tabela armazena dados complementares de motoristas que não estão
 * no Progress (trnmot), permitindo emissão de VPO para empresas.
 *
 * Fluxo:
 * 1. Usuário seleciona empresa (CNPJ) para VPO
 * 2. Sistema busca motoristas em PUB.trnmot
 * 3. Se motorista não tem dados completos, usuário preenche aqui
 * 4. Dados são usados para emissão VPO
 * 5. Futuramente pode sincronizar de volta para Progress
 */
class MotoristaEmpresaCache extends Model
{
    use HasFactory;

    protected $table = 'motorista_empresa_cache';

    protected $fillable = [
        // Chaves do Progress
        'codtrn',
        'codmot',

        // Dados do Progress (cache)
        'nommot',
        'numrg',
        'nompai',
        'nommae',

        // Dados completados pelo usuário
        'cpf',
        'rntrc',
        'data_nascimento',
        'cnh',
        'categoria_cnh',
        'validade_cnh',

        // Endereço
        'endereco_logradouro',
        'endereco_numero',
        'endereco_bairro',
        'endereco_cidade',
        'endereco_uf',
        'endereco_cep',

        // Controle
        'dados_completos',
        'sincronizado_progress',

        // Metadados
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'codtrn' => 'integer',
        'codmot' => 'integer',
        'data_nascimento' => 'date',
        'validade_cnh' => 'date',
        'dados_completos' => 'boolean',
        'sincronizado_progress' => 'boolean',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    /**
     * Campos obrigatórios para emissão de VPO
     */
    public const CAMPOS_OBRIGATORIOS_VPO = [
        'cpf',
        'rntrc',
        'nommot',
        'nommae',
        'data_nascimento',
    ];

    /**
     * Verifica se todos os campos obrigatórios para VPO estão preenchidos
     */
    public function verificarDadosCompletos(): bool
    {
        foreach (self::CAMPOS_OBRIGATORIOS_VPO as $campo) {
            if (empty($this->$campo)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Atualiza a flag de dados completos
     */
    public function atualizarFlagDadosCompletos(): self
    {
        $this->dados_completos = $this->verificarDadosCompletos();
        return $this;
    }

    /**
     * Lista campos faltantes para VPO
     */
    public function getCamposFaltantes(): array
    {
        $faltantes = [];
        foreach (self::CAMPOS_OBRIGATORIOS_VPO as $campo) {
            if (empty($this->$campo)) {
                $faltantes[] = $campo;
            }
        }
        return $faltantes;
    }

    /**
     * Busca motorista pelo codtrn e codmot
     */
    public static function findByMotorista(int $codtrn, int $codmot): ?self
    {
        return self::where('codtrn', $codtrn)
            ->where('codmot', $codmot)
            ->first();
    }

    /**
     * Lista todos motoristas de um transportador
     */
    public static function getByTransportador(int $codtrn): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('codtrn', $codtrn)
            ->orderBy('codmot')
            ->get();
    }

    /**
     * Lista motoristas com dados completos de um transportador
     */
    public static function getCompletosDoTransportador(int $codtrn): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('codtrn', $codtrn)
            ->where('dados_completos', true)
            ->orderBy('codmot')
            ->get();
    }

    /**
     * Converte para formato compatível com VpoDataSyncService
     */
    public function toVpoData(): array
    {
        return [
            'codtrn' => $this->codtrn,
            'codmot' => $this->codmot,

            // Dados do condutor
            'condutor_nome' => $this->nommot,
            'condutor_cpf' => $this->cpf,
            'condutor_rg' => $this->numrg,
            'condutor_nome_mae' => $this->nommae,
            'condutor_data_nascimento' => $this->data_nascimento?->format('Y-m-d'),

            // RNTRC
            'antt_rntrc' => $this->rntrc,
            'antt_nome' => $this->nommot,

            // CNH
            'cnh_numero' => $this->cnh,
            'cnh_categoria' => $this->categoria_cnh,
            'cnh_validade' => $this->validade_cnh?->format('Y-m-d'),

            // Endereço
            'endereco_logradouro' => $this->endereco_logradouro,
            'endereco_numero' => $this->endereco_numero,
            'endereco_bairro' => $this->endereco_bairro,
            'endereco_cidade' => $this->endereco_cidade,
            'endereco_estado' => $this->endereco_uf,
            'endereco_cep' => $this->endereco_cep,
        ];
    }

    /**
     * Boot do model - auto-atualiza flag antes de salvar
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            $model->atualizarFlagDadosCompletos();
        });
    }
}
