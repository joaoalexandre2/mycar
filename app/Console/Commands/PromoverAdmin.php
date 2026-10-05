<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PromoverAdmin extends Command
{
    protected $signature = 'admin:promover
        {email : E-mail do usuário}
        {--remover : Retira o acesso em vez de conceder}
        {--force : Concede sem perguntar (obrigatório fora de um terminal interativo, como o ssh sem tty)}';

    protected $description = 'Concede (ou retira) o acesso ao painel do operador da plataforma';

    public function handle(): int
    {
        $usuario = User::where('email', $this->argument('email'))->first();

        if (!$usuario) {
            $this->error('Nenhum usuário com esse e-mail.');

            return self::FAILURE;
        }

        $conceder = !$this->option('remover');

        // Conceder é o lado perigoso: o administrador enxerga todas as contas.
        // Por isso pede confirmação (padrão: não). Sem terminal interativo a
        // resposta é o padrão, ou seja, não concede; é preciso --force de propósito.
        // Isso barra o caso em que o Laravel sugere "admin:promover" no lugar de
        // um comando digitado errado (ou ainda não publicado) e a pergunta é
        // respondida sem querer. Retirar o acesso nunca pede confirmação.
        if ($conceder && !$this->option('force')) {
            $confirmou = $this->confirm(
                "Tornar {$usuario->name} <{$usuario->email}> ADMINISTRADOR da plataforma? Essa pessoa passa a ver todas as contas cadastradas.",
                false
            );

            if (!$confirmou) {
                $this->warn('Cancelado: nada foi alterado.');

                return self::FAILURE;
            }
        }

        // forceFill: is_super_admin não é "fillable" de propósito.
        $usuario->forceFill(['is_super_admin' => $conceder])->save();

        Log::warning($conceder ? 'Administrador da plataforma concedido' : 'Administrador da plataforma retirado', [
            'usuario_id' => $usuario->id,
            'email' => $usuario->email,
        ]);

        $this->info($conceder
            ? "{$usuario->name} <{$usuario->email}> agora é administrador da plataforma."
            : "{$usuario->name} <{$usuario->email}> não é mais administrador da plataforma.");

        return self::SUCCESS;
    }
}
