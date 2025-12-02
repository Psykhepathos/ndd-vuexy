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
        User::firstOrCreate([
            'email' => 'admin@ndd.com'
        ], [
            'name' => 'Administrador NDD',
            'email' => 'admin@ndd.com',
            'password' => Hash::make('Admin@123'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        // Usuario comum com senha forte: User@123
        User::firstOrCreate([
            'email' => 'user@ndd.com'
        ], [
            'name' => 'Usuario NDD',
            'email' => 'user@ndd.com',
            'password' => Hash::make('User@123'),
            'role' => 'user',
            'email_verified_at' => now(),
        ]);
    }
}