<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SessaoCookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SessaoEmCookieTest extends TestCase
{
    use RefreshDatabase;

    private function usuarioConfirmado(): User
    {
        return User::factory()->create([
            'password' => Hash::make('senha-correta'),
        ]);
    }

    public function test_login_grava_o_token_em_cookie_http_only(): void
    {
        $usuario = $this->usuarioConfirmado();

        $resposta = $this->postJson('/api/login', [
            'email' => $usuario->email,
            'password' => 'senha-correta',
        ]);

        $resposta->assertOk();

        $cookie = collect($resposta->headers->getCookies())
            ->firstWhere(fn ($c) => $c->getName() === SessaoCookie::NOME);

        $this->assertNotNull($cookie, 'o login precisa devolver o cookie da sessão');
        $this->assertTrue($cookie->isHttpOnly(), 'o cookie não pode ser lido pelo JavaScript');
        $this->assertSame('lax', strtolower((string) $cookie->getSameSite()));
        $this->assertSame($resposta->json('token'), $cookie->getValue());
    }

    public function test_o_cookie_autentica_as_chamadas_seguintes(): void
    {
        $usuario = $this->usuarioConfirmado();

        $login = $this->postJson('/api/login', [
            'email' => $usuario->email,
            'password' => 'senha-correta',
        ]);

        $this->withCredentials()->withUnencryptedCookie(SessaoCookie::NOME, $login->json('token'))
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('email', $usuario->email);
    }

    public function test_sem_cookie_nem_bearer_a_api_recusa(): void
    {
        $this->getJson('/api/me')->assertStatus(401);
    }

    public function test_o_bearer_continua_funcionando_para_outros_clientes(): void
    {
        $usuario = $this->usuarioConfirmado();

        $login = $this->postJson('/api/login', [
            'email' => $usuario->email,
            'password' => 'senha-correta',
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $login->json('token'))
            ->getJson('/api/me')
            ->assertOk();
    }

    public function test_logout_apaga_o_cookie_e_a_sessao(): void
    {
        $usuario = $this->usuarioConfirmado();

        $login = $this->postJson('/api/login', [
            'email' => $usuario->email,
            'password' => 'senha-correta',
        ]);
        $token = $login->json('token');

        $this->withCredentials()->withUnencryptedCookie(SessaoCookie::NOME, $token)
            ->postJson('/api/logout')
            ->assertOk()
            ->assertCookieExpired(SessaoCookie::NOME);

        // Mesmo token, já encerrado: não autentica mais.
        $this->withCredentials()->withUnencryptedCookie(SessaoCookie::NOME, $token)
            ->getJson('/api/me')
            ->assertStatus(401);
    }
}
