<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $fillable = [
        'module',
        'action',
        'name',
        'display_name',
        'description',
        'group',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Perfis que têm esta permissão
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions')
            ->withTimestamps();
    }

    /**
     * Cria ou atualiza uma permissão
     */
    public static function createOrUpdate(
        string $module,
        string $action,
        string $displayName,
        ?string $description = null,
        ?string $group = null,
        int $sortOrder = 0
    ): self {
        return self::updateOrCreate(
            ['name' => "{$module}.{$action}"],
            [
                'module' => $module,
                'action' => $action,
                'display_name' => $displayName,
                'description' => $description,
                'group' => $group,
                'sort_order' => $sortOrder,
            ]
        );
    }

    /**
     * Retorna permissões agrupadas por módulo
     */
    public static function getGroupedByModule(): array
    {
        return self::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('module')
            ->get()
            ->groupBy('module')
            ->toArray();
    }

    /**
     * Retorna permissões agrupadas por grupo (para UI)
     */
    public static function getGroupedByGroup(): array
    {
        return self::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('group')
            ->get()
            ->groupBy('group')
            ->toArray();
    }
}
