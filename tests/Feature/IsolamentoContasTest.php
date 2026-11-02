<?php

namespace Tests\Feature;

use App\Models\ChecklistUso;
use App\Models\Conta;
use App\Models\Motorista;
use App\Models\Multa;
use App\Models\UsoVeiculo;
use App\Models\VeiculoConta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

/**
 * Uma empresa não lê, altera nem apaga registros de outra, mesmo sabendo o ID.
 * Os registros da outra conta ficam intactos e o endpoint responde como se não existissem.
 */
class IsolamentoContasTest extends TestCase
{
    use AutenticaUsuario;
    use RefreshDatabase;

    /** @var array<string, string> */
    private array $headersA;

    private Conta $contaA;

    private Conta $contaB;

    private VeiculoConta $veiculoB;

    private Motorista $motoristaB;

    private UsoVeiculo $usoB;

    private Multa $multaB;

    protected function setUp(): void
    {
        parent::setUp();

        [, $this->headersA] = $this->autenticarConta('frota');
        $this->contaA = Conta::find(app('conta.atual'));
        $this->contaB = Conta::create(['tipo' => 'frota', 'nome' => 'Empresa B']);

        app()->instance('conta.atual', $this->contaB->id);
        $this->veiculoB = VeiculoConta::create(['placa' => 'BBB2B22', 'marca' => 'VW', 'modelo' => 'Gol', 'ano' => 2020, 'km_atual' => 100]);
        $this->motoristaB = Motorista::create(['nome' => 'Motorista B', 'cnh_numero' => '1', 'cnh_validade' => now()->addYear()->toDateString()]);
        $this->usoB = UsoVeiculo::create([
            'veiculo_conta_id' => $this->veiculoB->id,
            'motorista_id' => $this->motoristaB->id,
            'inicio_previsto' => now()->addDay(),
            'fim_previsto' => now()->addDay()->addHours(2),
            'status' => 'agendado',
        ]);
        $this->multaB = Multa::create([
            'veiculo_conta_id' => $this->veiculoB->id,
            'data_hora' => now()->subDay(),
            'descricao' => 'Multa da empresa B',
            'situacao' => 'aberta',
        ]);
        app()->instance('conta.atual', $this->contaA->id);
    }

    /**
     * Acesso a registro de outra empresa: a resposta tem de ser "não encontrado" ou "proibido".
     * Um 422 aqui seria de validação, não de bloqueio, então não serve como prova de isolamento.
     */
    private function naoEhSucesso(TestResponse $resposta): void
    {
        $this->assertTrue(in_array($resposta->getStatusCode(), [403, 404], true), "Esperado 403 ou 404, veio {$resposta->getStatusCode()}");
    }

    public function test_veiculo_de_outra_conta_nao_e_lido_nem_alterado_nem_apagado(): void
    {
        $this->naoEhSucesso($this->getJson("/api/conta/veiculos/{$this->veiculoB->id}", $this->headersA));
        $this->naoEhSucesso($this->putJson("/api/conta/veiculos/{$this->veiculoB->id}", ['marca' => 'Hacked', 'modelo' => 'X', 'placa' => 'BBB2B22', 'ano' => 2020], $this->headersA));
        $this->naoEhSucesso($this->deleteJson("/api/conta/veiculos/{$this->veiculoB->id}", [], $this->headersA));

        $this->assertSame('VW', $this->veiculoB->fresh()->marca);
    }

    public function test_km_e_qr_de_veiculo_de_outra_conta_sao_recusados(): void
    {
        $kmAntes = (int) $this->veiculoB->fresh()->km_atual;

        $this->naoEhSucesso($this->putJson("/api/conta/veiculos/{$this->veiculoB->id}/km", ['km' => 999, 'motivo' => 'Tentativa de alterar'], $this->headersA));
        $this->naoEhSucesso($this->postJson("/api/conta/veiculos/{$this->veiculoB->id}/qr", [], $this->headersA));

        $this->assertSame($kmAntes, (int) $this->veiculoB->fresh()->km_atual);
        $this->assertNull($this->veiculoB->fresh()->qr_token);
    }

    public function test_motorista_de_outra_conta_nao_e_alterado_nem_apagado(): void
    {
        $this->naoEhSucesso($this->putJson("/api/conta/motoristas/{$this->motoristaB->id}", ['nome' => 'Hacked'], $this->headersA));
        $this->naoEhSucesso($this->deleteJson("/api/conta/motoristas/{$this->motoristaB->id}", [], $this->headersA));

        $this->assertSame('Motorista B', $this->motoristaB->fresh()->nome);
    }

    public function test_reserva_de_outra_conta_nao_e_lida_nem_cancelada_nem_apagada(): void
    {
        $this->naoEhSucesso($this->getJson("/api/conta/usos/{$this->usoB->id}", $this->headersA));
        $this->naoEhSucesso($this->postJson("/api/conta/usos/{$this->usoB->id}/cancelar", [], $this->headersA));
        $this->naoEhSucesso($this->deleteJson("/api/conta/usos/{$this->usoB->id}", [], $this->headersA));

        $this->assertSame('agendado', $this->usoB->fresh()->status);
    }

    public function test_checklist_de_outra_conta_nao_e_lido_nem_gravado(): void
    {
        $this->naoEhSucesso($this->getJson("/api/conta/usos/{$this->usoB->id}/checklist", $this->headersA));
        $this->naoEhSucesso($this->postJson("/api/conta/usos/{$this->usoB->id}/checklist", [
            'momento' => 'saida',
            'itens' => collect(ChecklistUso::ITENS)->keys()->map(fn (string $i) => ['item' => $i, 'ok' => true])->all(),
        ], $this->headersA));

        $this->assertSame(0, ChecklistUso::withoutGlobalScopes()->where('uso_veiculo_id', $this->usoB->id)->count());
    }

    public function test_multa_de_outra_conta_nao_e_alterada_nem_apagada(): void
    {
        $this->naoEhSucesso($this->putJson("/api/conta/multas/{$this->multaB->id}", ['situacao' => 'paga'], $this->headersA));
        $this->naoEhSucesso($this->deleteJson("/api/conta/multas/{$this->multaB->id}", [], $this->headersA));

        $this->assertSame('aberta', $this->multaB->fresh()->situacao);
    }

    public function test_listas_nao_mostram_os_registros_da_outra_conta(): void
    {
        $this->getJson('/api/conta/veiculos', $this->headersA)->assertOk()->assertJsonMissing(['placa' => 'BBB2B22']);
        $this->getJson('/api/conta/motoristas', $this->headersA)->assertOk()->assertJsonMissing(['nome' => 'Motorista B']);
        $this->getJson('/api/conta/multas', $this->headersA)->assertOk()->assertJsonMissing(['descricao' => 'Multa da empresa B']);
        $this->getJson('/api/conta/painel-frota', $this->headersA)->assertOk()->assertJsonMissing(['placa' => 'BBB2B22']);
    }

    public function test_multa_nao_aceita_veiculo_motorista_ou_reserva_de_outra_conta(): void
    {
        $base = ['descricao' => 'Teste', 'data_hora' => now()->subDay()->toDateTimeString()];

        $this->postJson('/api/conta/multas', $base + ['veiculo_conta_id' => $this->veiculoB->id], $this->headersA)
            ->assertStatus(422);

        $meuVeiculo = VeiculoConta::create(['placa' => 'AAA1A11', 'marca' => 'Fiat', 'modelo' => 'Strada', 'ano' => 2022]);

        $this->postJson('/api/conta/multas', $base + ['veiculo_conta_id' => $meuVeiculo->id, 'motorista_id' => $this->motoristaB->id], $this->headersA)
            ->assertStatus(422);

        $this->postJson('/api/conta/multas', $base + ['veiculo_conta_id' => $meuVeiculo->id, 'uso_veiculo_id' => $this->usoB->id], $this->headersA)
            ->assertStatus(422);

        $this->assertSame(1, Multa::withoutGlobalScopes()->where('conta_id', $this->contaB->id)->count());
        $this->assertSame(0, Multa::withoutGlobalScopes()->where('conta_id', $this->contaA->id)->count());
    }

    public function test_atualizar_multa_propria_com_ids_de_outra_conta_e_recusado(): void
    {
        app()->instance('conta.atual', $this->contaA->id);
        $meuVeiculo = VeiculoConta::create(['placa' => 'AAA1A11', 'marca' => 'Fiat', 'modelo' => 'Strada', 'ano' => 2022]);
        $minha = Multa::create(['veiculo_conta_id' => $meuVeiculo->id, 'data_hora' => now()->subDay(), 'descricao' => 'Minha', 'situacao' => 'aberta']);

        $this->putJson("/api/conta/multas/{$minha->id}", ['veiculo_conta_id' => $this->veiculoB->id], $this->headersA)
            ->assertStatus(422);
        $this->putJson("/api/conta/multas/{$minha->id}", ['motorista_id' => $this->motoristaB->id], $this->headersA)
            ->assertStatus(422);

        $this->assertSame($meuVeiculo->id, (int) $minha->fresh()->veiculo_conta_id);
        $this->assertNull($minha->fresh()->motorista_id);
    }

    public function test_reserva_que_nao_e_do_veiculo_informado_e_recusada(): void
    {
        $meuVeiculo = VeiculoConta::create(['placa' => 'AAA1A11', 'marca' => 'Fiat', 'modelo' => 'Strada', 'ano' => 2022]);
        app()->instance('conta.atual', $this->contaA->id);
        $outroDoMesmoDono = VeiculoConta::create(['placa' => 'CCC3C33', 'marca' => 'Fiat', 'modelo' => 'Uno', 'ano' => 2019]);
        $motorista = Motorista::create(['nome' => 'Meu', 'cnh_numero' => '2', 'cnh_validade' => now()->addYear()->toDateString()]);
        $usoDoOutroCarro = UsoVeiculo::create([
            'veiculo_conta_id' => $outroDoMesmoDono->id,
            'motorista_id' => $motorista->id,
            'inicio_previsto' => now()->subDay(),
            'fim_previsto' => now()->subDay()->addHours(2),
            'status' => 'concluido',
        ]);

        $this->postJson('/api/conta/multas', [
            'veiculo_conta_id' => $meuVeiculo->id,
            'uso_veiculo_id' => $usoDoOutroCarro->id,
            'data_hora' => now()->subDay()->toDateTimeString(),
            'descricao' => 'Reserva de outro carro',
        ], $this->headersA)->assertStatus(422);
    }
}
