<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Ordem de execução importante:
     * 1. PermissionSeeder - Cria permissões e perfis (roles)
     * 2. DefaultUserSeeder - Cria usuários admin e user com roles corretos
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            DefaultUserSeeder::class,
        ]);
    }
}
