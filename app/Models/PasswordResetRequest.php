<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PasswordResetRequest extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'reason',
        'status',
        'handled_by',
        'handled_at',
        'admin_notes',
    ];

    protected $casts = [
        'handled_at' => 'datetime',
    ];

    /**
     * Usuário que solicitou o reset
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Admin que tratou a solicitação
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /**
     * Cria uma nova solicitação de reset
     */
    public static function createRequest(int $userId, ?string $reason = null): self
    {
        // Cancela solicitações pendentes anteriores
        self::where('user_id', $userId)
            ->where('status', self::STATUS_PENDING)
            ->update(['status' => self::STATUS_REJECTED, 'admin_notes' => 'Substituída por nova solicitação']);

        return self::create([
            'user_id' => $userId,
            'reason' => $reason,
            'status' => self::STATUS_PENDING,
        ]);
    }

    /**
     * Aprova a solicitação
     */
    public function approve(int $adminId, ?string $notes = null): bool
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'handled_by' => $adminId,
            'handled_at' => now(),
            'admin_notes' => $notes,
        ]);

        return true;
    }

    /**
     * Rejeita a solicitação
     */
    public function reject(int $adminId, ?string $notes = null): bool
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'handled_by' => $adminId,
            'handled_at' => now(),
            'admin_notes' => $notes,
        ]);

        return true;
    }

    /**
     * Verifica se está pendente
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Retorna a descrição do status
     */
    public function getStatusDescriptionAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pendente',
            self::STATUS_APPROVED => 'Aprovada',
            self::STATUS_REJECTED => 'Rejeitada',
            default => $this->status,
        };
    }

    /**
     * Retorna a cor do status
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'warning',
            self::STATUS_APPROVED => 'success',
            self::STATUS_REJECTED => 'error',
            default => 'default',
        };
    }
}
