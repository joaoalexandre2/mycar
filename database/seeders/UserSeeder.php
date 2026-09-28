<?php

namespace Database\Seeders;

use App\Models\Oficina;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Cria uma oficina e um usuário administrador padrão, caso ainda não
     * existam. Pode ser rodado com segurança em um banco que já tem dados:
     * `php artisan db:seed --class=UserSeeder`.
     */
    public function run(): void
    {
        if (User::where('email', 'admin@mycar.local')->exists()) {
            return;
        }

        $oficina = Oficina::firstOrCreate(['nome' => 'Oficina Demo']);

        // email_verified_at não é mass assignable (de propósito, para não
        // poder ser setado por engano via input de usuário em outro lugar),
        // então precisa de forceFill em vez de create().
        User::create([
            'oficina_id' => $oficina->id,
            'name' => 'Administrador',
            'email' => 'admin@mycar.local',
            'password' => Hash::make('mycar@123'),
        ])->forceFill(['email_verified_at' => now()])->save();
    }
}
