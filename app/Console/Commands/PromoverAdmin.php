<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class PromoverAdmin extends Command
{
    protected $signature = 'admin:promover {email : E-mail do usuário} {--remover : Retira o acesso em vez de conceder}';

    protected $description = 'Concede (ou retira) o acesso ao painel do operador da plataforma';

    public function handle(): int
    {
        $usuario = User::where('email', $this->argument('email'))->first();

        if (!$usuario) {
            $this->error('Nenhum usuário com esse e-mail.');

            return self::FAILURE;
        }

        $conceder = !$this->option('remover');

        // forceFill: is_super_admin não é "fillable" de propósito.
        $usuario->forceFill(['is_super_admin' => $conceder])->save();

        $this->info($conceder
            ? "{$usuario->name} <{$usuario->email}> agora é administrador da plataforma."
            : "{$usuario->name} <{$usuario->email}> não é mais administrador da plataforma.");

        return self::SUCCESS;
    }
}
