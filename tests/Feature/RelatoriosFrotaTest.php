<?php

namespace Tests\Feature;

use App\Models\Abastecimento;
use App\Models\CategoriaDespesa;
use App\Models\Conta;
use App\Models\Documento;
use App\Models\Motorista;
use App\Models\Multa;
use App\Models\Ocorrencia;
use App\Models\UsoVeiculo;
use App\Models\VeiculoConta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class RelatoriosFrotaTest extends TestCase
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

        $this->strada = VeiculoConta::create(['placa' => 'AAA1A11', 'marca' => 'Fiat', 'modelo' => 'Strada', 'ano' => 2022, 'km_atual' => 1200]);
        $this->joao = Motorista::create(['nome' => 'João', 'cnh_numero' => '123', 'cnh_validade' => now()->addYears(2)->toDateString()]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function uso(array $extra = []): UsoVeiculo
    {
        return UsoVeiculo::create(array_merge([
            'veiculo_conta_id' => $this->strada->id,
            'motorista_id' => $this->joao->id,
            'inicio_previsto' => '2026-03-05 08:00:00',
            'fim_previsto' => '2026-03-05 10:00:00',
            'saida_em' => '2026-03-05 08:00:00',
            'chegada_em' => '2026-03-05 10:00:00',
            'km_saida' => 1000,
            'km_chegada' => 1100,
            'status' => 'concluido',
        ], $extra));
    }

    public function test_catalogo_lista_os_doze_relatorios_e_nao_aparece_para_motorista(): void
    {
        $this->getJson('/api/conta/relatorios', $this->headers)
            ->assertOk()
            ->assertJsonCount(12);

        [$usuario, $headersMotorista] = $this->autenticarConta('frota', $this->conta);
        $usuario->forceFill(['papel_frota' => 'motorista'])->save();
        app()->instance('conta.atual', $this->conta->id);

        $this->getJson('/api/conta/relatorios', $headersMotorista)->assertStatus(403);
    }

    public function test_utilizacao_soma_horas_e_km_dos_usos_concluidos_do_periodo(): void
    {
        $this->uso();
        $this->uso(['saida_em' => '2026-02-01 08:00:00', 'chegada_em' => '2026-02-01 09:00:00']);

        $linhas = $this->getJson('/api/conta/relatorios/utilizacao?de=2026-03-01&ate=2026-03-31', $this->headers)
            ->assertOk()
            ->json('linhas');

        $this->assertCount(1, $linhas);
        $this->assertSame(1, $linhas[0]['usos']);
        $this->assertEqualsWithDelta(2.0, $linhas[0]['horas_em_uso'], 0.01);
        $this->assertSame(100, $linhas[0]['km_rodados']);
    }

    public function test_abastecimentos_trazem_valor_por_litro(): void
    {
        Abastecimento::create(['veiculo_conta_id' => $this->strada->id, 'data' => '2026-03-05', 'km' => 1000, 'litros' => 40, 'valor_total' => 200, 'combustivel' => 'gasolina', 'posto' => 'Posto Centro']);

        $this->getJson('/api/conta/relatorios/abastecimentos', $this->headers)
            ->assertOk()
            ->assertJsonPath('linhas.0.valor_litro', 5)
            ->assertJsonPath('linhas.0.placa', 'AAA1A11');
    }

    public function test_consumo_divide_os_km_pelos_litros(): void
    {
        Abastecimento::create(['veiculo_conta_id' => $this->strada->id, 'data' => '2026-03-02', 'km' => 1000, 'litros' => 25, 'valor_total' => 125]);
        Abastecimento::create(['veiculo_conta_id' => $this->strada->id, 'data' => '2026-03-09', 'km' => 1100, 'litros' => 25, 'valor_total' => 125]);

        $this->getJson('/api/conta/relatorios/consumo', $this->headers)
            ->assertOk()
            ->assertJsonPath('linhas.0.km_rodados', 100)
            ->assertJsonPath('linhas.0.km_por_litro', 2);
    }

    public function test_despesas_reunem_as_origens_e_o_filtro_de_categoria_fica_so_nas_avulsas(): void
    {
        Abastecimento::create(['veiculo_conta_id' => $this->strada->id, 'data' => '2026-03-05', 'km' => 1000, 'litros' => 40, 'valor_total' => 200]);
        Multa::create(['veiculo_conta_id' => $this->strada->id, 'data_hora' => '2026-03-05 10:00:00', 'descricao' => 'Velocidade', 'valor' => 100, 'situacao' => Multa::ABERTA]);

        $origens = collect($this->getJson('/api/conta/relatorios/despesas', $this->headers)->json('linhas'))->pluck('origem')->all();
        $this->assertEqualsCanonicalizing(['Combustível', 'Multa'], $origens);

        $categoria = CategoriaDespesa::where('nome', 'Pedágio')->first()
            ?? CategoriaDespesa::garantirPadroes() ?? CategoriaDespesa::where('nome', 'Pedágio')->first();

        $this->getJson("/api/conta/relatorios/despesas?categoria_id={$categoria->id}", $this->headers)
            ->assertOk()
            ->assertJsonCount(0, 'linhas');
    }

    public function test_vencimentos_marcam_o_documento_vencido(): void
    {
        Documento::create(['veiculo_conta_id' => $this->strada->id, 'tipo' => 'crlv', 'vencimento' => '2026-03-10']);
        Documento::create(['veiculo_conta_id' => $this->strada->id, 'tipo' => 'vistoria', 'vencimento' => '2026-04-20']);

        $linhas = $this->getJson('/api/conta/relatorios/vencimentos', $this->headers)->assertOk()->json('linhas');

        $this->assertSame('vencido', $linhas[0]['situacao']);
        $this->assertSame('vence em 36 dia(s)', $linhas[1]['situacao']);
    }

    public function test_multas_e_ocorrencias_aparecem_com_o_responsavel(): void
    {
        Multa::create(['veiculo_conta_id' => $this->strada->id, 'motorista_id' => $this->joao->id, 'data_hora' => '2026-03-05 10:00:00', 'descricao' => 'Velocidade', 'valor' => 100, 'pontos' => 4, 'situacao' => Multa::ABERTA]);
        Ocorrencia::create(['conta_id' => $this->conta->id, 'veiculo_conta_id' => $this->strada->id, 'tipo' => 'acidente', 'data_hora' => '2026-03-05 10:00:00', 'descricao' => 'Batida', 'status' => 'aberta', 'prejuizo_estimado' => 900]);

        $this->getJson('/api/conta/relatorios/multas', $this->headers)
            ->assertOk()
            ->assertJsonPath('linhas.0.motorista', 'João');

        $this->getJson('/api/conta/relatorios/ocorrencias', $this->headers)
            ->assertOk()
            ->assertJsonPath('linhas.0.tipo', 'Acidente')
            ->assertJsonPath('linhas.0.prejuizo', 900);
    }

    public function test_csv_vem_com_bom_separador_ponto_e_virgula_e_virgula_decimal(): void
    {
        Abastecimento::create(['veiculo_conta_id' => $this->strada->id, 'data' => '2026-03-05', 'km' => 1000, 'litros' => 40, 'valor_total' => 200, 'combustivel' => 'gasolina']);

        $resposta = $this->get('/api/conta/relatorios/abastecimentos/csv', $this->headers)->assertOk();

        $this->assertStringContainsString('text/csv', $resposta->headers->get('Content-Type'));
        $conteudo = $resposta->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $conteudo);
        $this->assertStringContainsString('Data;Placa;Km;Litros', $conteudo);
        $this->assertStringContainsString('05/03/2026;AAA1A11;1000;40,00;gasolina', $conteudo);
    }

    public function test_relatorio_inexistente_e_404(): void
    {
        $this->getJson('/api/conta/relatorios/nao-existe', $this->headers)->assertStatus(404);
    }

    public function test_relatorio_nao_mostra_dados_de_outra_conta(): void
    {
        $contaB = Conta::create(['tipo' => 'frota', 'nome' => 'Empresa B']);
        app()->instance('conta.atual', $contaB->id);
        $veiculoB = VeiculoConta::create(['placa' => 'BBB2B22', 'marca' => 'VW', 'modelo' => 'Gol', 'ano' => 2020]);
        Abastecimento::create(['veiculo_conta_id' => $veiculoB->id, 'data' => '2026-03-05', 'km' => 500, 'litros' => 30, 'valor_total' => 150]);
        app()->instance('conta.atual', $this->conta->id);

        $this->getJson('/api/conta/relatorios/abastecimentos', $this->headers)->assertJsonCount(0, 'linhas');
    }
}
