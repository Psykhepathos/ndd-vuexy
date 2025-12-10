<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

/**
 * Model para logs de auditoria de emissões VPO
 *
 * Armazena todos os dados de cada emissão VPO para auditoria completa.
 *
 * @property int $id
 * @property string $uuid
 * @property string $status
 * @property int|null $codpac
 * @property string|null $pacote_situacao
 * @property int|null $codtrn
 * @property string|null $transportador_nome
 * @property string|null $transportador_cpf_cnpj
 * @property bool $transportador_autonomo
 * @property string|null $transportador_rntrc
 * @property int|null $codmot
 * @property string|null $motorista_nome
 * @property string|null $motorista_cpf
 * @property string|null $veiculo_placa
 * @property string|null $veiculo_modelo
 * @property int|null $veiculo_eixos
 * @property int|null $categoria_pedagio
 * @property int|null $rota_id
 * @property string|null $rota_nome
 * @property int|null $rota_municipios_count
 * @property array|null $rota_municipios
 * @property int $pracas_count
 * @property array|null $pracas_pedagio
 * @property float $valor_total_pedagios
 * @property float|null $distancia_km
 * @property int|null $tempo_estimado_min
 * @property string|null $data_inicio
 * @property string|null $data_fim
 * @property string|null $roteirizador_guid
 * @property array|null $roteirizador_request
 * @property array|null $roteirizador_response
 * @property \Carbon\Carbon|null $roteirizador_enviado_em
 * @property \Carbon\Carbon|null $roteirizador_respondido_em
 * @property string|null $emissao_guid
 * @property array|null $emissao_request
 * @property array|null $emissao_response
 * @property \Carbon\Carbon|null $emissao_enviada_em
 * @property \Carbon\Carbon|null $emissao_respondida_em
 * @property string|null $ndd_codigo_retorno
 * @property string|null $ndd_mensagem_retorno
 * @property string|null $ndd_protocolo
 * @property string|null $erro_mensagem
 * @property string|null $erro_detalhes
 * @property int|null $user_id
 * @property string|null $user_name
 * @property string|null $user_ip
 * @property string|null $user_agent
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $concluido_em
 */
class VpoEmissaoLog extends Model
{
    use HasFactory;

    protected $table = 'vpo_emissao_logs';

    protected $fillable = [
        'uuid',
        'status',
        'codpac',
        'pacote_situacao',
        'codtrn',
        'transportador_nome',
        'transportador_cpf_cnpj',
        'transportador_autonomo',
        'transportador_rntrc',
        'codmot',
        'motorista_nome',
        'motorista_cpf',
        'veiculo_placa',
        'veiculo_modelo',
        'veiculo_eixos',
        'categoria_pedagio',
        'rota_id',
        'rota_nome',
        'rota_municipios_count',
        'rota_municipios',
        'pracas_count',
        'pracas_pedagio',
        'valor_total_pedagios',
        'distancia_km',
        'tempo_estimado_min',
        'data_inicio',
        'data_fim',
        'roteirizador_guid',
        'roteirizador_request',
        'roteirizador_response',
        'roteirizador_enviado_em',
        'roteirizador_respondido_em',
        'emissao_guid',
        'emissao_request',
        'emissao_response',
        'emissao_enviada_em',
        'emissao_respondida_em',
        'ndd_codigo_retorno',
        'ndd_mensagem_retorno',
        'ndd_protocolo',
        'erro_mensagem',
        'erro_detalhes',
        'user_id',
        'user_name',
        'user_ip',
        'user_agent',
        'concluido_em',
    ];

    protected $casts = [
        'transportador_autonomo' => 'boolean',
        'rota_municipios' => 'array',
        'pracas_pedagio' => 'array',
        'roteirizador_request' => 'array',
        'roteirizador_response' => 'array',
        'emissao_request' => 'array',
        'emissao_response' => 'array',
        'valor_total_pedagios' => 'decimal:2',
        'distancia_km' => 'decimal:2',
        'roteirizador_enviado_em' => 'datetime',
        'roteirizador_respondido_em' => 'datetime',
        'emissao_enviada_em' => 'datetime',
        'emissao_respondida_em' => 'datetime',
        'concluido_em' => 'datetime',
        'data_inicio' => 'date',
        'data_fim' => 'date',
    ];

    /**
     * Boot do model - gera UUID automaticamente
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = Str::uuid()->toString();
            }
        });
    }

    /**
     * Cria um novo log de emissão VPO
     *
     * @param array $data Dados iniciais
     * @param \Illuminate\Http\Request|null $request Request HTTP para auditoria
     * @return self
     */
    public static function iniciar(array $data, $request = null): self
    {
        $log = new self($data);
        $log->status = 'iniciado';

        // Dados de auditoria
        if ($request) {
            $log->user_ip = $request->ip();
            $log->user_agent = substr($request->userAgent() ?? '', 0, 500);

            if ($request->user()) {
                $log->user_id = $request->user()->id;
                $log->user_name = $request->user()->name;
            }
        }

        $log->save();

        return $log;
    }

    /**
     * Atualiza status para calculando praças
     */
    public function marcarCalculando(): self
    {
        $this->status = 'calculando';
        $this->save();
        return $this;
    }

    /**
     * Registra envio do roteirizador
     */
    public function registrarRoteirizadorEnviado(string $guid, array $request): self
    {
        $this->roteirizador_guid = $guid;
        $this->roteirizador_request = $request;
        $this->roteirizador_enviado_em = now();
        $this->status = 'aguardando';
        $this->save();
        return $this;
    }

    /**
     * Registra resposta do roteirizador
     */
    public function registrarRoteirizadorResposta(array $response, array $pracas, float $valorTotal, ?float $distanciaKm = null, ?int $tempoMin = null): self
    {
        $this->roteirizador_response = $response;
        $this->roteirizador_respondido_em = now();
        $this->pracas_pedagio = $pracas;
        $this->pracas_count = count($pracas);
        $this->valor_total_pedagios = $valorTotal;
        $this->distancia_km = $distanciaKm;
        $this->tempo_estimado_min = $tempoMin;
        $this->save();
        return $this;
    }

    /**
     * Registra envio da emissão VPO
     */
    public function registrarEmissaoEnviada(string $guid, array $request): self
    {
        $this->emissao_guid = $guid;
        $this->emissao_request = $request;
        $this->emissao_enviada_em = now();
        $this->save();
        return $this;
    }

    /**
     * Registra resposta da emissão VPO
     */
    public function registrarEmissaoResposta(array $response, ?string $codigoRetorno = null, ?string $mensagem = null, ?string $protocolo = null): self
    {
        $this->emissao_response = $response;
        $this->emissao_respondida_em = now();
        $this->ndd_codigo_retorno = $codigoRetorno;
        $this->ndd_mensagem_retorno = $mensagem;
        $this->ndd_protocolo = $protocolo;
        $this->save();
        return $this;
    }

    /**
     * Marca emissão como sucesso
     */
    public function marcarSucesso(?string $protocolo = null): self
    {
        $this->status = 'sucesso';
        $this->concluido_em = now();
        if ($protocolo) {
            $this->ndd_protocolo = $protocolo;
        }
        $this->save();
        return $this;
    }

    /**
     * Marca emissão como erro
     */
    public function marcarErro(string $mensagem, ?string $detalhes = null): self
    {
        $this->status = 'erro';
        $this->erro_mensagem = $mensagem;
        $this->erro_detalhes = $detalhes;
        $this->concluido_em = now();
        $this->save();
        return $this;
    }

    /**
     * Marca emissão como cancelada
     */
    public function marcarCancelado(?string $motivo = null): self
    {
        $this->status = 'cancelado';
        $this->erro_mensagem = $motivo;
        $this->concluido_em = now();
        $this->save();
        return $this;
    }

    /**
     * Scope: filtrar por status
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: filtrar por transportador
     */
    public function scopeTransportador($query, int $codtrn)
    {
        return $query->where('codtrn', $codtrn);
    }

    /**
     * Scope: filtrar por pacote
     */
    public function scopePacote($query, int $codpac)
    {
        return $query->where('codpac', $codpac);
    }

    /**
     * Scope: filtrar por placa
     */
    public function scopePlaca($query, string $placa)
    {
        return $query->where('veiculo_placa', $placa);
    }

    /**
     * Scope: filtrar por período
     */
    public function scopePeriodo($query, string $dataInicio, string $dataFim)
    {
        return $query->whereBetween('created_at', [$dataInicio, $dataFim]);
    }

    /**
     * Scope: apenas com sucesso
     */
    public function scopeSucesso($query)
    {
        return $query->where('status', 'sucesso');
    }

    /**
     * Scope: apenas com erro
     */
    public function scopeErro($query)
    {
        return $query->where('status', 'erro');
    }

    /**
     * Formata valor total para exibição
     */
    public function getValorTotalFormatadoAttribute(): string
    {
        return 'R$ ' . number_format($this->valor_total_pedagios, 2, ',', '.');
    }

    /**
     * Retorna duração total do processo (criação até conclusão)
     */
    public function getDuracaoSegundosAttribute(): ?int
    {
        if (!$this->concluido_em) {
            return null;
        }

        return $this->created_at->diffInSeconds($this->concluido_em);
    }

    /**
     * Retorna resumo para listagem
     */
    public function getResumoAttribute(): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'status' => $this->status,
            'codpac' => $this->codpac,
            'transportador' => $this->transportador_nome,
            'placa' => $this->veiculo_placa,
            'rota' => $this->rota_nome,
            'pracas_count' => $this->pracas_count,
            'valor_total' => $this->valor_total_pedagios,
            'valor_formatado' => $this->valor_total_formatado,
            'created_at' => $this->created_at?->format('d/m/Y H:i:s'),
            'concluido_em' => $this->concluido_em?->format('d/m/Y H:i:s'),
        ];
    }
}
