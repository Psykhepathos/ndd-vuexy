<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAuditLog extends Model
{
    // Ações possíveis
    public const ACTION_CREATED = 'created';
    public const ACTION_UPDATED = 'updated';
    public const ACTION_DELETED = 'deleted';
    public const ACTION_PASSWORD_RESET = 'password_reset';
    public const ACTION_PASSWORD_CHANGED = 'password_changed';
    public const ACTION_ROLE_CHANGED = 'role_changed';
    public const ACTION_STATUS_CHANGED = 'status_changed';
    public const ACTION_LOGIN = 'login';
    public const ACTION_LOGOUT = 'logout';
    public const ACTION_LOGIN_FAILED = 'login_failed';

    protected $fillable = [
        'user_id',
        'performed_by',
        'action',
        'field_changed',
        'old_value',
        'new_value',
        'reason',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Usuário afetado pela ação
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Usuário que realizou a ação
     */
    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /**
     * Registra uma ação de auditoria
     */
    public static function log(
        int $userId,
        string $action,
        ?int $performedBy = null,
        ?string $fieldChanged = null,
        mixed $oldValue = null,
        mixed $newValue = null,
        ?string $reason = null
    ): self {
        return self::create([
            'user_id' => $userId,
            'performed_by' => $performedBy ?? auth()->id(),
            'action' => $action,
            'field_changed' => $fieldChanged,
            'old_value' => is_array($oldValue) ? json_encode($oldValue) : $oldValue,
            'new_value' => is_array($newValue) ? json_encode($newValue) : $newValue,
            'reason' => $reason,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Retorna a descrição amigável da ação
     */
    public function getActionDescriptionAttribute(): string
    {
        $descriptions = [
            self::ACTION_CREATED => 'Usuário criado',
            self::ACTION_UPDATED => 'Dados atualizados',
            self::ACTION_DELETED => 'Usuário excluído',
            self::ACTION_PASSWORD_RESET => 'Senha resetada',
            self::ACTION_PASSWORD_CHANGED => 'Senha alterada',
            self::ACTION_ROLE_CHANGED => 'Perfil alterado',
            self::ACTION_STATUS_CHANGED => 'Status alterado',
            self::ACTION_LOGIN => 'Login realizado',
            self::ACTION_LOGOUT => 'Logout realizado',
            self::ACTION_LOGIN_FAILED => 'Tentativa de login falhou',
        ];

        return $descriptions[$this->action] ?? $this->action;
    }

    /**
     * Retorna a cor do badge para a ação
     */
    public function getActionColorAttribute(): string
    {
        $colors = [
            self::ACTION_CREATED => 'success',
            self::ACTION_UPDATED => 'info',
            self::ACTION_DELETED => 'error',
            self::ACTION_PASSWORD_RESET => 'warning',
            self::ACTION_PASSWORD_CHANGED => 'primary',
            self::ACTION_ROLE_CHANGED => 'secondary',
            self::ACTION_STATUS_CHANGED => 'secondary',
            self::ACTION_LOGIN => 'success',
            self::ACTION_LOGOUT => 'info',
            self::ACTION_LOGIN_FAILED => 'error',
        ];

        return $colors[$this->action] ?? 'default';
    }

    /**
     * Retorna o ícone para a ação
     */
    public function getActionIconAttribute(): string
    {
        $icons = [
            self::ACTION_CREATED => 'tabler-user-plus',
            self::ACTION_UPDATED => 'tabler-edit',
            self::ACTION_DELETED => 'tabler-trash',
            self::ACTION_PASSWORD_RESET => 'tabler-key',
            self::ACTION_PASSWORD_CHANGED => 'tabler-lock',
            self::ACTION_ROLE_CHANGED => 'tabler-crown',
            self::ACTION_STATUS_CHANGED => 'tabler-toggle-right',
            self::ACTION_LOGIN => 'tabler-login',
            self::ACTION_LOGOUT => 'tabler-logout',
            self::ACTION_LOGIN_FAILED => 'tabler-alert-triangle',
        ];

        return $icons[$this->action] ?? 'tabler-activity';
    }
}
