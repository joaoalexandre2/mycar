<?php

namespace Tests\Feature;

use App\Models\Motorista;
use App\Models\UsoVeiculo;
use App\Models\VeiculoConta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

/**
 * Devolução atrasada: a chegada é sempre registrada, e quem ficou afetado é avisado.
 * Afetado é a reserva do mesmo veículo ou do mesmo motorista (em outro carro) que começa antes da volta.
 */
class DevolucaoAtrasadaTest extends TestCase
{
    use AutenticaUsuario;
    use RefreshDatabase;

    /** @var array<string, string> */
    private array $headers;

    private VeiculoConta $strada;

    private Motorista $motorista;

    protected function setUp(): void
    {
        parent::setUp();

        [, $this->headers] = $this->autenticarConta('frota');

        $this->strada = VeiculoConta::create(['placa' => 'AAA1A11', 'marca' => 'Fiat', 'modelo' => 'Strada', 'ano' => 2022]);
        $this->motorista = Motorista::create(['nome' => 'João', 'cnh_numero' => '123', 'cnh_validade' => now()->addYears(2)->toDateString()]);
    }

    /** Uso da Strada que saiu há 4 horas e devia ter voltado há 1 hora. */
    private function usoAtrasado(): UsoVeiculo
    {
        return UsoVeiculo::create([
            'veiculo_conta_id' => $this->strada->id,
            'motorista_id' => $this->motorista->id,
            'inicio_previsto' => now()->subHours(4),
            'fim_previsto' => now()->subHour(),
            'saida_em' => now()->subHours(4),
            'status' => 'em_uso',
        ]);
    }

    private function reserva(array $extra = []): UsoVeiculo
    {
        return UsoVeiculo::create(array_merge([
            'veiculo_conta_id' => $this->strada->id,
            'motorista_id' => $this->motorista->id,
            'inicio_previsto' => now()->subHour(),
            'fim_previsto' => now()->addHours(2),
            'status' => 'agendado',
        ], $extra));
    }

    private function devolver(UsoVeiculo $uso): TestResponse
    {
        return $this->postJson("/api/conta/usos/{$uso->id}/chegada", ['km_chegada' => 500], $this->headers);
    }

    public function test_devolucao_depois_do_inicio_de_reserva_futura_gera_alerta_sem_impedir_o_registro(): void
    {
        $emUso = $this->usoAtrasado();
        $afetada = $this->reserva();

        $this->devolver($emUso)
            ->assertOk()
            ->assertJsonPath('status', 'concluido')
            ->assertJsonPath('alerta_conflito.0.uso_id', $afetada->id)
            ->assertJsonPath('alerta_conflito.0.motivo', 'veiculo');

        $this->assertDatabaseHas('usos_veiculo_eventos', [
            'uso_veiculo_id' => $afetada->id,
            'acao' => 'conflito_por_devolucao',
        ]);
    }

    public function test_motorista_que_ficou_com_o_carro_atrasado_e_avisado_na_reserva_de_outro_veiculo(): void
    {
        $saveiro = VeiculoConta::create(['placa' => 'BBB2B22', 'marca' => 'VW', 'modelo' => 'Saveiro', 'ano' => 2021]);
        $outroMotorista = Motorista::create(['nome' => 'Carla', 'cnh_numero' => '456', 'cnh_validade' => now()->addYears(2)->toDateString()]);

        $emUso = $this->usoAtrasado();
        $reservaDoMotorista = $this->reserva([
            'veiculo_conta_id' => $saveiro->id,
            'inicio_previsto' => now()->subMinutes(30),
            'fim_previsto' => now()->addHours(3),
        ]);
        // Não é do carro nem do motorista: não deve aparecer.
        $this->reserva([
            'veiculo_conta_id' => $saveiro->id,
            'motorista_id' => $outroMotorista->id,
            'inicio_previsto' => now()->subMinutes(20),
            'fim_previsto' => now()->addHours(3),
        ]);
        // Começa depois da volta: não foi atingida.
        $this->reserva([
            'veiculo_conta_id' => $saveiro->id,
            'inicio_previsto' => now()->addHours(2),
            'fim_previsto' => now()->addHours(4),
        ]);

        $this->devolver($emUso)
            ->assertOk()
            ->assertJsonCount(1, 'alerta_conflito')
            ->assertJsonPath('alerta_conflito.0.uso_id', $reservaDoMotorista->id)
            ->assertJsonPath('alerta_conflito.0.motivo', 'motorista')
            ->assertJsonPath('alerta_conflito.0.veiculo', 'BBB2B22')
            ->assertJsonPath('alerta_conflito.0.motorista', 'João');
    }
}
