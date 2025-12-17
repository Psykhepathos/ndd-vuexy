<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

/**
 * Cache de veículos validados no SemParar
 *
 * Armazena dados de veículos que foram validados na API SemParar,
 * permitindo reutilização e edição pelo usuário.
 */
class VeiculoSemPararCache extends Model
{
    protected $table = 'veiculo_semparar_cache';

    protected $fillable = [
        // Identificação
        'placa',

        // Dados SemParar
        'descricao',
        'eixos',
        'proprietario',
        'tag',
        'status',

        // Dados adicionais (editáveis)
        'tipo_veiculo',
        'modelo',
        'marca',
        'ano_fabricacao',
        'renavam',
        'chassi',

        // Relações
        'codtrn',
        'codmot',

        // Metadados
        'editado_manualmente',
        'ultima_validacao_semparar',
        'dados_semparar_reais',
        'ultimo_uso',
        'total_usos',
        'usuario_criacao_id',
        'usuario_atualizacao_id',
    ];

    protected $casts = [
        'eixos' => 'integer',
        'ano_fabricacao' => 'integer',
        'codtrn' => 'integer',
        'codmot' => 'integer',
        'editado_manualmente' => 'boolean',
        'dados_semparar_reais' => 'boolean',
        'ultima_validacao_semparar' => 'datetime',
        'ultimo_uso' => 'datetime',
        'total_usos' => 'integer',
    ];

    // === SCOPES ===

    /**
     * Buscar por placa (case-insensitive, sem hífen)
     */
    public function scopeByPlaca($query, string $placa)
    {
        $placaNormalizada = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $placa));
        return $query->whereRaw('UPPER(REPLACE(placa, "-", "")) = ?', [$placaNormalizada]);
    }

    /**
     * Buscar veículos de um transportador
     */
    public function scopeByTransportador($query, int $codtrn)
    {
        return $query->where('codtrn', $codtrn);
    }

    /**
     * Buscar veículos ativos
     */
    public function scopeAtivos($query)
    {
        return $query->where('status', 'ATIVO');
    }

    /**
     * Buscar veículos com dados reais (não simulados)
     */
    public function scopeDadosReais($query)
    {
        return $query->where('dados_semparar_reais', true);
    }

    /**
     * Buscar veículos que precisam revalidação (mais de 24h)
     */
    public function scopeNecessitaRevalidacao($query, int $horas = 24)
    {
        return $query->where(function ($q) use ($horas) {
            $q->whereNull('ultima_validacao_semparar')
              ->orWhere('ultima_validacao_semparar', '<', now()->subHours($horas));
        });
    }

    // === MÉTODOS ===

    /**
     * Busca veículo pelo placa, normalizando o formato
     */
    public static function findByPlaca(string $placa): ?self
    {
        $placaNormalizada = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $placa));
        return self::whereRaw('UPPER(REPLACE(placa, "-", "")) = ?', [$placaNormalizada])->first();
    }

    /**
     * Cria ou atualiza cache do veículo com dados do SemParar
     */
    public static function updateFromSemParar(array $dadosSemParar, bool $dadosReais = false, ?int $userId = null): self
    {
        $placa = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $dadosSemParar['placa'] ?? ''));

        $veiculo = self::findByPlaca($placa);

        $data = [
            'placa' => $placa,
            'descricao' => $dadosSemParar['descricao'] ?? null,
            'eixos' => $dadosSemParar['eixos'] ?? 2,
            'proprietario' => $dadosSemParar['proprietario'] ?? null,
            'tag' => $dadosSemParar['tag'] ?? null,
            'status' => $dadosSemParar['status'] ?? 'ATIVO',
            'ultima_validacao_semparar' => now(),
            'dados_semparar_reais' => $dadosReais,
        ];

        // Inferir tipo de veículo da descrição
        if (!empty($dadosSemParar['descricao'])) {
            $data['tipo_veiculo'] = self::inferirTipoVeiculo($dadosSemParar['descricao']);
        }

        if ($veiculo) {
            // Não sobrescrever dados editados manualmente
            if (!$veiculo->editado_manualmente) {
                $data['usuario_atualizacao_id'] = $userId;
                $veiculo->update($data);
            } else {
                // Apenas atualizar timestamp de validação
                $veiculo->update([
                    'ultima_validacao_semparar' => now(),
                    'dados_semparar_reais' => $dadosReais,
                ]);
            }
        } else {
            $data['usuario_criacao_id'] = $userId;
            $data['total_usos'] = 0;
            $veiculo = self::create($data);
        }

        return $veiculo;
    }

    /**
     * Infere o tipo de veículo a partir da descrição
     */
    public static function inferirTipoVeiculo(string $descricao): ?string
    {
        $descricaoLower = strtolower($descricao);

        $tipos = [
            'BITREM' => ['bitrem', 'bi-trem'],
            'RODOTREM' => ['rodotrem', 'rodo-trem', 'rodo trem'],
            'CARRETA' => ['carreta', 'semi-reboque'],
            'TRUCK' => ['truck', '3/4'],
            'TOCO' => ['toco'],
            'VUC' => ['vuc', 'veículo urbano', 'veiculo urbano'],
            'FURGAO' => ['furgão', 'furgao', 'van'],
            'UTILITARIO' => ['utilitário', 'utilitario', 'pickup', 'pick-up'],
            'ONIBUS' => ['ônibus', 'onibus', 'bus'],
            'MOTO' => ['moto', 'motocicleta'],
            'PASSEIO' => ['passeio', 'automóvel', 'automovel', 'carro'],
        ];

        foreach ($tipos as $tipo => $palavras) {
            foreach ($palavras as $palavra) {
                if (str_contains($descricaoLower, $palavra)) {
                    return $tipo;
                }
            }
        }

        // Se tem "caminhão" mas não identificou tipo específico
        if (str_contains($descricaoLower, 'caminhão') || str_contains($descricaoLower, 'caminhao')) {
            return 'CAMINHAO';
        }

        return null;
    }

    /**
     * Registra uso do veículo
     */
    public function registrarUso(): self
    {
        $this->increment('total_usos');
        $this->update(['ultimo_uso' => now()]);
        return $this;
    }

    /**
     * Marca como editado manualmente
     */
    public function marcarComoEditado(?int $userId = null): self
    {
        $this->update([
            'editado_manualmente' => true,
            'usuario_atualizacao_id' => $userId,
        ]);
        return $this;
    }

    /**
     * Verifica se precisa revalidação no SemParar
     */
    public function precisaRevalidacao(int $horas = 24): bool
    {
        if (!$this->ultima_validacao_semparar) {
            return true;
        }
        return $this->ultima_validacao_semparar->diffInHours(now()) >= $horas;
    }

    /**
     * Retorna dados formatados para o frontend
     */
    public function toFrontendArray(): array
    {
        return [
            'id' => $this->id,
            'placa' => $this->placa,
            'placa_formatada' => $this->getPlacaFormatada(),
            'descricao' => $this->descricao,
            'eixos' => $this->eixos,
            'proprietario' => $this->proprietario,
            'tag' => $this->tag,
            'status' => $this->status,
            'status_semparar' => $this->getStatusFormatado(),
            'tipo_veiculo' => $this->tipo_veiculo,
            'modelo' => $this->modelo,
            'marca' => $this->marca,
            'ano_fabricacao' => $this->ano_fabricacao,
            'codtrn' => $this->codtrn,
            'codmot' => $this->codmot,
            'editado_manualmente' => $this->editado_manualmente,
            'dados_semparar_reais' => $this->dados_semparar_reais,
            'ultima_validacao' => $this->ultima_validacao_semparar?->format('Y-m-d H:i:s'),
            'precisa_revalidacao' => $this->precisaRevalidacao(),
            'total_usos' => $this->total_usos,
        ];
    }

    /**
     * Retorna placa formatada (ABC-1234 ou ABC1D23)
     */
    public function getPlacaFormatada(): string
    {
        $placa = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $this->placa));
        if (strlen($placa) === 7) {
            // Detectar se é Mercosul (letra na 5ª posição)
            if (ctype_alpha($placa[4])) {
                return $placa; // Mercosul: ABC1D23
            }
            return substr($placa, 0, 3) . '-' . substr($placa, 3); // Antiga: ABC-1234
        }
        return $placa;
    }

    /**
     * Retorna status formatado com ícone
     */
    public function getStatusFormatado(): array
    {
        $statusMap = [
            'ATIVO' => ['label' => 'Ativo', 'color' => 'success', 'icon' => 'tabler-check'],
            'INATIVO' => ['label' => 'Inativo', 'color' => 'error', 'icon' => 'tabler-x'],
            'PENDENTE' => ['label' => 'Pendente', 'color' => 'warning', 'icon' => 'tabler-clock'],
            'BLOQUEADO' => ['label' => 'Bloqueado', 'color' => 'error', 'icon' => 'tabler-lock'],
        ];

        return $statusMap[$this->status] ?? ['label' => $this->status, 'color' => 'secondary', 'icon' => 'tabler-help'];
    }
}
