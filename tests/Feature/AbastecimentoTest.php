<?php

namespace Tests\Feature;

use App\Models\Abastecimento;
use App\Models\VeiculoConta;
use App\Services\CalculoConsumo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class AbastecimentoTest extends TestCase
{
    use RefreshDatabase;
    use AutenticaUsuario;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-06-30');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function veiculo(string $placa = 'AAA1B23'): VeiculoConta
    {
        return VeiculoConta::create(['placa' => $placa, 'marca' => 'Fiat', 'modelo' => 'Uno', 'ano' => 2018]);
    }

    private function abastecer(array $headers, VeiculoConta $veiculo, array $extra): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/api/conta/veiculos/{$veiculo->id}/abastecimentos", array_merge([
            'data' => '2026-06-01', 'km' => 10000, 'litros' => 40, 'valor_total' => 240, 'tanque_cheio' => true,
        ], $extra), $headers);
    }

    // --------------------------------------------------------- cálculo puro

    public function test_calculo_usa_o_tanque_cheio_e_ignora_o_primeiro_como_ponto_de_partida(): void
    {
        $r = (new CalculoConsumo())->calcular(collect([
            ['id' => 1, 'data' => '2026-01-01', 'km' => 1000, 'litros' => 30.0, 'valor_total' => 180.0, 'tanque_cheio' => true],
            ['id' => 2, 'data' => '2026-01-10', 'km' => 1400, 'litros' => 40.0, 'valor_total' => 240.0, 'tanque_cheio' => true],   // 400 km / 40 l = 10
            ['id' => 3, 'data' => '2026-01-20', 'km' => 1700, 'litros' => 20.0, 'valor_total' => 100.0, 'tanque_cheio' => false],  // parcial
            ['id' => 4, 'data' => '2026-01-25', 'km' => 1900, 'litros' => 20.0, 'valor_total' => 130.0, 'tanque_cheio' => true],   // 500 km / 40 l = 12,5
        ]));

        $porId = collect($r['registros'])->keyBy('id');

        $this->assertNull($porId[1]['consumo_km_l']);
        $this->assertSame(10.0, $porId[2]['consumo_km_l']);
        $this->assertSame(0.6, $porId[2]['custo_por_km']);        // 240 / 400
        $this->assertNull($porId[3]['consumo_km_l']);
        $this->assertSame(12.5, $porId[4]['consumo_km_l']);
        $this->assertSame(0.46, $porId[4]['custo_por_km']);       // (100 + 130) / 500

        // Média ponderada: (400 + 500) km / (40 + 40) l
        $this->assertSame(11.25, $r['consumo_medio_km_l']);
        $this->assertSame(0.522, $r['custo_por_km']);             // (240 + 230) / 900
        $this->assertSame(1900, $r['km_atual']);
        $this->assertSame(650.0, $r['total_gasto']);
    }

    public function test_calculo_sem_dois_tanques_cheios_nao_inventa_consumo(): void
    {
        $um = (new CalculoConsumo())->calcular(collect([
            ['id' => 1, 'data' => '2026-01-01', 'km' => 1000, 'litros' => 30.0, 'valor_total' => 180.0, 'tanque_cheio' => true],
        ]));
        $this->assertNull($um['consumo_medio_km_l']);
        $this->assertSame(6.0, $um['preco_medio_litro']);

        $parciais = (new CalculoConsumo())->calcular(collect([
            ['id' => 1, 'data' => '2026-01-01', 'km' => 1000, 'litros' => 10.0, 'valor_total' => 60.0, 'tanque_cheio' => false],
            ['id' => 2, 'data' => '2026-01-05', 'km' => 1100, 'litros' => 10.0, 'valor_total' => 60.0, 'tanque_cheio' => false],
        ]));
        $this->assertNull($parciais['consumo_medio_km_l']);

        $vazio = (new CalculoConsumo())->calcular(collect());
        $this->assertNull($vazio['km_atual']);
        $this->assertNull($vazio['preco_medio_litro']);
    }

    // ------------------------------------------------------------------ API

    public function test_exige_autenticacao_e_perfil_de_conta(): void
    {
        $this->getJson('/api/conta/veiculos/1/abastecimentos')->assertStatus(401);

        [, $headers] = $this->autenticar();
        $this->getJson('/api/conta/veiculos/1/abastecimentos', $headers)->assertStatus(403);
    }

    public function test_registra_e_calcula_o_consumo_pela_api(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $veiculo = $this->veiculo();

        $this->abastecer($headers, $veiculo, ['data' => '2026-05-01', 'km' => 10000, 'litros' => 30, 'valor_total' => 180])->assertStatus(201);
        $resposta = $this->abastecer($headers, $veiculo, ['data' => '2026-05-15', 'km' => 10400, 'litros' => 40, 'valor_total' => 240, 'combustivel' => 'gasolina', 'posto' => 'Posto X'])
            ->assertStatus(201);

        $resposta->assertJsonPath('resumo.consumo_medio_km_l', 10)
            ->assertJsonPath('resumo.quantidade', 2)
            ->assertJsonPath('resumo.km_atual', 10400)
            ->assertJsonPath('abastecimentos.0.km', 10400)         // mais recente primeiro
            ->assertJsonPath('abastecimentos.0.consumo_km_l', 10)
            ->assertJsonPath('abastecimentos.0.preco_litro', 6)
            ->assertJsonPath('abastecimentos.0.posto', 'Posto X');

        $this->getJson("/api/conta/veiculos/{$veiculo->id}/abastecimentos", $headers)
            ->assertJsonCount(2, 'abastecimentos');
    }

    public function test_valida_os_dados(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $veiculo = $this->veiculo();

        $this->postJson("/api/conta/veiculos/{$veiculo->id}/abastecimentos", [], $headers)
            ->assertStatus(422)->assertJsonValidationErrors(['data', 'km', 'litros', 'valor_total']);

        $this->abastecer($headers, $veiculo, ['litros' => 0])->assertStatus(422)->assertJsonValidationErrors('litros');
        $this->abastecer($headers, $veiculo, ['data' => '2026-07-15'])->assertStatus(422)->assertJsonValidationErrors('data'); // futuro
        $this->abastecer($headers, $veiculo, ['combustivel' => 'agua'])->assertStatus(422)->assertJsonValidationErrors('combustivel');
    }

    public function test_o_hodometro_so_sobe(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $veiculo = $this->veiculo();

        $this->abastecer($headers, $veiculo, ['data' => '2026-05-01', 'km' => 10000])->assertStatus(201);
        $this->abastecer($headers, $veiculo, ['data' => '2026-05-20', 'km' => 10800])->assertStatus(201);

        // Depois de 10000 km, não pode vir um km menor.
        $this->abastecer($headers, $veiculo, ['data' => '2026-05-10', 'km' => 9000])
            ->assertStatus(422)->assertJsonValidationErrors('km');
        // Entre os dois, também não pode passar do seguinte.
        $this->abastecer($headers, $veiculo, ['data' => '2026-05-10', 'km' => 11000])
            ->assertStatus(422)->assertJsonValidationErrors('km');
        // No meio, vale.
        $this->abastecer($headers, $veiculo, ['data' => '2026-05-10', 'km' => 10400])->assertStatus(201);
    }

    public function test_remove_um_abastecimento_e_recalcula(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $veiculo = $this->veiculo();

        $this->abastecer($headers, $veiculo, ['data' => '2026-05-01', 'km' => 10000]);
        $id = $this->abastecer($headers, $veiculo, ['data' => '2026-05-15', 'km' => 10400])->json('abastecimentos.0.id');

        $this->deleteJson("/api/conta/veiculos/{$veiculo->id}/abastecimentos/{$id}", [], $headers)
            ->assertStatus(200)
            ->assertJsonPath('resumo.quantidade', 1)
            ->assertJsonPath('resumo.consumo_medio_km_l', null);

        $this->deleteJson("/api/conta/veiculos/{$veiculo->id}/abastecimentos/999", [], $headers)->assertStatus(404);
    }

    public function test_uma_conta_nao_ve_nem_mexe_nos_abastecimentos_de_outra(): void
    {
        [, $headersA] = $this->autenticarConta('pessoa');
        $veiculoA = $this->veiculo();
        $id = $this->abastecer($headersA, $veiculoA, ['posto' => 'Segredo da conta A'])->json('abastecimentos.0.id');

        [, $headersB] = $this->autenticarConta('frota');

        $this->getJson("/api/conta/veiculos/{$veiculoA->id}/abastecimentos", $headersB)->assertStatus(404);
        $this->abastecer($headersB, $veiculoA, [])->assertStatus(404);
        $this->deleteJson("/api/conta/veiculos/{$veiculoA->id}/abastecimentos/{$id}", [], $headersB)->assertStatus(404);

        $this->assertSame(1, Abastecimento::withoutGlobalScopes()->count());
    }

    public function test_apagar_o_veiculo_leva_os_abastecimentos_junto(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $veiculo = $this->veiculo();
        $this->abastecer($headers, $veiculo, []);

        $this->deleteJson("/api/conta/veiculos/{$veiculo->id}", [], $headers)->assertStatus(200);

        $this->assertSame(0, Abastecimento::withoutGlobalScopes()->count());
    }
}
