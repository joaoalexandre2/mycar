<?php

namespace Tests\Feature;

use App\Models\Abastecimento;
use App\Models\CategoriaDespesa;
use App\Models\CentroCusto;
use App\Models\Conta;
use App\Models\Despesa;
use App\Models\Motorista;
use App\Models\Multa;
use App\Models\Servico;
use App\Models\UsoVeiculo;
use App\Models\VeiculoConta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

/**
 * Gestão financeira da frota: a consolidação de todos os gastos e as despesas avulsas.
 */
class FinanceiroFrotaTest extends TestCase
{
    use AutenticaUsuario;
    use RefreshDatabase;

    /** @var array<string, string> */
    private array $headers;

    private Conta $conta;

    private VeiculoConta $strada;

    private Motorista $joao;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-03-15 12:00:00');

        [, $this->headers] = $this->autenticarConta('frota');
        $this->conta = Conta::find(app('conta.atual'));

        $this->strada = VeiculoConta::create(['placa' => 'AAA1A11', 'marca' => 'Fiat', 'modelo' => 'Strada', 'ano' => 2022]);
        $this->joao = Motorista::create(['nome' => 'João', 'cnh_numero' => '123', 'cnh_validade' => now()->addYears(2)->toDateString()]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function categoria(string $nome): CategoriaDespesa
    {
        CategoriaDespesa::garantirPadroes();

        return CategoriaDespesa::where('nome', $nome)->firstOrFail();
    }

    private function avulsa(float $valor, string $data, ?VeiculoConta $veiculo = null, array $extra = []): Despesa
    {
        return Despesa::create(array_merge([
            'categoria_id' => $this->categoria('Pedágio')->id,
            'veiculo_conta_id' => $veiculo?->id ?? $this->strada->id,
            'data' => $data,
            'valor' => $valor,
        ], $extra));
    }

    public function test_consolida_combustivel_servicos_avulsas_e_multas_uma_vez_cada(): void
    {
        Abastecimento::create(['veiculo_conta_id' => $this->strada->id, 'data' => '2026-03-05', 'km' => 1000, 'litros' => 40, 'valor_total' => 200, 'combustivel' => 'gasolina']);
        Servico::create(['veiculo_conta_id' => $this->strada->id, 'tipo' => 'oleo', 'realizado_em' => '2026-03-05', 'valor' => 150]);
        $this->avulsa(30, '2026-03-05');
        Multa::create(['veiculo_conta_id' => $this->strada->id, 'data_hora' => '2026-03-05 10:00:00', 'descricao' => 'Velocidade', 'valor' => 100, 'situacao' => Multa::ABERTA]);

        $this->getJson('/api/conta/despesas', $this->headers)
            ->assertOk()
            ->assertJsonPath('total', 480)
            ->assertJsonPath('combustivel.total', 200)
            ->assertJsonPath('servicos.total', 150)
            ->assertJsonPath('avulsas.total', 30)
            ->assertJsonPath('multas.total', 100)
            ->assertJsonPath('multas.quantidade', 1)
            ->assertJsonPath('por_veiculo.0.total', 480)
            ->assertJsonPath('indicadores.custo_total', 480);
    }

    public function test_periodo_e_veiculo_valem_para_as_despesas_avulsas(): void
    {
        $gol = VeiculoConta::create(['placa' => 'BBB2B22', 'marca' => 'VW', 'modelo' => 'Gol', 'ano' => 2020]);
        $this->avulsa(30, '2026-03-05');
        $this->avulsa(70, '2026-02-20');
        $this->avulsa(50, '2026-03-06', $gol);

        $this->getJson('/api/conta/despesas?de=2026-03-01&ate=2026-03-31', $this->headers)
            ->assertJsonPath('avulsas.total', 80);

        $this->getJson("/api/conta/despesas?de=2026-03-01&ate=2026-03-31&veiculo_id={$this->strada->id}", $this->headers)
            ->assertJsonPath('avulsas.total', 30)
            ->assertJsonPath('total', 30);
    }

    public function test_custo_por_km_usa_os_km_rodados_dos_usos_do_periodo(): void
    {
        UsoVeiculo::create([
            'veiculo_conta_id' => $this->strada->id,
            'motorista_id' => $this->joao->id,
            'inicio_previsto' => '2026-03-05 08:00:00',
            'fim_previsto' => '2026-03-05 18:00:00',
            'saida_em' => '2026-03-05 11:00:00',
            'chegada_em' => '2026-03-05 20:00:00',
            'km_saida' => 1000,
            'km_chegada' => 1100,
            'status' => 'concluido',
        ]);
        $this->avulsa(100, '2026-03-05');

        $r = $this->getJson('/api/conta/despesas', $this->headers)->assertOk()->json();

        $this->assertSame(100, $r['por_veiculo'][0]['km_rodados']);
        $this->assertEqualsWithDelta(1.0, $r['por_veiculo'][0]['custo_por_km'], 0.001);
        $this->assertSame(100, $r['indicadores']['km_rodados']);
        $this->assertEqualsWithDelta(1.0, $r['indicadores']['custo_por_km'], 0.001);
    }

    public function test_motorista_e_centro_de_custo_aparecem_so_onde_existem(): void
    {
        $entregas = CentroCusto::create(['nome' => 'Entregas']);
        $this->avulsa(40, '2026-03-05', null, ['motorista_id' => $this->joao->id, 'centro_custo_id' => $entregas->id]);
        $this->avulsa(60, '2026-03-05');
        Multa::create(['veiculo_conta_id' => $this->strada->id, 'motorista_id' => $this->joao->id, 'data_hora' => '2026-03-05 10:00:00', 'descricao' => 'Velocidade', 'valor' => 100, 'situacao' => Multa::ABERTA]);

        $r = $this->getJson('/api/conta/despesas', $this->headers)->assertOk()->json();

        $motoristas = collect($r['por_motorista'])->keyBy('nome');
        $this->assertEqualsWithDelta(140.0, $motoristas['João']['total'], 0.001);
        $this->assertEqualsWithDelta(60.0, $motoristas['Sem motorista']['total'], 0.001);

        $centros = collect($r['por_centro'])->keyBy('nome');
        $this->assertEqualsWithDelta(40.0, $centros['Entregas']['total'], 0.001);
        $this->assertEqualsWithDelta(60.0, $centros['Sem centro de custo']['total'], 0.001);
    }

    public function test_mensal_separa_os_meses_pela_data_do_gasto(): void
    {
        Abastecimento::create(['veiculo_conta_id' => $this->strada->id, 'data' => '2026-03-05', 'km' => 1000, 'litros' => 40, 'valor_total' => 200, 'combustivel' => 'gasolina']);
        $this->avulsa(30, '2026-04-02');

        $mensal = $this->getJson('/api/conta/despesas', $this->headers)->assertOk()->json('mensal');

        $this->assertSame(['2026-03', '2026-04'], array_column($mensal, 'mes'));
        $this->assertEqualsWithDelta(200.0, $mensal[0]['combustivel'], 0.001);
        $this->assertEqualsWithDelta(30.0, $mensal[1]['avulsas'], 0.001);
    }

    public function test_cria_atualiza_e_apaga_despesa_avulsa(): void
    {
        $criada = $this->postJson('/api/conta/despesas/avulsas', [
            'categoria_id' => $this->categoria('Lavagem')->id,
            'veiculo_conta_id' => $this->strada->id,
            'motorista_id' => $this->joao->id,
            'data' => '2026-03-05',
            'valor' => 45.5,
            'descricao' => 'Lavagem completa',
            'comprovante' => 'NF 123',
        ], $this->headers)
            ->assertStatus(201)
            ->assertJsonPath('categoria.nome', 'Lavagem')
            ->assertJsonPath('valor', 45.5);

        $id = $criada->json('id');

        $this->putJson("/api/conta/despesas/avulsas/{$id}", [
            'categoria_id' => $this->categoria('Lavagem')->id,
            'data' => '2026-03-05',
            'valor' => 50,
        ], $this->headers)->assertOk()->assertJsonPath('valor', 50);

        $this->deleteJson("/api/conta/despesas/avulsas/{$id}", [], $this->headers)->assertNoContent();
        $this->assertDatabaseMissing('despesas', ['id' => $id]);
    }

    public function test_recusa_valor_zero_categoria_inativa_e_categoria_inexistente(): void
    {
        $lavagem = $this->categoria('Lavagem');

        $this->postJson('/api/conta/despesas/avulsas', ['categoria_id' => $lavagem->id, 'data' => '2026-03-05', 'valor' => 0], $this->headers)
            ->assertStatus(422);

        $this->putJson("/api/conta/despesas/categorias/{$lavagem->id}", ['ativo' => false], $this->headers)->assertOk();

        $this->postJson('/api/conta/despesas/avulsas', ['categoria_id' => $lavagem->id, 'data' => '2026-03-05', 'valor' => 10], $this->headers)
            ->assertStatus(422);

        $this->postJson('/api/conta/despesas/avulsas', ['categoria_id' => 999999, 'data' => '2026-03-05', 'valor' => 10], $this->headers)
            ->assertStatus(422);
    }

    public function test_categorias_comecam_com_as_padrao_e_nao_repetem_nome(): void
    {
        $this->getJson('/api/conta/despesas/categorias', $this->headers)
            ->assertOk()
            ->assertJsonCount(6)
            ->assertJsonPath('0.ativo', true);

        $this->postJson('/api/conta/despesas/categorias', ['nome' => 'Ponte'], $this->headers)
            ->assertStatus(201)
            ->assertJsonPath('nome', 'Ponte');

        $this->postJson('/api/conta/despesas/categorias', ['nome' => 'Lavagem'], $this->headers)
            ->assertStatus(422);
    }

    public function test_despesas_de_outra_conta_nao_aparecem_nem_podem_ser_alteradas(): void
    {
        $contaB = Conta::create(['tipo' => 'frota', 'nome' => 'Empresa B']);
        app()->instance('conta.atual', $contaB->id);
        $veiculoB = VeiculoConta::create(['placa' => 'CCC3C33', 'marca' => 'VW', 'modelo' => 'Gol', 'ano' => 2020]);
        $despesaB = $this->avulsa(999, '2026-03-05', $veiculoB);
        app()->instance('conta.atual', $this->conta->id);

        $this->getJson('/api/conta/despesas', $this->headers)->assertJsonPath('total', 0);

        $this->putJson("/api/conta/despesas/avulsas/{$despesaB->id}", ['categoria_id' => $this->categoria('Pedágio')->id, 'data' => '2026-03-05', 'valor' => 1], $this->headers)
            ->assertStatus(404);
        $this->deleteJson("/api/conta/despesas/avulsas/{$despesaB->id}", [], $this->headers)->assertStatus(404);

        $this->assertDatabaseHas('despesas', ['id' => $despesaB->id, 'valor' => 999]);
    }

    public function test_motorista_nao_acessa_as_despesas_avulsas(): void
    {
        [$usuario, $headersMotorista] = $this->autenticarConta('frota', $this->conta);
        $usuario->forceFill(['papel_frota' => 'motorista'])->save();
        app()->instance('conta.atual', $this->conta->id);

        $this->getJson('/api/conta/despesas/avulsas', $headersMotorista)->assertStatus(403);
        $this->postJson('/api/conta/despesas/avulsas', ['categoria_id' => 1, 'data' => '2026-03-05', 'valor' => 10], $headersMotorista)
            ->assertStatus(403);
    }
}
