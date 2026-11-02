<?php

namespace Tests\Feature;

use App\Models\ChecklistUso;
use App\Models\Conta;
use App\Models\Motorista;
use App\Models\Multa;
use App\Models\UsoVeiculo;
use App\Models\VeiculoConta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class FrotaDiferenciaisTest extends TestCase
{
    use AutenticaUsuario;
    use RefreshDatabase;

    /** @var array<string, string> */
    private array $headersAdmin;

    private Conta $conta;

    private VeiculoConta $veiculo;

    private Motorista $motorista;

    /** @var array<string, string> */
    private array $headersMotorista;

    protected function setUp(): void
    {
        parent::setUp();

        [, $this->headersAdmin] = $this->autenticarConta('frota');
        $this->conta = Conta::find(app('conta.atual'));
        app()->instance('conta.atual', $this->conta->id);

        $this->veiculo = VeiculoConta::create(['placa' => 'AAA1A11', 'marca' => 'Fiat', 'modelo' => 'Strada', 'ano' => 2022]);
        $this->motorista = Motorista::create(['nome' => 'Maria', 'cnh_numero' => '123', 'cnh_validade' => now()->addYears(2)->toDateString()]);

        [$usuario, $this->headersMotorista] = $this->autenticarConta('frota', $this->conta);
        $usuario->forceFill(['papel_frota' => 'motorista'])->save();
        $this->motorista->forceFill(['user_id' => $usuario->id])->save();
        app()->instance('conta.atual', $this->conta->id);
    }

    private function uso(array $extra = []): UsoVeiculo
    {
        return UsoVeiculo::create(array_merge([
            'veiculo_conta_id' => $this->veiculo->id,
            'motorista_id' => $this->motorista->id,
            'inicio_previsto' => now()->subHour(),
            'fim_previsto' => now()->addHours(3),
            'status' => 'agendado',
        ], $extra));
    }

    private function checklistCompleto(bool $tudoOk = true): array
    {
        return [
            'momento' => 'saida',
            'itens' => collect(ChecklistUso::ITENS)
                ->keys()->map(fn (string $item) => ['item' => $item, 'ok' => $tudoOk])->all(),
        ];
    }

    public function test_motorista_faz_o_checklist_da_propria_reserva(): void
    {
        $uso = $this->uso();

        $this->postJson("/api/conta/usos/{$uso->id}/checklist", $this->checklistCompleto(), $this->headersMotorista)
            ->assertStatus(201)
            ->assertJsonPath('problemas', 0);

        $this->postJson("/api/conta/usos/{$uso->id}/checklist", $this->checklistCompleto(), $this->headersMotorista)
            ->assertStatus(422);
    }

    public function test_checklist_exige_todos_os_itens_e_registra_os_problemas(): void
    {
        $uso = $this->uso();
        $incompleto = ['momento' => 'saida', 'itens' => [['item' => 'pneus', 'ok' => false]]];

        $this->postJson("/api/conta/usos/{$uso->id}/checklist", $incompleto, $this->headersMotorista)->assertStatus(422);

        $completo = $this->checklistCompleto(false);
        $this->postJson("/api/conta/usos/{$uso->id}/checklist", $completo, $this->headersMotorista)
            ->assertStatus(201)
            ->assertJsonPath('problemas', count(ChecklistUso::ITENS));
    }

    public function test_motorista_nao_ve_o_checklist_de_reserva_de_outro(): void
    {
        $outro = Motorista::create(['nome' => 'Outro', 'cnh_numero' => '9', 'cnh_validade' => now()->addYear()->toDateString()]);
        $uso = $this->uso(['motorista_id' => $outro->id]);

        $this->getJson("/api/conta/usos/{$uso->id}/checklist", $this->headersMotorista)->assertStatus(404);
    }

    public function test_qr_do_veiculo_mostra_ao_motorista_so_as_reservas_dele_de_hoje(): void
    {
        $this->uso();
        $outro = Motorista::create(['nome' => 'Outro', 'cnh_numero' => '9', 'cnh_validade' => now()->addYear()->toDateString()]);
        $this->uso(['motorista_id' => $outro->id]);

        $token = $this->postJson("/api/conta/veiculos/{$this->veiculo->id}/qr", [], $this->headersAdmin)
            ->assertOk()->json('token');

        $this->getJson("/api/conta/qr/{$token}", $this->headersMotorista)
            ->assertOk()
            ->assertJsonPath('veiculo.placa', 'AAA1A11')
            ->assertJsonCount(1, 'reservas');
    }

    public function test_qr_desconhecido_e_recusado(): void
    {
        $this->getJson('/api/conta/qr/nao-existe', $this->headersMotorista)->assertStatus(404);
    }

    public function test_multa_sem_responsavel_informado_fica_com_quem_estava_com_o_veiculo(): void
    {
        $uso = $this->uso(['saida_em' => now()->subHour(), 'inicio_previsto' => now()->subHour()]);

        $this->postJson('/api/conta/multas', [
            'veiculo_conta_id' => $this->veiculo->id,
            'data_hora' => now()->subMinutes(10)->toDateTimeString(),
            'descricao' => 'Excesso de velocidade',
            'valor' => 130.16,
            'pontos' => 4,
        ], $this->headersAdmin)
            ->assertStatus(201)
            ->assertJsonPath('motorista.nome', 'Maria')
            ->assertJsonPath('situacao', 'aberta');

        $this->assertSame($uso->id, Multa::first()->uso_veiculo_id);
    }

    public function test_motorista_nao_mexe_em_multas(): void
    {
        $this->getJson('/api/conta/multas', $this->headersMotorista)->assertStatus(403);
    }

    public function test_alertas_mostram_cnh_vencida(): void
    {
        Motorista::create(['nome' => 'Vencido', 'cnh_numero' => '5', 'cnh_validade' => now()->subDay()->toDateString()]);

        $this->getJson('/api/conta/alertas', $this->headersAdmin)
            ->assertOk()
            ->assertJsonFragment(['nome' => 'Vencido', 'situacao' => 'vencida']);
    }

    public function test_painel_calcula_as_horas_usadas_do_veiculo_no_periodo(): void
    {
        $dia = now()->toDateString();
        $this->uso([
            'inicio_previsto' => "{$dia} 08:00:00",
            'fim_previsto' => "{$dia} 10:00:00",
            'saida_em' => "{$dia} 08:00:00",
            'chegada_em' => "{$dia} 10:00:00",
            'status' => 'concluido',
            'km_saida' => 1000,
            'km_chegada' => 1085,
        ]);

        $this->getJson("/api/conta/painel-frota?de={$dia}&ate={$dia}", $this->headersAdmin)
            ->assertOk()
            ->assertJsonPath('veiculos.0.horas_usadas', 2)
            ->assertJsonPath('veiculos.0.km_rodados', 85)
            ->assertJsonPath('veiculos.0.reservas', 1);
    }
}
