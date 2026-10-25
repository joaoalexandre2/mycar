<?php

namespace Tests\Feature;

use App\Enums\TipoVeiculo;
use App\Models\Cliente;
use App\Models\Veiculo;
use App\Models\VeiculoConta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

/** Motos e caminhões: o tipo é guardado, a FIPE é consultada pelo tipo certo e o que só vale para carros fica desligado. */
class TiposDeVeiculoTest extends TestCase
{
    use AutenticaUsuario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Carbon::setTestNow('2026-03-05');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function dados(array $extra = []): array
    {
        return array_merge([
            'placa' => 'ABC1D23', 'marca' => 'Honda', 'modelo' => 'CG 160 Fan', 'ano' => 2022, 'uf' => 'RS',
        ], $extra);
    }

    // ------------------------------------------------------------- o tipo

    public function test_enum_liga_cada_tipo_a_tabela_da_fipe(): void
    {
        $this->assertSame('carros', TipoVeiculo::Carro->fipe());
        $this->assertSame('motos', TipoVeiculo::Moto->fipe());
        $this->assertSame('caminhoes', TipoVeiculo::Caminhao->fipe());
        $this->assertSame(['carro', 'moto', 'caminhao'], TipoVeiculo::valores());
        $this->assertSame('Caminhão', TipoVeiculo::Caminhao->rotulo());
    }

    public function test_sem_informar_o_tipo_o_veiculo_da_conta_e_carro(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');

        $this->postJson('/api/conta/veiculos', $this->dados(), $headers)
            ->assertStatus(201)
            ->assertJsonPath('tipo_veiculo', 'carro');
    }

    public function test_cadastra_moto_e_caminhao_na_conta_e_o_tipo_volta_na_lista(): void
    {
        [, $headers] = $this->autenticarConta('frota');

        $this->postJson('/api/conta/veiculos', $this->dados(['tipo_veiculo' => 'moto']), $headers)
            ->assertStatus(201)
            ->assertJsonPath('tipo_veiculo', 'moto');
        $this->postJson('/api/conta/veiculos', $this->dados(['placa' => 'DEF2G34', 'marca' => 'Volvo', 'modelo' => 'FH 540', 'tipo_veiculo' => 'caminhao']), $headers)
            ->assertStatus(201)
            ->assertJsonPath('tipo_veiculo', 'caminhao');

        $tipos = collect($this->getJson('/api/conta/veiculos?all=1', $headers)->json())->pluck('tipo_veiculo', 'placa');

        $this->assertSame('moto', $tipos['ABC1D23']);
        $this->assertSame('caminhao', $tipos['DEF2G34']);
    }

    public function test_recusa_tipo_que_nao_existe(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');

        $this->postJson('/api/conta/veiculos', $this->dados(['tipo_veiculo' => 'aviao']), $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors('tipo_veiculo');
    }

    public function test_edita_o_tipo_do_veiculo(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $id = $this->postJson('/api/conta/veiculos', $this->dados(), $headers)->json('id');

        $this->putJson("/api/conta/veiculos/{$id}", $this->dados(['tipo_veiculo' => 'moto']), $headers)
            ->assertOk()
            ->assertJsonPath('tipo_veiculo', 'moto');
    }

    public function test_veiculo_de_cliente_da_oficina_tambem_tem_tipo(): void
    {
        [, $headers] = $this->autenticar();
        $cliente = Cliente::factory()->create();

        $this->postJson('/api/veiculos', $this->dados(['cliente_id' => $cliente->id]), $headers)
            ->assertStatus(201)
            ->assertJsonPath('tipo_veiculo', 'carro');

        $this->postJson('/api/veiculos', $this->dados(['cliente_id' => $cliente->id, 'placa' => 'DEF2G34', 'tipo_veiculo' => 'caminhao']), $headers)
            ->assertStatus(201)
            ->assertJsonPath('tipo_veiculo', 'caminhao');

        $this->postJson('/api/veiculos', $this->dados(['cliente_id' => $cliente->id, 'placa' => 'GHI3J45', 'tipo_veiculo' => 'barco']), $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors('tipo_veiculo');
    }

    // ----------------------------------------------------- FIPE pelo tipo certo

    public function test_o_valor_da_moto_e_consultado_na_tabela_de_motos(): void
    {
        Http::fake([
            '*/motos/marcas/80/modelos/5000/anos/2022-1' => Http::response(['Valor' => 'R$ 14.500,00']),
            '*' => Http::response('tabela errada', 404),
        ]);
        [, $headers] = $this->autenticarConta('pessoa');

        $this->postJson('/api/conta/veiculos', $this->dados([
            'tipo_veiculo' => 'moto', 'fipe_marca_id' => 80, 'fipe_modelo_id' => 5000, 'fipe_ano' => '2022-1',
        ]), $headers)
            ->assertStatus(201)
            ->assertJsonPath('fipe_valor', '14500.00');

        Http::assertSent(fn ($r) => str_contains($r->url(), '/motos/marcas/80/modelos/5000/anos/2022-1'));
    }

    public function test_o_valor_do_caminhao_e_consultado_na_tabela_de_caminhoes(): void
    {
        Http::fake([
            '*/caminhoes/marcas/109/modelos/7000/anos/2020-3' => Http::response(['Valor' => 'R$ 480.000,00']),
            '*' => Http::response('tabela errada', 404),
        ]);
        [, $headers] = $this->autenticarConta('frota');

        $this->postJson('/api/conta/veiculos', $this->dados([
            'marca' => 'Volvo', 'tipo_veiculo' => 'caminhao', 'fipe_marca_id' => 109, 'fipe_modelo_id' => 7000, 'fipe_ano' => '2020-3',
        ]), $headers)
            ->assertStatus(201)
            ->assertJsonPath('fipe_valor', '480000.00');
    }

    public function test_o_valor_do_carro_continua_na_tabela_de_carros(): void
    {
        Http::fake(['*/carros/marcas/23/modelos/1/anos/2020-1' => Http::response(['Valor' => 'R$ 60.250,50'])]);
        [, $headers] = $this->autenticarConta('pessoa');

        $this->postJson('/api/conta/veiculos', $this->dados([
            'marca' => 'Fiat', 'modelo' => 'Uno', 'fipe_marca_id' => 23, 'fipe_modelo_id' => 1, 'fipe_ano' => '2020-1',
        ]), $headers)->assertJsonPath('fipe_valor', '60250.50');
    }

    public function test_atualizar_o_valor_fipe_de_uma_moto_ja_cadastrada_usa_motos(): void
    {
        Http::fake([
            '*/motos/marcas/80/modelos/5000/anos/2022-1' => Http::response(['Valor' => 'R$ 15.000,00']),
            '*' => Http::response('tabela errada', 404),
        ]);
        [, $headers] = $this->autenticarConta('pessoa');
        $veiculo = VeiculoConta::create($this->dados(['tipo_veiculo' => 'moto', 'fipe_marca_id' => 80, 'fipe_modelo_id' => 5000, 'fipe_ano' => '2022-1']));

        $this->postJson("/api/conta/veiculos/{$veiculo->id}/fipe", [], $headers)
            ->assertOk()
            ->assertJsonPath('fipe_valor', '15000.00');
    }

    public function test_mudar_so_o_tipo_refaz_a_consulta_da_fipe(): void
    {
        Http::fake([
            '*/carros/marcas/80/modelos/5000/anos/2022-1' => Http::response(['Valor' => 'R$ 90.000,00']),
            '*/motos/marcas/80/modelos/5000/anos/2022-1' => Http::response(['Valor' => 'R$ 14.000,00']),
        ]);
        [, $headers] = $this->autenticarConta('pessoa');
        $fipe = ['fipe_marca_id' => 80, 'fipe_modelo_id' => 5000, 'fipe_ano' => '2022-1'];
        $id = $this->postJson('/api/conta/veiculos', $this->dados($fipe), $headers)->assertJsonPath('fipe_valor', '90000.00')->json('id');

        $this->putJson("/api/conta/veiculos/{$id}", $this->dados($fipe + ['tipo_veiculo' => 'moto']), $headers)
            ->assertOk()
            ->assertJsonPath('fipe_valor', '14000.00');
    }

    public function test_a_fipe_da_oficina_usa_o_tipo_do_veiculo(): void
    {
        Http::fake([
            '*/caminhoes/marcas/109/modelos/7000/anos/2020-3' => Http::response(['Valor' => 'R$ 480.000,00']),
            '*' => Http::response('tabela errada', 404),
        ]);
        [, $headers] = $this->autenticar();
        $cliente = Cliente::factory()->create();

        $this->postJson('/api/veiculos', $this->dados([
            'cliente_id' => $cliente->id, 'marca' => 'Volvo', 'tipo_veiculo' => 'caminhao',
            'fipe_marca_id' => 109, 'fipe_modelo_id' => 7000, 'fipe_ano' => '2020-3',
        ]), $headers)
            ->assertStatus(201)
            ->assertJsonPath('fipe_valor', '480000.00');
    }

    // ------------------------------------------- o que só vale para carros

    public function test_a_taxa_de_licenciamento_e_so_de_carro(): void
    {
        $carro = new VeiculoConta($this->dados(['marca' => 'Fiat', 'modelo' => 'Uno', 'tipo_veiculo' => 'carro', 'fipe_valor' => 50000]));
        $moto = new VeiculoConta($this->dados(['tipo_veiculo' => 'moto', 'fipe_valor' => 14000]));
        $caminhao = new VeiculoConta($this->dados(['tipo_veiculo' => 'caminhao', 'fipe_valor' => 480000]));

        $this->assertNotNull($carro->licenciamento_valor);
        $this->assertNull($moto->licenciamento_valor);
        $this->assertNull($caminhao->licenciamento_valor);
    }

    // ----------------------------------------------- IPVA de moto e de caminhão

    private function veiculoCom(string $tipo, ?string $uf, ?float $fipe): VeiculoConta
    {
        return new VeiculoConta($this->dados(['tipo_veiculo' => $tipo, 'uf' => $uf, 'fipe_valor' => $fipe]));
    }

    public function test_ipva_da_moto_usa_a_aliquota_confirmada_do_estado(): void
    {
        // SP 2%, SC 1%, GO 3%: valores conferidos nas Sefaz e nas tabelas públicas.
        $this->assertEqualsWithDelta(280.0, $this->veiculoCom('moto', 'SP', 14000)->ipva_estimado, 0.001);
        $this->assertEqualsWithDelta(140.0, $this->veiculoCom('moto', 'SC', 14000)->ipva_estimado, 0.001);
        $this->assertEqualsWithDelta(420.0, $this->veiculoCom('moto', 'GO', 14000)->ipva_estimado, 0.001);

        $this->assertFalse($this->veiculoCom('moto', 'SP', 14000)->ipva_aliquota_media);
    }

    public function test_ipva_do_caminhao_usa_a_aliquota_confirmada_do_estado(): void
    {
        // SP 1,5%, MG 1%, GO 1,25%.
        $this->assertEqualsWithDelta(7200.0, $this->veiculoCom('caminhao', 'SP', 480000)->ipva_estimado, 0.001);
        $this->assertEqualsWithDelta(4800.0, $this->veiculoCom('caminhao', 'MG', 480000)->ipva_estimado, 0.001);
        $this->assertEqualsWithDelta(6000.0, $this->veiculoCom('caminhao', 'GO', 480000)->ipva_estimado, 0.001);

        $this->assertFalse($this->veiculoCom('caminhao', 'SP', 480000)->ipva_aliquota_media);
    }

    public function test_estado_sem_aliquota_confirmada_usa_a_media_do_tipo_e_avisa(): void
    {
        // PR (moto por cilindrada) e AP (fontes divergem) não têm alíquota confirmada.
        $moto = $this->veiculoCom('moto', 'PR', 14000);
        $caminhao = $this->veiculoCom('caminhao', 'AP', 480000);

        $this->assertEqualsWithDelta(280.0, $moto->ipva_estimado, 0.001);       // média de moto: 2%
        $this->assertTrue($moto->ipva_aliquota_media);
        $this->assertEqualsWithDelta(4800.0, $caminhao->ipva_estimado, 0.001);  // média de caminhão: 1%
        $this->assertTrue($caminhao->ipva_aliquota_media);
    }

    public function test_cada_tipo_usa_a_sua_aliquota_no_mesmo_estado(): void
    {
        // Em SP: carro 4%, moto 2%, caminhão 1,5% sobre o mesmo valor.
        $this->assertEqualsWithDelta(4000.0, $this->veiculoCom('carro', 'SP', 100000)->ipva_estimado, 0.001);
        $this->assertEqualsWithDelta(2000.0, $this->veiculoCom('moto', 'SP', 100000)->ipva_estimado, 0.001);
        $this->assertEqualsWithDelta(1500.0, $this->veiculoCom('caminhao', 'SP', 100000)->ipva_estimado, 0.001);
    }

    public function test_sem_estado_ou_sem_valor_fipe_nao_ha_estimativa(): void
    {
        $this->assertNull($this->veiculoCom('moto', null, 14000)->ipva_estimado);
        $this->assertNull($this->veiculoCom('moto', 'SP', null)->ipva_estimado);
        $this->assertNull($this->veiculoCom('caminhao', null, 480000)->ipva_estimado);
    }

    public function test_as_tabelas_de_aliquota_so_tem_estados_e_valores_plausiveis(): void
    {
        $estados = array_keys(config('tributos.estados'));

        foreach (['ipva_moto' => [0.5, 4.0], 'ipva_caminhao' => [0.5, 3.0]] as $tabela => [$minimo, $maximo]) {
            foreach (config("tributos.{$tabela}") as $uf => $aliquota) {
                $this->assertContains($uf, $estados, "{$tabela}: {$uf} não é um estado");
                $this->assertGreaterThanOrEqual($minimo, $aliquota, "{$tabela}.{$uf}");
                $this->assertLessThanOrEqual($maximo, $aliquota, "{$tabela}.{$uf}");
            }
        }

        $this->assertSame(2.0, config('tributos.aliquota_media_ipva_moto'));
        $this->assertSame(1.0, config('tributos.aliquota_media_ipva_caminhao'));
    }

    public function test_o_ipva_da_moto_aparece_na_listagem_da_conta(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        VeiculoConta::create($this->dados(['tipo_veiculo' => 'moto', 'uf' => 'SP', 'fipe_valor' => 14000]));

        $dados = $this->getJson('/api/conta/veiculos?all=1', $headers)->json('0');

        $this->assertEqualsWithDelta(280.0, $dados['ipva_estimado'], 0.001);
        $this->assertFalse($dados['ipva_aliquota_media']);
        $this->assertNull($dados['licenciamento_valor']);
    }

    public function test_moto_antiga_nao_e_marcada_como_isenta_nem_com_possivel_isencao(): void
    {
        $carroAntigo = new VeiculoConta($this->dados(['tipo_veiculo' => 'carro', 'ano' => 2001, 'ano_fabricacao' => 2001]));
        $motoAntiga = new VeiculoConta($this->dados(['tipo_veiculo' => 'moto', 'ano' => 2001, 'ano_fabricacao' => 2001]));
        $caminhaoAntigo = new VeiculoConta($this->dados(['tipo_veiculo' => 'caminhao', 'ano' => 2008, 'ano_fabricacao' => 2008]));

        $this->assertTrue($carroAntigo->isento_ipva);

        $this->assertFalse($motoAntiga->isento_ipva);
        $this->assertFalse($motoAntiga->possivel_isencao_ipva);
        $this->assertFalse($caminhaoAntigo->possivel_isencao_ipva);
        // Sem afirmar isenção, o lembrete de vencimento continua existindo.
        $this->assertNotNull($motoAntiga->proximo_vencimento_ipva);
    }

    public function test_os_vencimentos_por_final_de_placa_valem_para_moto_e_caminhao(): void
    {
        $moto = new VeiculoConta($this->dados(['placa' => 'AAA1B23', 'tipo_veiculo' => 'moto']));

        $this->assertSame('2026-05-31', $moto->proximo_vencimento_licenciamento);
    }

    public function test_ficha_do_catalogo_e_tanque_so_para_carros(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $moto = VeiculoConta::create($this->dados(['marca' => 'Fiat', 'modelo' => 'Strada', 'tipo_veiculo' => 'moto']));
        $carro = VeiculoConta::create($this->dados(['placa' => 'DEF2G34', 'marca' => 'Fiat', 'modelo' => 'Strada', 'tipo_veiculo' => 'carro']));

        // Mesmo nome de um carro do catálogo: a moto não pega a ficha nem o tanque dele.
        $this->getJson("/api/conta/veiculos/{$moto->id}/ficha-tecnica", $headers)->assertOk()->assertJsonPath('dados_catalogo', null);
        $this->getJson("/api/conta/veiculos/{$moto->id}/abastecimentos", $headers)->assertOk()->assertJsonPath('tanque_litros', null);

        $this->getJson("/api/conta/veiculos/{$carro->id}/ficha-tecnica", $headers)->assertOk();
        $this->assertNotNull($this->getJson("/api/conta/veiculos/{$carro->id}/abastecimentos", $headers)->json('tanque_litros'));
    }

    public function test_seguro_de_moto_usa_a_faixa_de_moto_e_nao_a_de_carro(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $moto = VeiculoConta::create($this->dados(['tipo_veiculo' => 'moto', 'fipe_valor' => 10000]));
        $carro = VeiculoConta::create($this->dados(['placa' => 'DEF2G34', 'tipo_veiculo' => 'carro', 'fipe_valor' => 10000]));

        // Moto: 6,7% a 10,1% do valor, médio 8,7% (IPSM, Serasa Experian). Carro: 3% a 8%, médio 4,7%.
        $this->getJson("/api/conta/veiculos/{$moto->id}/seguros", $headers)
            ->assertOk()
            ->assertJsonPath('referencia.baixo', 670)
            ->assertJsonPath('referencia.medio', 870)
            ->assertJsonPath('referencia.alto', 1010);

        $this->getJson("/api/conta/veiculos/{$carro->id}/seguros", $headers)
            ->assertOk()
            ->assertJsonPath('referencia.baixo', 300)
            ->assertJsonPath('referencia.medio', 470)
            ->assertJsonPath('referencia.alto', 800);
    }

    public function test_seguro_de_caminhao_nao_tem_faixa_de_referencia(): void
    {
        [, $headers] = $this->autenticarConta('frota');
        $caminhao = VeiculoConta::create($this->dados(['tipo_veiculo' => 'caminhao', 'fipe_valor' => 480000]));

        $this->getJson("/api/conta/veiculos/{$caminhao->id}/seguros", $headers)
            ->assertOk()
            ->assertJsonPath('referencia', null);
    }

    public function test_proposta_de_seguro_da_moto_e_comparada_com_a_media_de_moto(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $moto = VeiculoConta::create($this->dados(['tipo_veiculo' => 'moto', 'fipe_valor' => 10000]));

        // R$ 1.740 por ano é o dobro da média de moto (R$ 870): 100% acima.
        $r = $this->postJson("/api/conta/veiculos/{$moto->id}/seguros", [
            'tipo' => 'proposta', 'seguradora' => 'Porto', 'valor_anual' => 1740,
        ], $headers)->assertStatus(201);

        $this->assertEquals(100.0, $r->json('seguros.0.vs_referencia_pct'));
    }

    public function test_catalogo_de_pecas_de_moto_cai_no_catalogo_geral(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $moto = VeiculoConta::create($this->dados(['marca' => 'Fiat', 'modelo' => 'Strada', 'tipo_veiculo' => 'moto']));
        $carro = VeiculoConta::create($this->dados(['placa' => 'DEF2G34', 'marca' => 'Fiat', 'modelo' => 'Strada', 'tipo_veiculo' => 'carro']));

        $this->getJson("/api/conta/pecas-catalogo?veiculo_id={$moto->id}", $headers)->assertOk()->assertJsonPath('escopo', 'geral');
        $this->getJson("/api/conta/pecas-catalogo?veiculo_id={$carro->id}", $headers)->assertOk()->assertJsonPath('escopo', 'modelo');
    }

    public function test_ficha_da_oficina_so_para_carro(): void
    {
        [, $headers] = $this->autenticar();
        $moto = Veiculo::factory()->create(['tipo_veiculo' => 'moto', 'marca' => 'Honda', 'modelo' => 'CG 160']);

        $this->getJson("/api/veiculos/{$moto->id}/ficha-tecnica", $headers)
            ->assertOk()
            ->assertJsonPath('fonte', null);
    }
}
