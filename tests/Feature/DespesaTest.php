<?php

namespace Tests\Feature;

use App\Models\Abastecimento;
use App\Models\Seguro;
use App\Models\Servico;
use App\Models\VeiculoConta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class DespesaTest extends TestCase
{
    use RefreshDatabase;
    use AutenticaUsuario;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-03-05');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function veiculo(string $placa = 'AAA1B25', string $modelo = 'Uno'): VeiculoConta
    {
        return VeiculoConta::create(['placa' => $placa, 'marca' => 'Fiat', 'modelo' => $modelo, 'ano' => 2018]);
    }

    private function abastecer(VeiculoConta $v, string $data, float $valor, ?string $combustivel, float $litros = 40): void
    {
        Abastecimento::create([
            'veiculo_conta_id' => $v->id, 'data' => $data, 'km' => 1000, 'litros' => $litros,
            'valor_total' => $valor, 'combustivel' => $combustivel,
        ]);
    }

    private function servico(VeiculoConta $v, string $tipo, string $data, ?float $valor, ?string $titulo = null): void
    {
        Servico::create([
            'veiculo_conta_id' => $v->id, 'tipo' => $tipo, 'titulo' => $titulo,
            'realizado_em' => $data, 'valor' => $valor,
        ]);
    }

    public function test_exige_autenticacao_e_perfil_de_conta(): void
    {
        $this->getJson('/api/conta/despesas')->assertStatus(401);

        [, $headers] = $this->autenticar();
        $this->getJson('/api/conta/despesas', $headers)->assertStatus(403);
    }

    public function test_soma_por_categoria_e_por_tipo_de_combustivel(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $v = $this->veiculo();

        $this->abastecer($v, '2026-03-01', 240, 'gasolina', 40);
        $this->abastecer($v, '2026-02-20', 160, 'gasolina', 28.5);
        $this->abastecer($v, '2026-02-10', 100, 'etanol', 30);
        $this->abastecer($v, '2026-02-05', 50, null, 10);
        $this->servico($v, 'oleo', '2026-02-15', 300);
        $this->servico($v, 'outro', '2026-02-16', 450, 'Pastilhas');
        $this->servico($v, 'bateria', '2026-02-17', null); // sem valor: fora

        $r = $this->getJson('/api/conta/despesas', $headers)->assertStatus(200);

        $r->assertJsonPath('combustivel.total', 550)
            ->assertJsonPath('servicos.total', 750)
            ->assertJsonPath('total', 1300);

        $combustivel = collect($r->json('combustivel.itens'))->keyBy('tipo');
        $this->assertSame('Gasolina', $combustivel['gasolina']['rotulo']);
        $this->assertSame(400, $combustivel['gasolina']['total']);
        $this->assertSame(68.5, $combustivel['gasolina']['litros']);
        $this->assertSame(2, $combustivel['gasolina']['quantidade']);
        $this->assertSame(100, $combustivel['etanol']['total']);
        $this->assertSame('Outro combustível', $combustivel['outro']['rotulo']);
        $this->assertSame('gasolina', $r->json('combustivel.itens.0.tipo')); // maior primeiro

        $servicos = collect($r->json('servicos.itens'))->pluck('total', 'rotulo');
        $this->assertSame(300, $servicos['Troca de óleo']);
        $this->assertSame(450, $servicos['Pastilhas']);
        $this->assertCount(2, $servicos);
    }

    public function test_filtra_por_periodo_e_por_veiculo(): void
    {
        [, $headers] = $this->autenticarConta('frota');
        $a = $this->veiculo('AAA1B25', 'Uno');
        $b = $this->veiculo('BBB2C36', 'Palio');

        $this->abastecer($a, '2026-03-01', 200, 'gasolina');
        $this->abastecer($a, '2025-12-01', 100, 'gasolina');
        $this->abastecer($b, '2026-03-02', 80, 'etanol');
        $this->servico($b, 'oleo', '2026-03-03', 250);

        $periodo = $this->getJson('/api/conta/despesas?de=2026-01-01&ate=2026-03-31', $headers);
        $periodo->assertJsonPath('total', 530)->assertJsonCount(2, 'por_veiculo');

        $this->assertSame(
            ['Fiat Palio' => 330, 'Fiat Uno' => 200],
            collect($periodo->json('por_veiculo'))->pluck('total', 'veiculo')->all(),
        );

        $this->getJson("/api/conta/despesas?veiculo_id={$b->id}", $headers)
            ->assertJsonPath('total', 330)
            ->assertJsonCount(1, 'por_veiculo');

        $this->getJson('/api/conta/despesas?veiculo_id=999', $headers)->assertStatus(404);
        $this->getJson('/api/conta/despesas?de=2026-05-01&ate=2026-01-01', $headers)->assertStatus(422);
    }

    public function test_seguro_aparece_a_parte_e_nao_entra_no_total(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $v = $this->veiculo();
        $this->abastecer($v, '2026-03-01', 100, 'gasolina');

        Seguro::create(['veiculo_conta_id' => $v->id, 'tipo' => 'apolice', 'seguradora' => 'Atual', 'valor_anual' => 3000, 'vigencia_fim' => '2026-06-30']);
        Seguro::create(['veiculo_conta_id' => $v->id, 'tipo' => 'proposta', 'seguradora' => 'Outra', 'valor_anual' => 2000]);

        $r = $this->getJson('/api/conta/despesas', $headers);

        $r->assertJsonPath('total', 100)
            ->assertJsonPath('seguro.valor_anual_total', 3000)
            ->assertJsonCount(1, 'seguro.itens')
            ->assertJsonPath('seguro.itens.0.seguradora', 'Atual')
            ->assertJsonPath('seguro.itens.0.veiculo', 'Fiat Uno');
    }

    public function test_uma_conta_nao_ve_os_gastos_de_outra(): void
    {
        [, $headersA] = $this->autenticarConta('pessoa');
        $this->abastecer($this->veiculo(), '2026-03-01', 999, 'gasolina');

        [, $headersB] = $this->autenticarConta('pessoa');

        $this->getJson('/api/conta/despesas', $headersB)
            ->assertStatus(200)->assertJsonPath('total', 0)->assertJsonCount(0, 'por_veiculo');
        $this->getJson('/api/conta/despesas', $headersA)->assertJsonPath('total', 999);
    }
}
