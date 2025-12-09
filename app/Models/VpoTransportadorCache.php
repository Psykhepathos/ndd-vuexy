<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Carbon\Carbon;

class VpoTransportadorCache extends Model
{
    protected $table = 'vpo_transportadores_cache';

    protected $fillable = [
        // Chaves Progress
        'codtrn',
        'codmot',
        'numpla',
        'flgautonomo',

        // 19 campos VPO
        'cpf_cnpj',
        'antt_rntrc',
        'antt_nome',
        'antt_validade',
        'antt_status',
        'placa',
        'veiculo_tipo',
        'veiculo_modelo',
        'condutor_rg',
        'condutor_nome',
        'condutor_sexo',
        'condutor_nome_mae',
        'condutor_data_nascimento',
        'endereco_rua',
        'endereco_bairro',
        'endereco_cidade',
        'endereco_estado',
        'contato_celular',
        'contato_email',

        // Metadados
        'fontes_dados',
        'ultima_sync_progress',
        'ultima_sync_antt',
        'antt_fonte',
        'score_qualidade',
        'campos_faltantes',
        'avisos',
        'ultimo_uso',
        'total_usos',
        'editado_manualmente',
        'data_edicao_manual',
    ];

    protected $casts = [
        'flgautonomo' => 'boolean',
        'antt_validade' => 'date',
        'condutor_data_nascimento' => 'date',
        'fontes_dados' => 'array',
        'campos_faltantes' => 'array',
        'avisos' => 'array',
        'ultima_sync_progress' => 'datetime',
        'ultima_sync_antt' => 'datetime',
        'ultimo_uso' => 'datetime',
        'score_qualidade' => 'integer',
        'total_usos' => 'integer',
        'editado_manualmente' => 'boolean',
        'data_edicao_manual' => 'datetime',
    ];

    /**
     * Retorna array com dados VPO formatados para NDD Cargo
     */
    public function toVpoArray(): array
    {
        return [
            // Flag de tipo de transportador (crítico para cpfTransportador vs cnpjTransportador)
            'flgautonomo' => $this->flgautonomo,

            // 19 campos VPO
            'cpf_cnpj' => $this->cpf_cnpj,
            'antt_rntrc' => $this->antt_rntrc,
            'antt_nome' => $this->antt_nome,
            'antt_validade' => $this->antt_validade?->format('Y-m-d'),
            'antt_status' => $this->antt_status,
            'placa' => $this->placa,
            'veiculo_tipo' => $this->veiculo_tipo,
            'veiculo_modelo' => $this->veiculo_modelo,
            'condutor_rg' => $this->condutor_rg,
            'condutor_nome' => $this->condutor_nome,
            'condutor_sexo' => $this->condutor_sexo,
            'condutor_nome_mae' => $this->condutor_nome_mae,
            'condutor_data_nascimento' => $this->condutor_data_nascimento?->format('Y-m-d'),
            'endereco_rua' => $this->endereco_rua,
            'endereco_bairro' => $this->endereco_bairro,
            'endereco_cidade' => $this->endereco_cidade,
            'endereco_estado' => $this->endereco_estado,
            'contato_celular' => $this->contato_celular,
            'contato_email' => $this->contato_email,
        ];
    }

    /**
     * Verifica se os dados estão desatualizados (> 7 dias desde última sync)
     */
    public function isStale(): bool
    {
        if (!$this->ultima_sync_progress) {
            return true;
        }

        return $this->ultima_sync_progress->lt(now()->subDays(7));
    }

    /**
     * Verifica se o RNTRC está válido (data de validade futura)
     */
    public function isRntrcValido(): bool
    {
        if (!$this->antt_validade) {
            return false;
        }

        return $this->antt_validade->isFuture();
    }

    /**
     * Verifica se precisa atualizar dados da ANTT (> 30 dias)
     */
    public function needsAnttUpdate(): bool
    {
        if (!$this->ultima_sync_antt) {
            return true;
        }

        return $this->ultima_sync_antt->lt(now()->subDays(30));
    }

    /**
     * Incrementa contador de uso e atualiza timestamp
     */
    public function registerUse(): void
    {
        $this->increment('total_usos');
        $this->update(['ultimo_uso' => now()]);
    }

    /**
     * Calcula score de qualidade dos dados (0-100)
     */
    public function calculateQualityScore(): int
    {
        $score = 100;
        $campos_faltantes = [];

        // Campos obrigatórios (-10 pontos cada)
        $obrigatorios = [
            'cpf_cnpj', 'antt_rntrc', 'antt_nome', 'placa',
            'veiculo_tipo', 'condutor_rg', 'condutor_nome',
            'condutor_nome_mae', 'condutor_data_nascimento',
            'endereco_rua', 'endereco_cidade', 'endereco_estado',
            'contato_celular', 'contato_email'
        ];

        foreach ($obrigatorios as $campo) {
            if (empty($this->$campo)) {
                $score -= 10;
                $campos_faltantes[] = $campo;
            }
        }

        // Campos opcionais (-5 pontos cada)
        $opcionais = ['veiculo_modelo', 'antt_validade', 'endereco_bairro'];

        foreach ($opcionais as $campo) {
            if (empty($this->$campo)) {
                $score -= 5;
                $campos_faltantes[] = $campo;
            }
        }

        // RNTRC vencido (-20 pontos)
        if (!$this->isRntrcValido()) {
            $score -= 20;
        }

        // Status não ativo (-30 pontos)
        if ($this->antt_status !== 'Ativo') {
            $score -= 30;
        }

        // Dados desatualizados (-10 pontos)
        if ($this->isStale()) {
            $score -= 10;
        }

        $this->update([
            'score_qualidade' => max(0, $score),
            'campos_faltantes' => $campos_faltantes
        ]);

        return max(0, $score);
    }

    /**
     * Scope: buscar por Progress codtrn
     */
    public function scopeByCodtrn($query, int $codtrn)
    {
        return $query->where('codtrn', $codtrn);
    }

    /**
     * Scope: buscar por RNTRC
     */
    public function scopeByRntrc($query, string $rntrc)
    {
        return $query->where('antt_rntrc', $rntrc);
    }

    /**
     * Scope: buscar por placa
     */
    public function scopeByPlaca($query, string $placa)
    {
        return $query->where('placa', $placa);
    }

    /**
     * Scope: apenas ativos
     */
    public function scopeAtivos($query)
    {
        return $query->where('antt_status', 'Ativo');
    }

    /**
     * Scope: apenas com RNTRC válido
     */
    public function scopeRntrcValido($query)
    {
        return $query->where('antt_validade', '>=', now());
    }

    /**
     * Scope: score acima de threshold
     */
    public function scopeQualidadeMinima($query, int $minScore = 70)
    {
        return $query->where('score_qualidade', '>=', $minScore);
    }
}
