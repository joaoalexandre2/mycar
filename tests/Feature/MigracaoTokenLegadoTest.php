<?php

namespace Tests\Feature;

use App\Models\TokenAcesso;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * A migration que tira users.api_token precisa preservar quem ainda tem uma
 * sessão aberta pelo campo antigo: o mesmo token passa a valer como sessão de
 * aparelho, e o campo some.
 */
class MigracaoTokenLegadoTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_10_21_000000_remove_api_token_legado_dos_usuarios.php');
    }

    /** Volta o banco ao estado de antes: users com a coluna api_token. */
    private function recriarColuna(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('api_token', 64)->unique()->nullable();
        });
    }

    public function test_o_campo_nao_existe_mais_nos_usuarios(): void
    {
        $this->assertFalse(Schema::hasColumn('users', 'api_token'));
    }

    public function test_token_legado_vira_sessao_de_aparelho_e_continua_valendo(): void
    {
        $this->recriarColuna();

        $usuario = User::factory()->create();
        $hash = hash('sha256', 'token-de-antes-da-mudanca');
        DB::table('users')->where('id', $usuario->id)->update([
            'api_token' => $hash,
            'ultimo_acesso_em' => now()->subDays(3),
        ]);

        $this->migration()->up();

        $this->assertFalse(Schema::hasColumn('users', 'api_token'));

        $sessao = TokenAcesso::where('token_hash', $hash)->first();
        $this->assertNotNull($sessao);
        $this->assertSame($usuario->id, $sessao->user_id);
        $this->assertSame('Sessão anterior', $sessao->dispositivo);

        // O mesmo token segue autenticando.
        $this->getJson('/api/me', ['Authorization' => 'Bearer token-de-antes-da-mudanca'])
            ->assertStatus(200)
            ->assertJsonPath('email', $usuario->email);
    }

    public function test_quem_nao_tinha_token_legado_nao_ganha_sessao(): void
    {
        $this->recriarColuna();

        User::factory()->create();

        $this->migration()->up();

        $this->assertSame(0, TokenAcesso::count());
    }

    public function test_nao_duplica_sessao_que_ja_existe(): void
    {
        $this->recriarColuna();

        $usuario = User::factory()->create();
        $hash = hash('sha256', 'ja-migrado');
        DB::table('users')->where('id', $usuario->id)->update(['api_token' => $hash]);
        $usuario->tokensAcesso()->create([
            'token_hash' => $hash,
            'dispositivo' => 'Celular',
            'ultimo_uso_em' => now(),
        ]);

        $this->migration()->up();

        $this->assertSame(1, TokenAcesso::where('token_hash', $hash)->count());
    }

    public function test_token_legado_parado_ha_muito_tempo_expira_pela_regra_normal(): void
    {
        $this->recriarColuna();

        $usuario = User::factory()->create();
        $hash = hash('sha256', 'token-esquecido');
        DB::table('users')->where('id', $usuario->id)->update([
            'api_token' => $hash,
            'ultimo_acesso_em' => now()->subDays(TokenAcesso::DIAS_SEM_USO + 10),
        ]);

        $this->migration()->up();

        $this->getJson('/api/me', ['Authorization' => 'Bearer token-esquecido'])
            ->assertStatus(401);
    }

    public function test_a_migration_e_idempotente(): void
    {
        // Sem a coluna (estado atual), rodar de novo não faz nada nem quebra.
        $this->migration()->up();

        $this->assertFalse(Schema::hasColumn('users', 'api_token'));
    }
}
