<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DefaultUserSeeder extends Seeder
{
    public function run(): void
    {
        // Credenciais configuráveis via .env (com defaults para desenvolvimento)
        $adminEmail = env('SEED_ADMIN_EMAIL', 'admin@ndd.com');
        $adminPassword = env('SEED_ADMIN_PASSWORD', 'Admin@123');
        $userEmail = env('SEED_USER_EMAIL', 'user@ndd.com');
        $userPassword = env('SEED_USER_PASSWORD', 'User@123');

        // Admin com senha forte
        // Atende: min:8, lowercase, uppercase, number, special char
        // updateOrCreate garante que role seja sempre 'admin' mesmo se user já existir
        User::updateOrCreate(
            ['email' => $adminEmail],
            [
                'name' => 'Administrador NDD',
                'password' => Hash::make($adminPassword),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );

        // Usuario comum com senha forte
        User::updateOrCreate(
            ['email' => $userEmail],
            [
                'name' => 'Usuario NDD',
                'password' => Hash::make($userPassword),
                'role' => 'user',
                'email_verified_at' => now(),
            ]
        );
    }
}