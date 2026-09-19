<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_com_credenciais_validas_retorna_token(): void
    {
        $usuario = User::factory()->create([
            'password' => Hash::make('senha-correta'),
        ]);

        $resposta = $this->postJson('/api/login', [
            'email' => $usuario->email,
            'password' => 'senha-correta',
        ]);

        $resposta->assertStatus(200)
            ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token'])
            ->assertJsonPath('user.email', $usuario->email);
    }

    public function test_login_com_senha_errada_falha(): void
    {
        $usuario = User::factory()->create([
            'password' => Hash::make('senha-correta'),
        ]);

        $resposta = $this->postJson('/api/login', [
            'email' => $usuario->email,
            'password' => 'senha-errada',
        ]);

        $resposta->assertStatus(422);
    }

    public function test_login_com_email_inexistente_falha(): void
    {
        $resposta = $this->postJson('/api/login', [
            'email' => 'naoexiste@mycar.local',
            'password' => 'qualquer-coisa',
        ]);

        $resposta->assertStatus(422);
    }

    public function test_rota_protegida_sem_token_retorna_401(): void
    {
        $this->getJson('/api/clientes')->assertStatus(401);
    }

    public function test_rota_protegida_com_token_invalido_retorna_401(): void
    {
        $this->getJson('/api/clientes', [
            'Authorization' => 'Bearer token-que-nao-existe',
        ])->assertStatus(401);
    }

    public function test_rota_protegida_com_token_valido_funciona(): void
    {
        $token = Str::random(60);

        User::factory()->create([
            'api_token' => hash('sha256', $token),
        ]);

        $this->getJson('/api/clientes', [
            'Authorization' => "Bearer {$token}",
        ])->assertStatus(200);
    }

    public function test_logout_revoga_o_token(): void
    {
        $token = Str::random(60);

        User::factory()->create([
            'api_token' => hash('sha256', $token),
        ]);

        $headers = ['Authorization' => "Bearer {$token}"];

        $this->postJson('/api/logout', [], $headers)->assertStatus(200);

        $this->getJson('/api/clientes', $headers)->assertStatus(401);
    }

    public function test_me_retorna_usuario_autenticado(): void
    {
        $token = Str::random(60);

        $usuario = User::factory()->create([
            'api_token' => hash('sha256', $token),
        ]);

        $this->getJson('/api/me', ['Authorization' => "Bearer {$token}"])
            ->assertStatus(200)
            ->assertJsonPath('email', $usuario->email);
    }
}
