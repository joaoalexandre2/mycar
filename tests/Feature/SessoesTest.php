<?php

namespace Tests\Feature;

use App\Models\TokenAcesso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class SessoesTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(): User
    {
        return User::factory()->create([
            'password' => Hash::make('senha-segura-123'),
            'email_verified_at' => now(),
        ]);
    }

    /** @return array<string, string> */
    private function entrar(User $usuario, string $aparelho): array
    {
        $r = $this->withHeaders(['User-Agent' => $aparelho])
            ->postJson('/api/login', ['email' => $usuario->email, 'password' => 'senha-segura-123'])
            ->assertStatus(200);

        return ['Authorization' => 'Bearer '.$r->json('token')];
    }

    public function test_entrar_no_computador_nao_derruba_o_celular(): void
    {
        $usuario = $this->usuario();

        $celular = $this->entrar($usuario, 'Mozilla/5.0 (iPhone)');
        $computador = $this->entrar($usuario, 'Mozilla/5.0 (Windows NT 10.0)');

        $this->getJson('/api/me', $celular)->assertStatus(200)->assertJsonPath('id', $usuario->id);
        $this->getJson('/api/me', $computador)->assertStatus(200)->assertJsonPath('id', $usuario->id);

        $this->assertSame(2, TokenAcesso::where('user_id', $usuario->id)->count());
        $this->assertNotSame($celular, $computador);
    }

    public function test_guarda_so_o_hash_do_token_e_o_aparelho(): void
    {
        $usuario = $this->usuario();
        $headers = $this->entrar($usuario, 'Mozilla/5.0 (iPhone)');
        $token = str_replace('Bearer ', '', $headers['Authorization']);

        $acesso = TokenAcesso::where('user_id', $usuario->id)->first();

        $this->assertSame(hash('sha256', $token), $acesso->token_hash);
        $this->assertStringNotContainsString($token, json_encode($acesso->toArray()));
        $this->assertSame('Mozilla/5.0 (iPhone)', $acesso->dispositivo);
        $this->assertNotNull($acesso->ultimo_uso_em);
    }

    public function test_sair_encerra_so_a_sessao_deste_aparelho(): void
    {
        $usuario = $this->usuario();
        $celular = $this->entrar($usuario, 'iPhone');
        $computador = $this->entrar($usuario, 'Windows');

        $this->postJson('/api/logout', [], $computador)->assertStatus(200);

        $this->getJson('/api/me', $computador)->assertStatus(401);
        $this->getJson('/api/me', $celular)->assertStatus(200);
    }

    public function test_sessao_antiga_pelo_campo_api_token_continua_valendo_ate_o_logout(): void
    {
        $usuario = $this->usuario();
        $usuario->forceFill(['api_token' => hash('sha256', 'token-antigo-de-antes-da-mudanca')])->save();
        $antiga = ['Authorization' => 'Bearer token-antigo-de-antes-da-mudanca'];

        $this->getJson('/api/me', $antiga)->assertStatus(200);

        // Entrar em outro aparelho não derruba a sessão antiga.
        $novo = $this->entrar($usuario, 'Windows');
        $this->getJson('/api/me', $antiga)->assertStatus(200);
        $this->getJson('/api/me', $novo)->assertStatus(200);

        $this->postJson('/api/logout', [], $antiga)->assertStatus(200);
        $this->getJson('/api/me', $antiga)->assertStatus(401);
        $this->getJson('/api/me', $novo)->assertStatus(200);
    }

    public function test_token_invalido_continua_barrado(): void
    {
        $this->getJson('/api/me', ['Authorization' => 'Bearer qualquer-coisa'])->assertStatus(401);
        $this->getJson('/api/me')->assertStatus(401);
    }

    public function test_trocar_a_senha_derruba_os_outros_aparelhos_mas_nao_este(): void
    {
        $usuario = $this->usuario();
        $celular = $this->entrar($usuario, 'iPhone');
        $computador = $this->entrar($usuario, 'Windows');

        $this->putJson('/api/me/senha', [
            'senha_atual' => 'senha-segura-123', 'password' => 'outra-senha-456', 'password_confirmation' => 'outra-senha-456',
        ], $computador)->assertStatus(200);

        $this->getJson('/api/me', $computador)->assertStatus(200);
        $this->getJson('/api/me', $celular)->assertStatus(401);
    }

    public function test_redefinir_a_senha_derruba_todas_as_sessoes(): void
    {
        $usuario = $this->usuario();
        $celular = $this->entrar($usuario, 'iPhone');
        $computador = $this->entrar($usuario, 'Windows');

        $this->postJson('/api/password/redefinir', [
            'token' => Password::createToken($usuario), 'email' => $usuario->email,
            'password' => 'nova-senha-789', 'password_confirmation' => 'nova-senha-789',
        ])->assertStatus(200);

        $this->getJson('/api/me', $celular)->assertStatus(401);
        $this->getJson('/api/me', $computador)->assertStatus(401);
        $this->assertSame(0, TokenAcesso::where('user_id', $usuario->id)->count());
    }

    public function test_limite_de_aparelhos_descarta_o_menos_usado(): void
    {
        $usuario = $this->usuario();
        $primeiro = $this->entrar($usuario, 'Aparelho 1');
        TokenAcesso::where('user_id', $usuario->id)->update(['ultimo_uso_em' => now()->subDays(30)]);

        // Completa o limite direto no banco (o login tem limite de requisições por minuto).
        for ($i = 2; $i <= TokenAcesso::LIMITE_POR_USUARIO; $i++) {
            TokenAcesso::create([
                'user_id' => $usuario->id, 'token_hash' => hash('sha256', "aparelho-{$i}"),
                'dispositivo' => "Aparelho {$i}", 'ultimo_uso_em' => now()->subMinutes($i),
            ]);
        }

        $this->assertSame(TokenAcesso::LIMITE_POR_USUARIO, TokenAcesso::where('user_id', $usuario->id)->count());
        $this->entrar($usuario, 'Aparelho novo');

        $this->assertSame(TokenAcesso::LIMITE_POR_USUARIO, TokenAcesso::where('user_id', $usuario->id)->count());
        $this->getJson('/api/me', $primeiro)->assertStatus(401); // o mais antigo saiu
    }

    public function test_sessao_sem_uso_ha_muito_tempo_e_descartada_no_proximo_login(): void
    {
        $usuario = $this->usuario();
        $esquecida = $this->entrar($usuario, 'Aparelho velho');
        TokenAcesso::where('user_id', $usuario->id)->update(['ultimo_uso_em' => now()->subDays(TokenAcesso::DIAS_SEM_USO + 1)]);

        $this->entrar($usuario, 'Aparelho novo');

        $this->getJson('/api/me', $esquecida)->assertStatus(401);
        $this->assertSame(1, TokenAcesso::where('user_id', $usuario->id)->count());
    }

    public function test_cada_pessoa_so_ve_a_propria_sessao(): void
    {
        $ana = $this->usuario();
        $bia = $this->usuario();
        $tokenAna = $this->entrar($ana, 'iPhone');
        $this->entrar($bia, 'Windows');

        $this->getJson('/api/me', $tokenAna)->assertJsonPath('id', $ana->id);
        // Sair da Ana não encerra a Bia.
        $this->postJson('/api/logout', [], $tokenAna)->assertStatus(200);
        $this->assertSame(1, TokenAcesso::where('user_id', $bia->id)->count());
    }
}
