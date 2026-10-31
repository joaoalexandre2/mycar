<?php

namespace Tests\Feature;

use App\Models\CentroCusto;
use App\Models\Conta;
use App\Models\Motorista;
use App\Models\UsoVeiculo;
use App\Models\UsoVeiculoCentro;
use App\Models\VeiculoConta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class CentroCustoHistoricoTest extends TestCase
{
    use AutenticaUsuario;
    use RefreshDatabase;

    /** @var array<string, string> */
    private array $headers;

    private int $contaId;

    private int $usoId;

    private CentroCusto $vendas;

    private CentroCusto $entregas;

    protected function setUp(): void
    {
        parent::setUp();
        [, $this->headers] = $this->autenticarConta('frota');
        $this->contaId = app('conta.atual');

        $this->vendas = CentroCusto::create(['nome' => 'Vendas']);
        $this->entregas = CentroCusto::create(['nome' => 'Entregas']);

        $veiculo = VeiculoConta::create(['placa' => 'AAA1B25', 'marca' => 'Fiat', 'modelo' => 'Strada', 'ano' => 2022]);
        $motorista = Motorista::create(['nome' => 'Maria', 'cnh_numero' => '123456', 'cnh_validade' => '2035-01-01']);

        $this->usoId = $this->postJson('/api/conta/usos', [
            'veiculo_conta_id' => $veiculo->id,
            'motorista_id' => $motorista->id,
            'centro_custo_id' => $this->vendas->id,
            'inicio_previsto' => '2026-11-10 08:00',
            'fim_previsto' => '2026-11-10 12:00',
        ], $this->headers)->assertStatus(201)->json('id');
    }

    public function test_administrador_troca_o_centro_e_o_anterior_fica_registrado(): void
    {
        $this->putJson("/api/conta/usos/{$this->usoId}/centro-custo", [
            'centro_custo_id' => $this->entregas->id,
            'motivo' => 'Lançado errado pelo motorista',
        ], $this->headers)->assertOk()->assertJsonPath('centro_custo_id', $this->entregas->id);

        $this->getJson("/api/conta/usos/{$this->usoId}/centros-historico", $this->headers)
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.de.nome', 'Vendas')
            ->assertJsonPath('0.para.nome', 'Entregas')
            ->assertJsonPath('0.motivo', 'Lançado errado pelo motorista');

        // Também aparece na trilha de auditoria do uso.
        $acoes = array_column($this->getJson("/api/conta/usos/{$this->usoId}/eventos", $this->headers)->json(), 'acao');
        $this->assertContains('trocou_centro', $acoes);
    }

    public function test_o_historico_guarda_o_nome_mesmo_se_o_centro_for_renomeado(): void
    {
        $this->putJson("/api/conta/usos/{$this->usoId}/centro-custo", [
            'centro_custo_id' => $this->entregas->id, 'motivo' => 'Ajuste de custo',
        ], $this->headers)->assertOk();

        $this->vendas->update(['nome' => 'Comercial']);

        $this->getJson('/api/conta/centros-custo/historico', $this->headers)
            ->assertOk()
            ->assertJsonPath('0.de.nome', 'Vendas');
    }

    public function test_troca_funciona_em_uso_concluido_e_exige_motivo(): void
    {
        UsoVeiculo::whereKey($this->usoId)->update(['status' => 'concluido']);

        $this->putJson("/api/conta/usos/{$this->usoId}/centro-custo", [
            'centro_custo_id' => $this->entregas->id,
        ], $this->headers)->assertStatus(422);

        $this->putJson("/api/conta/usos/{$this->usoId}/centro-custo", [
            'centro_custo_id' => $this->entregas->id, 'motivo' => 'Reclassificação mensal',
        ], $this->headers)->assertOk();
    }

    public function test_nao_troca_para_o_mesmo_centro(): void
    {
        $this->putJson("/api/conta/usos/{$this->usoId}/centro-custo", [
            'centro_custo_id' => $this->vendas->id, 'motivo' => 'Sem mudança real',
        ], $this->headers)->assertStatus(422);

        $this->assertSame(0, UsoVeiculoCentro::count());
    }

    public function test_editar_o_uso_com_outro_centro_tambem_entra_no_historico(): void
    {
        $uso = UsoVeiculo::find($this->usoId);

        $this->putJson("/api/conta/usos/{$this->usoId}", [
            'veiculo_conta_id' => $uso->veiculo_conta_id,
            'motorista_id' => $uso->motorista_id,
            'centro_custo_id' => $this->entregas->id,
            'inicio_previsto' => '2026-11-10 08:00',
            'fim_previsto' => '2026-11-10 12:00',
        ], $this->headers)->assertOk();

        $this->assertSame(1, UsoVeiculoCentro::count());
    }

    public function test_relatorio_filtra_por_centro_e_sobrevive_ao_uso_apagado(): void
    {
        $this->putJson("/api/conta/usos/{$this->usoId}/centro-custo", [
            'centro_custo_id' => $this->entregas->id, 'motivo' => 'Ajuste de custo',
        ], $this->headers)->assertOk();

        $outro = CentroCusto::create(['nome' => 'Compras']);

        $this->getJson("/api/conta/centros-custo/historico?centro_custo_id={$outro->id}", $this->headers)
            ->assertOk()->assertJsonCount(0);
        $this->getJson("/api/conta/centros-custo/historico?centro_custo_id={$this->vendas->id}", $this->headers)
            ->assertOk()->assertJsonCount(1);

        $this->deleteJson("/api/conta/usos/{$this->usoId}", [], $this->headers)->assertOk();
        $this->getJson('/api/conta/centros-custo/historico', $this->headers)->assertOk()->assertJsonCount(1);
    }

    public function test_motorista_nao_troca_nem_ve_o_historico(): void
    {
        [$user, $headersMotorista] = $this->autenticarConta('frota', Conta::find($this->contaId));
        $user->forceFill(['papel_frota' => 'motorista'])->save();
        app()->instance('conta.atual', $this->contaId);

        $this->putJson("/api/conta/usos/{$this->usoId}/centro-custo", [
            'centro_custo_id' => $this->entregas->id, 'motivo' => 'Tentativa indevida',
        ], $headersMotorista)->assertStatus(403);
        $this->getJson('/api/conta/centros-custo/historico', $headersMotorista)->assertStatus(403);
    }
}
