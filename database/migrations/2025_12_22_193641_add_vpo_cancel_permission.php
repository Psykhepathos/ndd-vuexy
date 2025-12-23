<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Permissão de cancelamento VPO
     */
    private array $permission = [
        'module' => 'vpo_emissao',
        'action' => 'cancel',
        'name' => 'vpo_emissao.cancel',
        'display_name' => 'Cancelar emissões VPO',
        'description' => 'Permissão para cancelar emissões de Vale Pedágio na NDD Cargo',
        'group' => 'VPO',
        'sort_order' => 204,
        'is_active' => true,
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        // Verificar se já existe
        $exists = DB::table('permissions')->where('name', $this->permission['name'])->exists();

        if (!$exists) {
            DB::table('permissions')->insert(array_merge($this->permission, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        // Buscar ID da nova permissão
        $permissionId = DB::table('permissions')
            ->where('name', $this->permission['name'])
            ->value('id');

        // Buscar role admin
        $adminRole = DB::table('roles')->where('name', 'admin')->first();

        if ($adminRole && $permissionId) {
            // Atribuir permissão ao admin (se ainda não tiver)
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Buscar ID da permissão
        $permissionId = DB::table('permissions')
            ->where('name', $this->permission['name'])
            ->value('id');

        if ($permissionId) {
            // Remover relações primeiro
            DB::table('role_permissions')
                ->where('permission_id', $permissionId)
                ->delete();

            // Remover permissão
            DB::table('permissions')
                ->where('id', $permissionId)
                ->delete();
        }
    }
};
