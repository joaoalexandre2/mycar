<?php

namespace Tests\Feature;

use App\Mail\RedefinirSenhaEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_solicitar_redefinicao_envia_email_para_conta_existente(): void
    {
        Mail::fake();
        $usuario = User::factory()->create(['email' => 'joao@exemplo.com']);

        $resposta = $this->postJson('/api/password/esqueci', ['email' => 'joao@exemplo.com']);

        $resposta->assertStatus(200);
        Mail::assertSent(RedefinirSenhaEmail::class, fn ($mail) => $mail->hasTo($usuario->email));
    }

    public function test_solicitar_redefinicao_para_email_inexistente_nao_revela_nada(): void
    {
        Mail::fake();

        $resposta = $this->postJson('/api/password/esqueci', ['email' => 'naoexiste@exemplo.com']);

        $resposta->assertStatus(200);
        Mail::assertNothingSent();
    }

    public function test_redefinir_com_token_valido_troca_a_senha(): void
    {
        $usuario = User::factory()->create(['password' => Hash::make('senha-antiga-123')]);

        $token = Password::createToken($usuario);

        $resposta = $this->postJson('/api/password/redefinir', [
            'token' => $token,
            'email' => $usuario->email,
            'password' => 'senha-nova-456',
            'password_confirmation' => 'senha-nova-456',
        ]);

        $resposta->assertStatus(200);

        $this->assertTrue(Hash::check('senha-nova-456', $usuario->fresh()->password));

        $this->postJson('/api/login', [
            'email' => $usuario->email,
            'password' => 'senha-antiga-123',
        ])->assertStatus(422);

        $this->postJson('/api/login', [
            'email' => $usuario->email,
            'password' => 'senha-nova-456',
        ])->assertStatus(200);
    }

    public function test_redefinir_com_token_invalido_falha(): void
    {
        $usuario = User::factory()->create();

        $resposta = $this->postJson('/api/password/redefinir', [
            'token' => 'token-invalido',
            'email' => $usuario->email,
            'password' => 'senha-nova-456',
            'password_confirmation' => 'senha-nova-456',
        ]);

        $resposta->assertStatus(422);
    }
}
