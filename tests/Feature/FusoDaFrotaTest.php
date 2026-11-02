<?php

namespace Tests\Feature;

use App\Models\Motorista;
use App\Models\UsoVeiculo;
use App\Models\VeiculoConta;
use App\Support\FusoDaFrota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

/**
 * Contrato de datas da frota: a empresa digita e vê o horário de Brasília; o banco guarda UTC.
 */
class FusoDaFrotaTest extends TestCase
{
    use AutenticaUsuario;
    use RefreshDatabase;

    /** @var array<string, string> */
    private array $headers;

    private VeiculoConta $veiculo;

    private Motorista $motorista;

    protected function setUp(): void
    {
        parent::setUp();

        [, $this->headers] = $this->autenticarConta('frota');

        $this->veiculo = VeiculoConta::create(['placa' => 'AAA1A11', 'marca' => 'Fiat', 'modelo' => 'Strada', 'ano' => 2022]);
        $this->motorista = Motorista::create(['nome' => 'Maria', 'cnh_numero' => '123', 'cnh_validade' => now()->addYears(2)->toDateString()]);
    }

    public function test_texto_sem_fuso_e_horario_de_brasilia_e_vira_utc(): void
    {
        $instante = FusoDaFrota::entrada('2026-11-10T08:00');

        $this->assertSame('2026-11-10 11:00:00', $instante->format('Y-m-d H:i:s'));
    }

    public function test_texto_com_fuso_e_respeitado(): void
    {
        $this->assertSame('2026-11-10 11:00:00', FusoDaFrota::entrada('2026-11-10T08:00-03:00')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-10 11:00:00', FusoDaFrota::entrada('2026-11-10T11:00:00Z')->format('Y-m-d H:i:s'));
    }

    public function test_reserva_digitada_as_8h_e_gravada_as_11h_utc_e_mostrada_as_8h_com_menos_3(): void
    {
        $resposta = $this->postJson('/api/conta/usos', [
            'veiculo_conta_id' => $this->veiculo->id,
            'motorista_id' => $this->motorista->id,
            'inicio_previsto' => '2026-11-10T08:00',
            'fim_previsto' => '2026-11-10T18:00',
        ], $this->headers)->assertStatus(201);

        $uso = UsoVeiculo::find($resposta->json('id'));
        $this->assertSame('2026-11-10 11:00:00', $uso->getRawOriginal('inicio_previsto'));

        $this->assertStringStartsWith('2026-11-10T08:00:00-03:00', $resposta->json('inicio_previsto'));
    }

    public function test_devolucao_depois_do_inicio_de_reserva_futura_gera_alerta_sem_impedir_o_registro(): void
    {
        // Saiu há 4 horas com uma reserva que começou há 1 hora; a devolução agora invade essa reserva.
        $emUso = UsoVeiculo::create([
            'veiculo_conta_id' => $this->veiculo->id,
            'motorista_id' => $this->motorista->id,
            'inicio_previsto' => now()->subHours(4),
            'fim_previsto' => now()->subHour(),
            'saida_em' => now()->subHours(4),
            'status' => 'em_uso',
        ]);
        $afetada = UsoVeiculo::create([
            'veiculo_conta_id' => $this->veiculo->id,
            'motorista_id' => $this->motorista->id,
            'inicio_previsto' => now()->subHour(),
            'fim_previsto' => now()->addHours(2),
            'status' => 'agendado',
        ]);

        $this->postJson("/api/conta/usos/{$emUso->id}/chegada", ['km_chegada' => 500], $this->headers)
            ->assertOk()
            ->assertJsonPath('status', 'concluido')
            ->assertJsonPath('alerta_conflito.0.uso_id', $afetada->id);
    }
}
