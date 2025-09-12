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
        User::firstOrCreate([
            'email' => 'admin@ndd.com'
        ], [
            'name' => 'Administrador NDD',
            'email' => 'admin@ndd.com',
            'password' => Hash::make('123456'),
            'email_verified_at' => now(),
        ]);

        User::firstOrCreate([
            'email' => 'user@ndd.com'
        ], [
            'name' => 'Usuario NDD',
            'email' => 'user@ndd.com', 
            'password' => Hash::make('123456'),
            'email_verified_at' => now(),
        ]);
    }
}