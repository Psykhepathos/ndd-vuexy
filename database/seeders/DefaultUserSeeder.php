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
        // Admin com senha forte: Admin@123
        // Atende: min:8, lowercase, uppercase, number, special char
        // updateOrCreate garante que role seja sempre 'admin' mesmo se user já existir
        User::updateOrCreate(
            ['email' => 'admin@ndd.com'],
            [
                'name' => 'Administrador NDD',
                'password' => Hash::make('Admin@123'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );

        // Usuario comum com senha forte: User@123
        User::updateOrCreate(
            ['email' => 'user@ndd.com'],
            [
                'name' => 'Usuario NDD',
                'password' => Hash::make('User@123'),
                'role' => 'user',
                'email_verified_at' => now(),
            ]
        );
    }
}