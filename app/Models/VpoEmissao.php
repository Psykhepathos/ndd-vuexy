<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class VpoEmissao extends Model
{
    protected $table = 'vpo_emissoes';

    protected $fillable = [
        // Identificação
        'uuid',
        'codpac',
        'codtrn',
        'codmot',

        // Rota
        'rota_id',
        'rota_nome',
        'waypoints',
        'total_waypoints',

        // Dados VPO
        'vpo_data',
        'fontes_dados',
        'score_qualidade',

        // Status
        'status',

        // Request/Response
        'ndd_request_xml',
        'ndd_response',
        'error_message',
        'error_code',

        // Resultados
        'pracas_pedagio',
        'total_pracas',
        'custo_total',
        'distancia_km',
        'tempo_minutos',

        // Polling
        'tentativas_polling',
        'requested_at',
        'polled_at',
        'completed_at',
        'failed_at',

        // Cancelamento
        'cancelled_at',
        'cancellation_reason',
        'ndd_cancellation_request',
        'ndd_cancellation_response',

        // Metadados
        'usuario_id',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'waypoints' => 'array',
        'vpo_data' => 'array',
        'fontes_dados' => 'array',
        'ndd_response' => 'array',
        'pracas_pedagio' => 'array',
        'score_qualidade' => 'integer',
        'total_waypoints' => 'integer',
        'total_pracas' => 'integer',
        'custo_total' => 'decimal:2',
        'distancia_km' => 'decimal:2',
        'tempo_minutos' => 'integer',
        'tentativas_polling' => 'integer',
        'requested_at' => 'datetime',
        'polled_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'ndd_cancellation_response' => 'array',
    ];

    // === RELATIONSHIPS ===

    /**
     * Usuário que iniciou a emissão
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    // === STATUS CHECKERS ===

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Verifica se ainda está em processamento (não finalizou)
     */
    public function isInProgress(): bool
    {
        return in_array($this->status, ['pending', 'processing']);
    }

    /**
     * Verifica se finalizou (sucesso ou falha)
     */
    public function isFinished(): bool
    {
        return in_array($this->status, ['completed', 'failed', 'cancelled']);
    }

    // === STATUS TRANSITIONS ===

    public function markAsProcessing(): self
    {
        $this->update([
            'status' => 'processing',
            'requested_at' => now(),
        ]);

        return $this;
    }

    public function markAsCompleted(array $response): self
    {
        $this->update([
            'status' => 'completed',
            'ndd_response' => $response,
            'completed_at' => now(),
        ]);

        return $this;
    }

    public function markAsFailed(string $errorMessage, ?string $errorCode = null): self
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'error_code' => $errorCode,
            'failed_at' => now(),
        ]);

        return $this;
    }

    public function markAsCancelled(): self
    {
        $this->update([
            'status' => 'cancelled',
        ]);

        return $this;
    }

    /**
     * Incrementa contador de polling e atualiza timestamp
     */
    public function registerPolling(): self
    {
        $this->increment('tentativas_polling');
        $this->update(['polled_at' => now()]);

        return $this;
    }

    // === POLLING CONTROL ===

    /**
     * Verifica se excedeu limite de tentativas de polling (ex: 20 tentativas)
     */
    public function hasExceededPollingLimit(int $maxTentativas = 20): bool
    {
        return $this->tentativas_polling >= $maxTentativas;
    }

    /**
     * Verifica se já passou tempo suficiente desde último polling (ex: 5 segundos)
     */
    public function canPollAgain(int $intervalSeconds = 5): bool
    {
        if (!$this->polled_at) {
            return true;
        }

        return $this->polled_at->addSeconds($intervalSeconds)->isPast();
    }

    /**
     * Verifica se emissão está "travada" (processing por mais de 10 minutos)
     */
    public function isStuck(int $timeoutMinutes = 10): bool
    {
        if (!$this->isProcessing()) {
            return false;
        }

        if (!$this->requested_at) {
            return false;
        }

        return $this->requested_at->addMinutes($timeoutMinutes)->isPast();
    }

    // === DATA HELPERS ===

    /**
     * Retorna resumo da emissão (para exibição no frontend)
     */
    public function getSummary(): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'codpac' => $this->codpac,
            'rota_nome' => $this->rota_nome,
            'status' => $this->status,
            'total_pracas' => $this->total_pracas,
            'custo_total' => $this->custo_total,
            'distancia_km' => $this->distancia_km,
            'tempo_minutos' => $this->tempo_minutos,
            'score_qualidade' => $this->score_qualidade,
            'requested_at' => $this->requested_at?->format('Y-m-d H:i:s'),
            'completed_at' => $this->completed_at?->format('Y-m-d H:i:s'),
            'error_message' => $this->error_message,
        ];
    }

    /**
     * Extrai dados VPO do cache (para validação)
     */
    public function getVpoData(): array
    {
        return $this->vpo_data ?? [];
    }

    /**
     * Extrai lista de praças de pedágio
     */
    public function getPracasPedagio(): array
    {
        return $this->pracas_pedagio ?? [];
    }

    // === SCOPES ===

    /**
     * Scope: buscar por UUID
     */
    public function scopeByUuid($query, string $uuid)
    {
        return $query->where('uuid', $uuid);
    }

    /**
     * Scope: buscar por pacote
     */
    public function scopeByPacote($query, int $codpac)
    {
        return $query->where('codpac', $codpac);
    }

    /**
     * Scope: buscar por transportador
     */
    public function scopeByTransportador($query, int $codtrn)
    {
        return $query->where('codtrn', $codtrn);
    }

    /**
     * Scope: apenas em processamento
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    /**
     * Scope: apenas completadas
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope: apenas com falha
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope: em progresso (pending ou processing)
     */
    public function scopeInProgress($query)
    {
        return $query->whereIn('status', ['pending', 'processing']);
    }

    /**
     * Scope: finalizadas (completed, failed ou cancelled)
     */
    public function scopeFinished($query)
    {
        return $query->whereIn('status', ['completed', 'failed', 'cancelled']);
    }

    /**
     * Scope: travadas (processing há mais de X minutos)
     */
    public function scopeStuck($query, int $timeoutMinutes = 10)
    {
        return $query->where('status', 'processing')
            ->where('requested_at', '<', now()->subMinutes($timeoutMinutes));
    }

    /**
     * Scope: prontas para polling (processing + intervalo passou)
     */
    public function scopeReadyForPolling($query, int $intervalSeconds = 5)
    {
        return $query->where('status', 'processing')
            ->where(function ($q) use ($intervalSeconds) {
                $q->whereNull('polled_at')
                    ->orWhere('polled_at', '<', now()->subSeconds($intervalSeconds));
            });
    }

    /**
     * Scope: ordenar por mais recentes
     */
    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }
}
