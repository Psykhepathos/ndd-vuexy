<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = [
        'name',
        'display_name',
        'description',
        'color',
        'icon',
        'is_system',
        'is_active',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Permissões associadas a este perfil
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions')
            ->withTimestamps();
    }

    /**
     * Usuários com este perfil
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Verifica se o perfil tem uma permissão específica
     */
    public function hasPermission(string $permissionName): bool
    {
        return $this->permissions()->where('name', $permissionName)->exists();
    }

    /**
     * Verifica se o perfil tem permissão para uma ação em um módulo
     */
    public function can(string $module, string $action): bool
    {
        return $this->hasPermission("{$module}.{$action}");
    }

    /**
     * Sincroniza as permissões do perfil
     */
    public function syncPermissions(array $permissionIds): void
    {
        $this->permissions()->sync($permissionIds);
    }

    /**
     * Adiciona permissões ao perfil
     */
    public function givePermissions(array $permissionIds): void
    {
        $this->permissions()->attach($permissionIds);
    }

    /**
     * Remove permissões do perfil
     */
    public function revokePermissions(array $permissionIds): void
    {
        $this->permissions()->detach($permissionIds);
    }

    /**
     * Retorna as permissões agrupadas por módulo
     */
    public function getPermissionsGroupedByModule(): array
    {
        return $this->permissions
            ->groupBy('module')
            ->map(fn ($perms) => $perms->pluck('action')->toArray())
            ->toArray();
    }
}
