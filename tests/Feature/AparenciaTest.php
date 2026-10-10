<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class AparenciaTest extends TestCase
{
    use RefreshDatabase;
    use AutenticaUsuario;

    public function test_exige_autenticacao(): void
    {
        $this->putJson('/api/me/aparencia', ['tema' => 'escuro', 'cor' => 'green'])->assertStatus(401);
    }

    public function test_quem_nunca_escolheu_vem_com_tema_e_cor_nulos(): void
    {
        [, $headers] = $this->autenticar();

        $this->getJson('/api/me', $headers)
            ->assertStatus(200)->assertJsonPath('tema', null)->assertJsonPath('cor', null);
    }

    public function test_grava_tema_e_cor_no_banco_e_devolve_no_me(): void
    {
        [$user, $headers] = $this->autenticar();

        $this->putJson('/api/me/aparencia', ['tema' => 'escuro', 'cor' => 'purple'], $headers)
            ->assertStatus(200)->assertJsonPath('tema', 'escuro')->assertJsonPath('cor', 'purple');

        $this->assertSame('escuro', User::find($user->id)->tema);
        $this->assertSame('purple', User::find($user->id)->cor);

        $this->getJson('/api/me', $headers)->assertJsonPath('tema', 'escuro')->assertJsonPath('cor', 'purple');
    }

    public function test_funciona_para_perfil_de_conta_tambem(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');

        $this->putJson('/api/me/aparencia', ['tema' => 'claro', 'cor' => 'orange'], $headers)
            ->assertStatus(200)->assertJsonPath('cor', 'orange');
    }

    public function test_aceita_todas_as_cores_da_lista_inclusive_a_vermelha(): void
    {
        [, $headers] = $this->autenticar();

        foreach (['blue', 'green', 'purple', 'orange', 'red'] as $cor) {
            $this->putJson('/api/me/aparencia', ['tema' => 'claro', 'cor' => $cor], $headers)
                ->assertStatus(200)->assertJsonPath('cor', $cor);
        }
    }

    public function test_rejeita_valores_invalidos(): void
    {
        [, $headers] = $this->autenticar();

        $this->putJson('/api/me/aparencia', [], $headers)
            ->assertStatus(422)->assertJsonValidationErrors(['tema', 'cor']);
        $this->putJson('/api/me/aparencia', ['tema' => 'neon', 'cor' => 'blue'], $headers)
            ->assertStatus(422)->assertJsonValidationErrors('tema');
        $this->putJson('/api/me/aparencia', ['tema' => 'claro', 'cor' => 'rosa'], $headers)
            ->assertStatus(422)->assertJsonValidationErrors('cor');
    }
}
