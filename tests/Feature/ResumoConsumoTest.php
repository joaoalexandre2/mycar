<?php

namespace Tests\Feature;

use App\Models\Abastecimento;
use App\Models\Servico;
use App\Models\VeiculoConta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

/** O resumo da tela Início traz o consumo médio, o preço do litro e o gasto do mês. */
class ResumoConsumoTest extends TestCase
{
    use RefreshDatabase;
    use AutenticaUsuario;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-15');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function veiculo(string $placa = 'AAA1B25'): VeiculoConta
    {
        return VeiculoConta::create(['placa' => $placa, 'marca' => 'Fiat', 'modelo' => 'Uno', 'ano' => 2018]);
    }

    private function abastecer(VeiculoConta $v, string $data, int $km, float $litros, float $valor, bool $cheio = true): void
    {
        Abastecimento::create([
            'veiculo_conta_id' => $v->id, 'data' => $data, 'km' => $km, 'litros' => $litros,
            'valor_total' => $valor, 'tanque_cheio' => $cheio,
        ]);
    }

    public function test_sem_abastecimentos_o_consumo_vem_nulo_e_o_gasto_zerado(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $this->veiculo();

        $this->getJson('/api/conta/resumo', $headers)->assertStatus(200)
            ->assertJsonPath('consumo.km_por_litro', null)
            ->assertJsonPath('consumo.preco_medio_litro', null)
            ->assertJsonPath('consumo.veiculos_com_dados', 0)
            ->assertJsonPath('gasto_mes.total', 0);
    }

    public function test_um_unico_tanque_cheio_ainda_nao_fecha_um_intervalo(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $v = $this->veiculo();
        $this->abastecer($v, '2026-10-01', 10000, 40, 240);

        $this->getJson('/api/conta/resumo', $headers)
            ->assertJsonPath('consumo.km_por_litro', null)
            ->assertJsonPath('consumo.veiculos_com_dados', 0)
            ->assertJsonPath('consumo.preco_medio_litro', 6); // 240 / 40
    }

    public function test_calcula_o_consumo_pelo_metodo_do_tanque_cheio(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $v = $this->veiculo();
        $this->abastecer($v, '2026-10-01', 10000, 40, 240);
        $this->abastecer($v, '2026-10-10', 10450, 50, 310); // 450 km com 50 L = 9 km/l

        $this->getJson('/api/conta/resumo', $headers)
            ->assertJsonPath('consumo.km_por_litro', 9)
            ->assertJsonPath('consumo.veiculos_com_dados', 1)
            ->assertJsonPath('consumo.preco_medio_litro', 6.11); // 550 / 90
    }

    public function test_media_dos_veiculos_que_tem_dado(): void
    {
        [, $headers] = $this->autenticarConta('frota');
        $a = $this->veiculo('AAA1B25');
        $b = $this->veiculo('BBB2C36');
        $semDado = $this->veiculo('CCC3D47');

        $this->abastecer($a, '2026-10-01', 1000, 40, 240);
        $this->abastecer($a, '2026-10-10', 1400, 40, 240);  // 10 km/l
        $this->abastecer($b, '2026-10-01', 5000, 40, 240);
        $this->abastecer($b, '2026-10-10', 5320, 40, 240);  // 8 km/l

        $this->getJson('/api/conta/resumo', $headers)
            ->assertJsonPath('consumo.km_por_litro', 9)
            ->assertJsonPath('consumo.veiculos_com_dados', 2);
        $this->assertNotNull($semDado->id);
    }

    public function test_gasto_do_mes_soma_combustivel_e_servicos_so_do_mes_corrente(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $v = $this->veiculo();

        $this->abastecer($v, '2026-10-05', 1000, 40, 250);
        $this->abastecer($v, '2026-09-28', 800, 40, 999); // mês passado
        Servico::create(['veiculo_conta_id' => $v->id, 'tipo' => 'oleo', 'realizado_em' => '2026-10-12', 'valor' => 310]);
        Servico::create(['veiculo_conta_id' => $v->id, 'tipo' => 'bateria', 'realizado_em' => '2026-10-13', 'valor' => null]); // sem valor
        Servico::create(['veiculo_conta_id' => $v->id, 'tipo' => 'pneus', 'realizado_em' => '2026-08-01', 'valor' => 2000]); // outro mês

        $this->getJson('/api/conta/resumo', $headers)
            ->assertJsonPath('gasto_mes.combustivel', 250)
            ->assertJsonPath('gasto_mes.servicos', 310)
            ->assertJsonPath('gasto_mes.total', 560);
    }

    public function test_nao_mistura_dados_de_outra_conta(): void
    {
        [, $headersA] = $this->autenticarConta('pessoa');
        $v = $this->veiculo();
        $this->abastecer($v, '2026-10-01', 1000, 40, 240);
        $this->abastecer($v, '2026-10-10', 1400, 40, 240);

        [, $headersB] = $this->autenticarConta('pessoa');

        $this->getJson('/api/conta/resumo', $headersB)
            ->assertJsonPath('consumo.km_por_litro', null)
            ->assertJsonPath('gasto_mes.total', 0);
        $this->getJson('/api/conta/resumo', $headersA)->assertJsonPath('consumo.km_por_litro', 10);
    }
}
