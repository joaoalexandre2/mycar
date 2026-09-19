<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Cria um usuário administrador padrão, caso ainda não exista.
     * Pode ser rodado com segurança em um banco que já tem dados:
     * `php artisan db:seed --class=UserSeeder`.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@mycar.local'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('mycar@123'),
                'email_verified_at' => now(),
            ]
        );
    }
}
