<?php

namespace Tests\Feature;

use App\Models\Conta;
use App\Models\Motorista;
use App\Models\UsoVeiculo;
use App\Models\VeiculoConta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

/**
 * Conflitos de reserva consideram a utilização real: um carro que ainda não voltou continua ocupado.
 * Reservas fora do horário comercial (noite, madrugada, fim de semana) são permitidas.
 */
class ReservasUtilizacaoRealTest extends TestCase
{
    use AutenticaUsuario;
    use RefreshDatabase;

    /** @var array<string, string> */
    private array $headers;

    private Conta $conta;

    private VeiculoConta $veiculo;

    private Motorista $motorista;

    protected function setUp(): void
    {
        parent::setUp();

        [, $this->headers] = $this->autenticarConta('frota');
        $this->conta = Conta::find(app('conta.atual'));
        app()->instance('conta.atual', $this->conta->id);

        $this->veiculo = VeiculoConta::create(['placa' => 'AAA1A11', 'marca' => 'Fiat', 'modelo' => 'Strada', 'ano' => 2022]);
        $this->motorista = Motorista::create(['nome' => 'Maria', 'cnh_numero' => '123', 'cnh_validade' => now()->addYears(2)->toDateString()]);
    }

    private function reservar(string $inicio, string $fim): TestResponse
    {
        return $this->postJson('/api/conta/usos', [
            'veiculo_conta_id' => $this->veiculo->id,
            'motorista_id' => $this->motorista->id,
            'inicio_previsto' => $inicio,
            'fim_previsto' => $fim,
            'finalidade' => 'Teste',
        ], $this->headers);
    }

    private function emUso(array $extra): UsoVeiculo
    {
        return UsoVeiculo::create(array_merge([
            'veiculo_conta_id' => $this->veiculo->id,
            'motorista_id' => $this->motorista->id,
            'status' => 'em_uso',
        ], $extra));
    }

    public function test_carro_que_ainda_nao_voltou_nao_aceita_reserva_sobreposta(): void
    {
        // Saiu há 3 horas e deve voltar só daqui a 2: a reserva das próximas horas não pode entrar.
        $this->emUso([
            'inicio_previsto' => now()->subHours(3),
            'fim_previsto' => now()->addHours(2),
            'saida_em' => now()->subHours(3),
        ]);

        $this->reservar(now()->addHour()->toDateTimeString(), now()->addHours(3)->toDateTimeString())
            ->assertStatus(422)
            ->assertJsonPath('conflito.status', 'em_uso');
    }

    public function test_uso_que_passou_do_horario_previsto_segue_ocupando_ate_agora(): void
    {
        // A devolução estava prevista para uma hora atrás e o carro ainda não voltou: ocupado até agora.
        $this->emUso([
            'inicio_previsto' => now()->subHours(4),
            'fim_previsto' => now()->subHour(),
            'saida_em' => now()->subHours(4),
        ]);

        $this->reservar(now()->subMinutes(30)->toDateTimeString(), now()->addHour()->toDateTimeString())
            ->assertStatus(422);
    }

    public function test_uso_devolvido_libera_o_carro_para_nova_reserva(): void
    {
        $this->emUso([
            'inicio_previsto' => now()->subHours(4),
            'fim_previsto' => now()->addHour(),
            'saida_em' => now()->subHours(4),
            'chegada_em' => now()->subHour(),
        ]);

        $this->reservar(now()->subMinutes(30)->toDateTimeString(), now()->addHours(2)->toDateTimeString())
            ->assertStatus(201);
    }

    public function test_reserva_noturna_e_de_fim_de_semana_e_permitida(): void
    {
        $amanha = now()->addDay()->startOfDay();

        $this->reservar($amanha->copy()->setHour(22)->toDateTimeString(), $amanha->copy()->addDay()->setHour(6)->toDateTimeString())
            ->assertStatus(201);

        $sabado = now()->next('Saturday')->setTime(7, 0);
        $this->reservar($sabado->toDateTimeString(), $sabado->copy()->addDays(2)->toDateTimeString())
            ->assertStatus(201);
    }

    public function test_motorista_com_uso_real_em_andamento_nao_entra_em_outra_reserva(): void
    {
        $outroVeiculo = VeiculoConta::create(['placa' => 'BBB2B22', 'marca' => 'VW', 'modelo' => 'Gol', 'ano' => 2020]);
        UsoVeiculo::create([
            'veiculo_conta_id' => $this->veiculo->id,
            'motorista_id' => $this->motorista->id,
            'inicio_previsto' => now()->subHours(3),
            'fim_previsto' => now()->addHours(2),
            'saida_em' => now()->subHours(3),
            'status' => 'em_uso',
        ]);

        $this->postJson('/api/conta/usos', [
            'veiculo_conta_id' => $outroVeiculo->id,
            'motorista_id' => $this->motorista->id,
            'inicio_previsto' => now()->addHour()->toDateTimeString(),
            'fim_previsto' => now()->addHours(3)->toDateTimeString(),
        ], $this->headers)->assertStatus(422);
    }
}
