<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Permissões de notificações a serem criadas
     */
    private array $permissions = [
        [
            'module' => 'notificacoes',
            'action' => 'view',
            'name' => 'notificacoes.view',
            'display_name' => 'Visualizar notificações',
            'description' => 'Permissão para visualizar e gerenciar notificações do sistema',
            'group' => 'Sistema',
            'sort_order' => 100,
            'is_active' => true,
        ],
        [
            'module' => 'notificacoes',
            'action' => 'create',
            'name' => 'notificacoes.create',
            'display_name' => 'Criar notificações',
            'description' => 'Permissão para criar novas notificações no sistema',
            'group' => 'Sistema',
            'sort_order' => 101,
            'is_active' => true,
        ],
        [
            'module' => 'notificacoes',
            'action' => 'edit',
            'name' => 'notificacoes.edit',
            'display_name' => 'Editar notificações',
            'description' => 'Permissão para editar notificações existentes',
            'group' => 'Sistema',
            'sort_order' => 102,
            'is_active' => true,
        ],
        [
            'module' => 'notificacoes',
            'action' => 'delete',
            'name' => 'notificacoes.delete',
            'display_name' => 'Excluir notificações',
            'description' => 'Permissão para excluir notificações do sistema',
            'group' => 'Sistema',
            'sort_order' => 103,
            'is_active' => true,
        ],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        // Criar as permissões
        foreach ($this->permissions as $permission) {
            // Verificar se já existe
            $exists = DB::table('permissions')->where('name', $permission['name'])->exists();

            if (!$exists) {
                DB::table('permissions')->insert(array_merge($permission, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }

        // Buscar IDs das novas permissões
        $permissionIds = DB::table('permissions')
            ->where('module', 'notificacoes')
            ->pluck('id')
            ->toArray();

        // Buscar role admin
        $adminRole = DB::table('roles')->where('name', 'admin')->first();

        if ($adminRole && !empty($permissionIds)) {
            // Atribuir permissões ao admin (se ainda não tiver)
            foreach ($permissionIds as $permissionId) {
                $exists = DB::table('role_permissions')
                    ->where('role_id', $adminRole->id)
                    ->where('permission_id', $permissionId)
                    ->exists();

                if (!$exists) {
                    DB::table('role_permissions')->insert([
                        'role_id' => $adminRole->id,
                        'permission_id' => $permissionId,
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remover relações primeiro
        $permissionIds = DB::table('permissions')
            ->where('module', 'notificacoes')
            ->pluck('id')
            ->toArray();

        if (!empty($permissionIds)) {
            DB::table('role_permissions')
                ->whereIn('permission_id', $permissionIds)
                ->delete();
        }

        // Remover permissões
        DB::table('permissions')
            ->where('module', 'notificacoes')
            ->delete();
    }
};
