<?php

namespace Tests\Concerns;

use App\Models\Conta;
use App\Models\Oficina;
use App\Models\User;
use Illuminate\Support\Str;

trait AutenticaUsuario
{
    /**
     * Cria uma oficina e um usuário autenticado dela, e retorna
     * [usuario, headers] prontos para uso em requisições de teste às
     * rotas protegidas. Sem oficina, nenhum dado de Cliente/Veiculo/etc.
     * apareceria (a trait PertenceAOficina falha fechada).
     *
     * @return array{0: User, 1: array<string, string>}
     */
    protected function autenticar(?Oficina $oficina = null): array
    {
        $token = Str::random(60);

        $usuario = User::factory()->create([
            'api_token' => hash('sha256', $token),
            'oficina_id' => ($oficina ?? Oficina::factory()->create())->id,
        ]);

        // Além do middleware (que faz isso a cada request), já deixamos
        // vinculado agora: qualquer fixture criada em seguida no teste
        // (Cliente::factory()->create(), etc.) já nasce na oficina certa.
        app()->instance('oficina.atual', $usuario->oficina_id ?? 0);

        return [$usuario, ['Authorization' => "Bearer {$token}"]];
    }

    /**
     * Cria uma conta (pessoa ou frota) e um usuário autenticado dela, sem
     * oficina. Também vincula a conta para as fixtures criadas em seguida.
     *
     * @return array{0: User, 1: array<string, string>}
     */
    protected function autenticarConta(string $perfil = 'pessoa', ?Conta $conta = null): array
    {
        $token = Str::random(60);

        $conta ??= Conta::create(['tipo' => $perfil, 'nome' => "Conta {$perfil} " . Str::random(4)]);

        $usuario = User::factory()->create([
            'api_token' => hash('sha256', $token),
            'oficina_id' => null,
            'perfil' => $perfil,
            'conta_id' => $conta->id,
        ]);

        app()->instance('conta.atual', $conta->id);
        app()->instance('oficina.atual', 0);

        return [$usuario, ['Authorization' => "Bearer {$token}"]];
    }
}
