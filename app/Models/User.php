<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'role_id',
        'status',
        'password_reset_required',
        'password_changed_at',
        'setup_token',
        'setup_token_expires_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'setup_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_reset_required' => 'boolean',
            'password_changed_at' => 'datetime',
            'setup_token_expires_at' => 'datetime',
        ];
    }

    /**
     * Relacionamento com logs de auditoria
     */
    public function auditLogs()
    {
        return $this->hasMany(UserAuditLog::class, 'user_id');
    }

    /**
     * Logs de auditoria realizados por este usuário
     */
    public function performedAuditLogs()
    {
        return $this->hasMany(UserAuditLog::class, 'performed_by');
    }

    /**
     * Perfil do usuário (novo sistema)
     */
    public function roleRelation()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * Solicitações de reset de senha
     */
    public function passwordResetRequests()
    {
        return $this->hasMany(PasswordResetRequest::class);
    }

    /**
     * Verifica se o usuário tem uma permissão específica
     */
    public function hasPermission(string $permissionName): bool
    {
        // Admin tem todas as permissões
        if ($this->role === 'admin') {
            return true;
        }

        // Verifica pelo perfil associado
        if ($this->roleRelation) {
            return $this->roleRelation->hasPermission($permissionName);
        }

        return false;
    }

    /**
     * Verifica se o usuário pode realizar uma ação em um módulo
     */
    public function can($ability, $arguments = []): bool
    {
        // Se for uma permissão no formato "modulo.acao"
        if (is_string($ability) && str_contains($ability, '.')) {
            return $this->hasPermission($ability);
        }

        // Delega para o método pai para outras verificações
        return parent::can($ability, $arguments);
    }

    /**
     * Retorna as permissões do usuário
     */
    public function getPermissions(): array
    {
        if ($this->role === 'admin') {
            return Permission::where('is_active', true)->pluck('name')->toArray();
        }

        if ($this->roleRelation) {
            return $this->roleRelation->permissions()->pluck('name')->toArray();
        }

        return [];
    }

    /**
     * Verifica se precisa definir senha (primeiro acesso)
     */
    public function needsPasswordSetup(): bool
    {
        return empty($this->password) || $this->password_reset_required;
    }

    /**
     * Mutator para validar role antes de salvar
     *
     * @param string $value
     * @return void
     * @throws \InvalidArgumentException
     */
    public function setRoleAttribute($value): void
    {
        $validRoles = ['admin', 'user'];

        if (!in_array($value, $validRoles, true)) {
            throw new \InvalidArgumentException(
                "Role inválido: '$value'. Valores aceitos: " . implode(', ', $validRoles)
            );
        }

        $this->attributes['role'] = $value;
    }
}
