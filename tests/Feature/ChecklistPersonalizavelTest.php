<?php

namespace Tests\Feature;

use App\Models\ChecklistModelo;
use App\Models\ChecklistUso;
use App\Models\Conta;
use App\Models\Motorista;
use App\Models\UsoVeiculo;
use App\Models\VeiculoConta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

/**
 * Checklist personalizável: a empresa monta os próprios itens, com OK, problema ou não se aplica.
 */
class ChecklistPersonalizavelTest extends TestCase
{
    use AutenticaUsuario;
    use RefreshDatabase;

    /** @var array<string, string> */
    private array $headers;

    private Conta $conta;

    private UsoVeiculo $uso;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-10 12:00:00');

        [, $this->headers] = $this->autenticarConta('frota');
        $this->conta = Conta::find(app('conta.atual'));

        $veiculo = VeiculoConta::create(['placa' => 'AAA1A11', 'marca' => 'Fiat', 'modelo' => 'Strada', 'ano' => 2022]);
        $joao = Motorista::create(['nome' => 'João', 'cnh_numero' => '123', 'cnh_validade' => now()->addYears(2)->toDateString()]);

        $this->uso = UsoVeiculo::create([
            'veiculo_conta_id' => $veiculo->id,
            'motorista_id' => $joao->id,
            'inicio_previsto' => '2026-11-10 08:00:00',
            'fim_previsto' => '2026-11-10 18:00:00',
            'saida_em' => '2026-11-10 08:05:00',
            'km_saida' => 1000,
            'status' => 'em_uso',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** Cria um modelo pela API, como a tela faz. */
    private function modelo(string $nome, string $momento, array $itens): int
    {
        return $this->postJson('/api/conta/checklists/modelos', [
            'nome' => $nome,
            'momento' => $momento,
            'itens' => $itens,
        ], $this->headers)->assertStatus(201)->json('id');
    }

    private function checklist(array $corpo): TestResponse
    {
        return $this->postJson("/api/conta/usos/{$this->uso->id}/checklist", $corpo, $this->headers);
    }

    public function test_o_modelo_padrao_traz_os_itens_de_sempre_e_o_formato_antigo_continua_aceito(): void
    {
        $this->getJson("/api/conta/usos/{$this->uso->id}/checklist", $this->headers)
            ->assertOk()
            ->assertJsonPath('modelos.0.nome', 'Padrão da frota')
            ->assertJsonCount(7, 'modelos.0.itens');

        $itensAntigos = collect(ChecklistUso::ITENS)->keys()->map(fn (string $chave) => ['item' => $chave, 'ok' => true])->all();

        $this->checklist(['momento' => 'saida', 'itens' => $itensAntigos])->assertStatus(201);
    }

    public function test_so_os_obrigatorios_sao_exigidos_e_item_de_outro_modelo_e_recusado(): void
    {
        $modeloId = $this->modelo('Carro de entrega', 'saida', [
            ['rotulo' => 'Pneus', 'obrigatorio' => true],
            ['rotulo' => 'Caixa de ferramentas', 'obrigatorio' => false],
        ]);

        $this->checklist(['momento' => 'saida', 'checklist_modelo_id' => $modeloId, 'itens' => [['item' => 'pneus', 'situacao' => 'ok']]])
            ->assertStatus(201)
            ->assertJsonPath('modelo.nome', 'Carro de entrega');

        $this->postJson("/api/conta/usos/{$this->uso->id}/checklist", [
            'momento' => 'devolucao',
            'checklist_modelo_id' => $modeloId,
            'itens' => [['item' => 'pneus', 'situacao' => 'ok']],
        ], $this->headers)->assertStatus(422);

        $semObrigatorio = $this->postJson("/api/conta/usos/{$this->uso->id}/checklist", [
            'momento' => 'devolucao',
            'itens' => [['item' => 'caixa-de-ferramentas', 'situacao' => 'ok']],
        ], $this->headers);
        $semObrigatorio->assertStatus(422);
    }

    public function test_problema_pede_descricao_e_nao_se_aplica_nao_conta_como_problema(): void
    {
        $modeloId = $this->modelo('Entrega', 'ambos', [
            ['rotulo' => 'Pneus'],
            ['rotulo' => 'Estepe'],
        ]);

        $this->checklist(['momento' => 'saida', 'checklist_modelo_id' => $modeloId, 'itens' => [
            ['item' => 'pneus', 'situacao' => 'problema'],
            ['item' => 'estepe', 'situacao' => 'na'],
        ]])->assertStatus(422);

        $this->checklist(['momento' => 'saida', 'checklist_modelo_id' => $modeloId, 'itens' => [
            ['item' => 'pneus', 'situacao' => 'problema', 'observacao' => 'Pneu traseiro carecado'],
            ['item' => 'estepe', 'situacao' => 'na'],
        ]])
            ->assertStatus(201)
            ->assertJsonPath('problemas', 1);

        $this->assertSame('na', ChecklistUso::first()->itens[1]['situacao']);
        $this->assertTrue(ChecklistUso::first()->itens[1]['ok']);
    }

    public function test_modelo_de_outro_momento_nao_serve(): void
    {
        $modeloSaida = $this->modelo('Só saída', 'saida', [['rotulo' => 'Pneus']]);

        $this->checklist(['momento' => 'devolucao', 'checklist_modelo_id' => $modeloSaida, 'itens' => [['item' => 'pneus', 'situacao' => 'ok']]])
            ->assertStatus(422);
    }

    public function test_modelo_de_outra_conta_nao_e_usado(): void
    {
        $contaB = Conta::create(['tipo' => 'frota', 'nome' => 'Empresa B']);
        app()->instance('conta.atual', $contaB->id);
        $modeloB = ChecklistModelo::create(['nome' => 'Da outra empresa', 'momento' => 'ambos', 'ativo' => true]);
        app()->instance('conta.atual', $this->conta->id);

        $this->checklist(['momento' => 'saida', 'checklist_modelo_id' => $modeloB->id, 'itens' => [['item' => 'x', 'situacao' => 'ok']]])
            ->assertStatus(422);
    }

    public function test_modelo_usado_nao_some_e_o_que_nao_foi_usado_pode_ser_apagado(): void
    {
        $usado = $this->modelo('Usado', 'ambos', [['rotulo' => 'Pneus']]);
        $livre = $this->modelo('Livre', 'ambos', [['rotulo' => 'Luzes']]);

        $this->checklist(['momento' => 'saida', 'checklist_modelo_id' => $usado, 'itens' => [['item' => 'pneus', 'situacao' => 'ok']]])->assertStatus(201);

        $this->deleteJson("/api/conta/checklists/modelos/{$usado}", [], $this->headers)->assertStatus(422);
        $this->deleteJson("/api/conta/checklists/modelos/{$livre}", [], $this->headers)->assertStatus(204);
    }

    public function test_motorista_nao_monta_modelos(): void
    {
        [$usuario, $headersMotorista] = $this->autenticarConta('frota', $this->conta);
        $usuario->forceFill(['papel_frota' => 'motorista'])->save();
        app()->instance('conta.atual', $this->conta->id);

        $this->postJson('/api/conta/checklists/modelos', ['nome' => 'Meu', 'momento' => 'ambos', 'itens' => [['rotulo' => 'Pneus']]], $headersMotorista)
            ->assertStatus(403);
    }

    public function test_chave_do_item_e_gerada_do_rotulo_e_fica_estavel_na_edicao(): void
    {
        $modeloId = $this->modelo('Com acento', 'ambos', [['rotulo' => 'Nível de óleo'], ['rotulo' => 'Nível de óleo']]);

        $modelo = collect($this->getJson('/api/conta/checklists/modelos', $this->headers)->json())->firstWhere('id', $modeloId);
        $this->assertSame(['nivel-de-oleo', 'nivel-de-oleo-2'], collect($modelo['itens'])->pluck('chave')->all());

        // Na edição, a chave que veio do servidor é mantida mesmo com outro rótulo.
        $this->putJson("/api/conta/checklists/modelos/{$modeloId}", [
            'nome' => 'Com acento',
            'momento' => 'ambos',
            'itens' => [
                ['chave' => 'nivel-de-oleo', 'rotulo' => 'Nível do óleo do motor'],
                ['rotulo' => 'Pneus'],
            ],
        ], $this->headers)
            ->assertOk()
            ->assertJsonPath('itens.0.chave', 'nivel-de-oleo')
            ->assertJsonPath('itens.1.chave', 'pneus');
    }
}
