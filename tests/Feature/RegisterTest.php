<?php

namespace Tests\Feature;

use App\Mail\ConfirmeSeuEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_cadastro_cria_oficina_e_usuario_e_envia_email(): void
    {
        Mail::fake();

        $resposta = $this->postJson('/api/register', [
            'nome_oficina' => 'Oficina do João',
            'name' => 'João',
            'email' => 'joao@exemplo.com',
            'password' => 'senha-forte-123',
            'password_confirmation' => 'senha-forte-123',
        ]);

        $resposta->assertStatus(201);

        $this->assertDatabaseHas('oficinas', ['nome' => 'Oficina do João']);
        $this->assertDatabaseHas('users', ['email' => 'joao@exemplo.com', 'email_verified_at' => null]);

        Mail::assertSent(ConfirmeSeuEmail::class, function ($mail) {
            return $mail->hasTo('joao@exemplo.com');
        });
    }

    public function test_nao_deixa_login_sem_confirmar_email(): void
    {
        Mail::fake();

        $this->postJson('/api/register', [
            'nome_oficina' => 'Oficina do João',
            'name' => 'João',
            'email' => 'joao@exemplo.com',
            'password' => 'senha-forte-123',
            'password_confirmation' => 'senha-forte-123',
        ])->assertStatus(201);

        $this->postJson('/api/login', [
            'email' => 'joao@exemplo.com',
            'password' => 'senha-forte-123',
        ])->assertStatus(422);
    }

    public function test_confirmar_email_com_link_valido_permite_login(): void
    {
        Mail::fake();

        $this->postJson('/api/register', [
            'nome_oficina' => 'Oficina do João',
            'name' => 'João',
            'email' => 'joao@exemplo.com',
            'password' => 'senha-forte-123',
            'password_confirmation' => 'senha-forte-123',
        ]);

        $usuario = User::where('email', 'joao@exemplo.com')->firstOrFail();

        $url = URL::temporarySignedRoute(
            'verificacao.confirmar',
            now()->addHours(24),
            ['id' => $usuario->id, 'hash' => sha1($usuario->email)]
        );

        $this->get($url)->assertRedirect();

        $this->assertNotNull($usuario->fresh()->email_verified_at);

        $this->postJson('/api/login', [
            'email' => 'joao@exemplo.com',
            'password' => 'senha-forte-123',
        ])->assertStatus(200);
    }

    public function test_link_de_confirmacao_sem_assinatura_valida_e_rejeitado(): void
    {
        Mail::fake();

        $this->postJson('/api/register', [
            'nome_oficina' => 'Oficina do João',
            'name' => 'João',
            'email' => 'joao@exemplo.com',
            'password' => 'senha-forte-123',
            'password_confirmation' => 'senha-forte-123',
        ]);

        $usuario = User::where('email', 'joao@exemplo.com')->firstOrFail();

        $this->get("/api/email/verificar/{$usuario->id}/" . sha1($usuario->email))
            ->assertStatus(403);

        $this->assertNull($usuario->fresh()->email_verified_at);
    }

    public function test_nao_permite_cadastrar_email_ja_usado(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'ja-existe@exemplo.com']);

        $this->postJson('/api/register', [
            'nome_oficina' => 'Outra Oficina',
            'name' => 'Fulano',
            'email' => 'ja-existe@exemplo.com',
            'password' => 'senha-forte-123',
            'password_confirmation' => 'senha-forte-123',
        ])->assertStatus(422);
    }

    public function test_reenviar_confirmacao_nao_revela_se_email_existe(): void
    {
        Mail::fake();

        $resposta = $this->postJson('/api/email/reenviar', [
            'email' => 'naoexiste@exemplo.com',
        ]);

        $resposta->assertStatus(200);
        Mail::assertNothingSent();
    }
}
