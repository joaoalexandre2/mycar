<?php

namespace Tests\Feature;

use App\Models\Oficina;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class ConfiguracaoTest extends TestCase
{
    use RefreshDatabase;
    use AutenticaUsuario;

    public function test_exige_autenticacao(): void
    {
        $this->putJson('/api/me', [])->assertStatus(401);
        $this->putJson('/api/me/senha', [])->assertStatus(401);
        $this->getJson('/api/oficina')->assertStatus(401);
        $this->putJson('/api/oficina', [])->assertStatus(401);
    }

    public function test_atualiza_nome_do_perfil_sem_mexer_no_email(): void
    {
        [$usuario, $headers] = $this->autenticar();

        $this->putJson('/api/me', ['name' => 'Novo Nome', 'email' => 'outro@x.com'], $headers)
            ->assertStatus(200)
            ->assertJsonPath('name', 'Novo Nome')
            ->assertJsonPath('email', $usuario->email);

        $this->assertDatabaseHas('users', ['id' => $usuario->id, 'name' => 'Novo Nome', 'email' => $usuario->email]);
    }

    public function test_nome_do_perfil_e_obrigatorio(): void
    {
        [, $headers] = $this->autenticar();

        $this->putJson('/api/me', ['name' => ''], $headers)->assertStatus(422)->assertJsonValidationErrors('name');
    }

    public function test_altera_senha_com_a_senha_atual_correta(): void
    {
        [$usuario, $headers] = $this->autenticar();
        $usuario->forceFill(['password' => Hash::make('senha-antiga-1')])->save();

        $this->putJson('/api/me/senha', [
            'senha_atual' => 'senha-antiga-1',
            'password' => 'senha-nova-1234',
            'password_confirmation' => 'senha-nova-1234',
        ], $headers)->assertStatus(200);

        $this->assertTrue(Hash::check('senha-nova-1234', $usuario->fresh()->password));
    }

    public function test_recusa_senha_atual_errada_e_senha_fraca(): void
    {
        [$usuario, $headers] = $this->autenticar();
        $usuario->forceFill(['password' => Hash::make('senha-antiga-1')])->save();

        $this->putJson('/api/me/senha', [
            'senha_atual' => 'errada',
            'password' => 'senha-nova-1234',
            'password_confirmation' => 'senha-nova-1234',
        ], $headers)->assertStatus(422)->assertJsonValidationErrors('senha_atual');

        $this->putJson('/api/me/senha', [
            'senha_atual' => 'senha-antiga-1',
            'password' => 'curta',
            'password_confirmation' => 'curta',
        ], $headers)->assertStatus(422)->assertJsonValidationErrors('password');

        $this->assertTrue(Hash::check('senha-antiga-1', $usuario->fresh()->password));
    }

    public function test_le_e_atualiza_dados_da_oficina(): void
    {
        [, $headers] = $this->autenticar();

        $this->putJson('/api/oficina', [
            'nome' => 'Oficina Nova',
            'cnpj' => '00.000.000/0001-00',
            'telefone' => '(45) 3035-0000',
            'endereco' => 'Cascavel - PR',
        ], $headers)->assertStatus(200)->assertJsonPath('nome', 'Oficina Nova');

        $this->getJson('/api/oficina', $headers)
            ->assertStatus(200)
            ->assertJsonPath('cnpj', '00.000.000/0001-00')
            ->assertJsonPath('endereco', 'Cascavel - PR');
    }

    public function test_nome_da_oficina_e_obrigatorio(): void
    {
        [, $headers] = $this->autenticar();

        $this->putJson('/api/oficina', ['nome' => ''], $headers)->assertStatus(422)->assertJsonValidationErrors('nome');
    }

    public function test_so_altera_a_propria_oficina(): void
    {
        [$usuario, $headers] = $this->autenticar();
        $outra = Oficina::factory()->create(['nome' => 'Outra']);

        $this->putJson('/api/oficina', ['nome' => 'Minha'], $headers)->assertStatus(200);

        $this->assertSame('Minha', $usuario->oficina->fresh()->nome);
        $this->assertSame('Outra', $outra->fresh()->nome);
    }
}
