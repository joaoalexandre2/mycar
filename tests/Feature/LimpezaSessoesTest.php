<?php

namespace Tests\Feature;

use App\Models\TokenAcesso;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sessões esquecidas já são recusadas pelo middleware; a limpeza agendada só
 * tira as linhas mortas da tabela tokens_acesso.
 */
class LimpezaSessoesTest extends TestCase
{
    use RefreshDatabase;

    private function sessao(User $usuario, string $nome, ?int $diasSemUso, ?int $criadaHaDias = null): TokenAcesso
    {
        $sessao = $usuario->tokensAcesso()->create([
            'token_hash' => hash('sha256', $nome),
            'dispositivo' => $nome,
            'ultimo_uso_em' => $diasSemUso === null ? null : now()->subDays($diasSemUso),
        ]);

        if ($criadaHaDias !== null) {
            $sessao->forceFill(['created_at' => now()->subDays($criadaHaDias)])->saveQuietly();
        }

        return $sessao;
    }

    private function limpar(): void
    {
        $this->artisan('model:prune', ['--model' => [TokenAcesso::class]])->assertExitCode(0);
    }

    public function test_apaga_so_as_sessoes_sem_uso_alem_do_prazo(): void
    {
        $usuario = User::factory()->create();

        $esquecida = $this->sessao($usuario, 'esquecida', TokenAcesso::DIAS_SEM_USO + 1);
        $velhissima = $this->sessao($usuario, 'velhissima', 400);
        $naBorda = $this->sessao($usuario, 'na-borda', TokenAcesso::DIAS_SEM_USO - 1);
        $recente = $this->sessao($usuario, 'recente', 0);

        $this->limpar();

        $this->assertModelMissing($esquecida);
        $this->assertModelMissing($velhissima);
        $this->assertModelExists($naBorda);
        $this->assertModelExists($recente);
    }

    public function test_sessao_sem_registro_de_uso_conta_pela_data_de_criacao(): void
    {
        $usuario = User::factory()->create();

        $antiga = $this->sessao($usuario, 'sem-uso-antiga', null, TokenAcesso::DIAS_SEM_USO + 5);
        $nova = $this->sessao($usuario, 'sem-uso-nova', null, 2);

        $this->limpar();

        $this->assertModelMissing($antiga);
        $this->assertModelExists($nova);
    }

    public function test_nao_mexe_nas_sessoes_de_outros_usuarios_nem_nos_usuarios(): void
    {
        $dono = User::factory()->create();
        $outro = User::factory()->create();

        $this->sessao($dono, 'velha', 200);
        $viva = $this->sessao($outro, 'viva', 1);

        $this->limpar();

        $this->assertModelExists($viva);
        $this->assertDatabaseHas('users', ['id' => $dono->id]);
        $this->assertDatabaseHas('users', ['id' => $outro->id]);
    }

    public function test_sessao_apagada_pela_limpeza_continua_recusada(): void
    {
        $usuario = User::factory()->create();
        $usuario->tokensAcesso()->create([
            'token_hash' => hash('sha256', 'token-esquecido'),
            'dispositivo' => 'Celular',
            'ultimo_uso_em' => now()->subDays(TokenAcesso::DIAS_SEM_USO + 3),
        ]);

        $this->limpar();

        $this->assertSame(0, TokenAcesso::count());
        $this->getJson('/api/me', ['Authorization' => 'Bearer token-esquecido'])
            ->assertStatus(401);
    }

    public function test_a_limpeza_esta_agendada_todo_dia(): void
    {
        $eventos = collect(app(Schedule::class)->events())
            ->filter(fn ($evento) => str_contains((string) $evento->command, 'model:prune'));

        $this->assertCount(1, $eventos);

        $evento = $eventos->first();

        $this->assertStringContainsString(TokenAcesso::class, (string) $evento->command);
        // Roda uma vez por dia (minuto e hora fixos, qualquer dia).
        $this->assertSame('30 3 * * *', $evento->expression);
    }
}
