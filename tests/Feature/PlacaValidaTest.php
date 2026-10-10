<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Veiculo;
use App\Rules\PlacaBrasileira;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

/**
 * A placa só aceita o padrão antigo (ABC1234) ou o Mercosul (ABC1D23).
 */
class PlacaValidaTest extends TestCase
{
    use RefreshDatabase;
    use AutenticaUsuario;

    public static function placasInvalidas(): array
    {
        return [
            'muito curta' => ['ABC'],
            'só números' => ['1234567'],
            'só letras' => ['ABCDEFG'],
            'letra no lugar errado' => ['ABCD123'],
            'um número a mais' => ['ABC12345'],
            'texto qualquer' => ['meu carro'],
            'mercosul com letra a mais' => ['AB1C2D3'],
        ];
    }

    public static function placasValidas(): array
    {
        return [
            'antiga' => ['CMG3164', 'CMG3164'],
            'antiga com hífen' => ['cmg-3164', 'CMG3164'],
            'mercosul' => ['FJB4E12', 'FJB4E12'],
            'mercosul minúscula com espaço' => [' fjb 4e12 ', 'FJB4E12'],
        ];
    }

    #[DataProvider('placasInvalidas')]
    public function test_conta_rejeita_placa_invalida(string $placa): void
    {
        [, $headers] = $this->autenticarConta('pessoa');

        $this->postJson('/api/conta/veiculos', [
            'placa' => $placa, 'marca' => 'Fiat', 'modelo' => 'Uno', 'ano' => 2018,
        ], $headers)->assertStatus(422)->assertJsonValidationErrors('placa');
    }

    #[DataProvider('placasValidas')]
    public function test_conta_aceita_e_normaliza_a_placa(string $enviada, string $guardada): void
    {
        [, $headers] = $this->autenticarConta('pessoa');

        $this->postJson('/api/conta/veiculos', [
            'placa' => $enviada, 'marca' => 'Fiat', 'modelo' => 'Uno', 'ano' => 2018,
        ], $headers)->assertStatus(201)->assertJsonPath('placa', $guardada);
    }

    public function test_conta_nao_aceita_a_mesma_placa_escrita_de_outro_jeito(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $dados = ['marca' => 'Fiat', 'modelo' => 'Uno', 'ano' => 2018];

        $this->postJson('/api/conta/veiculos', $dados + ['placa' => 'CMG3164'], $headers)->assertStatus(201);
        $this->postJson('/api/conta/veiculos', $dados + ['placa' => 'cmg-3164'], $headers)
            ->assertStatus(422)->assertJsonValidationErrors('placa');
    }

    #[DataProvider('placasInvalidas')]
    public function test_oficina_rejeita_placa_invalida(string $placa): void
    {
        [, $headers] = $this->autenticar();
        $cliente = Cliente::factory()->create();

        $this->postJson('/api/veiculos', [
            'cliente_id' => $cliente->id, 'placa' => $placa, 'marca' => 'Fiat', 'modelo' => 'Uno', 'ano' => 2018,
        ], $headers)->assertStatus(422)->assertJsonValidationErrors('placa');
    }

    public function test_oficina_aceita_e_normaliza_na_criacao_e_na_edicao(): void
    {
        [, $headers] = $this->autenticar();
        $cliente = Cliente::factory()->create();
        $dados = ['cliente_id' => $cliente->id, 'marca' => 'Fiat', 'modelo' => 'Uno', 'ano' => 2018];

        $id = $this->postJson('/api/veiculos', $dados + ['placa' => 'cmg-3164'], $headers)
            ->assertStatus(201)->assertJsonPath('placa', 'CMG3164')->json('id');

        $this->putJson("/api/veiculos/{$id}", $dados + ['placa' => 'fjb4e12'], $headers)
            ->assertStatus(200)->assertJsonPath('placa', 'FJB4E12');

        $this->putJson("/api/veiculos/{$id}", $dados + ['placa' => 'xx'], $headers)
            ->assertStatus(422)->assertJsonValidationErrors('placa');

        $this->assertSame('FJB4E12', Veiculo::find($id)->placa);
    }

    public function test_regra_diferencia_os_dois_padroes(): void
    {
        $this->assertSame('mercosul', PlacaBrasileira::padrao('FJB4E12'));
        $this->assertSame('mercosul', PlacaBrasileira::padrao('fjb-4e12'));
        $this->assertSame('antiga', PlacaBrasileira::padrao('CMG-3164'));
        $this->assertNull(PlacaBrasileira::padrao('CMG316'));
        $this->assertNull(PlacaBrasileira::padrao(null));
    }
}
