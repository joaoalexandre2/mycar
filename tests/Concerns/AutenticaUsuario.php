<?php

namespace Tests\Concerns;

use App\Models\User;
use Illuminate\Support\Str;

trait AutenticaUsuario
{
    /**
     * Cria um usuário autenticado e retorna [usuario, headers] prontos
     * para uso em requisições de teste às rotas protegidas.
     *
     * @return array{0: User, 1: array<string, string>}
     */
    protected function autenticar(): array
    {
        $token = Str::random(60);

        $usuario = User::factory()->create([
            'api_token' => hash('sha256', $token),
        ]);

        return [$usuario, ['Authorization' => "Bearer {$token}"]];
    }
}
