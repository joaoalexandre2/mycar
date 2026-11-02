<?php

namespace Tests\Feature;

use App\Models\Conta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Perfil Estética (produto vendido à parte): cadastro cria uma conta própria do tipo estetica. */
class EsteticaFundacaoTest extends TestCase
{
    use RefreshDatabase;

    private function cadastro(array $extra = []): array
    {
        return array_merge([
            'name' => 'Ana Souza',
            'nome_estetica' => 'Lava Jato Brilho',
            'email' => 'estetica@exemplo.com',
            'password' => 'senha-forte-123',
            'password_confirmation' => 'senha-forte-123',
        ], $extra);
    }

    public function test_cadastro_de_estetica_cria_conta_do_tipo_estetica(): void
    {
        Mail::fake();

        $this->postJson('/api/register', $this->cadastro(['perfil' => 'estetica']))->assertStatus(201);

        $usuario = User::where('email', 'estetica@exemplo.com')->first();

        $this->assertSame('estetica', $usuario->perfil);
        $this->assertNull($usuario->oficina_id);
        $this->assertDatabaseHas('contas', ['id' => $usuario->conta_id, 'tipo' => Conta::TIPO_ESTETICA, 'nome' => 'Lava Jato Brilho']);
    }

    public function test_estetica_nao_ganha_papel_de_frota(): void
    {
        Mail::fake();

        $this->postJson('/api/register', $this->cadastro(['perfil' => 'estetica']))->assertStatus(201);

        $this->assertNull(User::where('email', 'estetica@exemplo.com')->first()->papel_frota);
    }
}
