<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

/**
 * O "ano" do veículo é o ano do MODELO (o mesmo da tabela FIPE), que pode ser o
 * ano seguinte ao de fabricação: carros modelo 2027 já são vendidos em 2026.
 */
class AnoModeloTest extends TestCase
{
    use RefreshDatabase;
    use AutenticaUsuario;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-07');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function veiculoConta(int $ano): array
    {
        return ['placa' => 'AAA1B25', 'marca' => 'Fiat', 'modelo' => 'Pulse', 'ano' => $ano];
    }

    public function test_conta_aceita_o_ano_modelo_do_ano_seguinte(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');

        $this->postJson('/api/conta/veiculos', $this->veiculoConta(2027), $headers)->assertStatus(201)->assertJsonPath('ano', 2027);
    }

    public function test_conta_rejeita_dois_anos_a_frente_e_antes_de_1900(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');

        $this->postJson('/api/conta/veiculos', $this->veiculoConta(2028), $headers)->assertStatus(422)->assertJsonValidationErrors('ano');
        $this->postJson('/api/conta/veiculos', $this->veiculoConta(1850), $headers)->assertStatus(422)->assertJsonValidationErrors('ano');
    }

    public function test_oficina_tambem_aceita_o_ano_modelo_do_ano_seguinte(): void
    {
        [, $headers] = $this->autenticar();
        $cliente = \App\Models\Cliente::factory()->create();

        $dados = ['cliente_id' => $cliente->id, 'placa' => 'BBB2C36', 'marca' => 'Fiat', 'modelo' => 'Pulse', 'ano' => 2027];

        $this->postJson('/api/veiculos', $dados, $headers)->assertStatus(201);
        $this->postJson('/api/veiculos', ['placa' => 'CCC3D47', 'ano' => 2028] + $dados, $headers)
            ->assertStatus(422)->assertJsonValidationErrors('ano');
    }
}
