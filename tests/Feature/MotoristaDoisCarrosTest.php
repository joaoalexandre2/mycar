<?php

namespace Tests\Feature;

use App\Models\CentroCusto;
use App\Models\Motorista;
use App\Models\VeiculoConta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class MotoristaDoisCarrosTest extends TestCase
{
    use AutenticaUsuario;
    use RefreshDatabase;

    /** @var array<string, string> */
    private array $headers;

    private Motorista $motorista;

    private VeiculoConta $carroA;

    private VeiculoConta $carroB;

    protected function setUp(): void
    {
        parent::setUp();
        [, $this->headers] = $this->autenticarConta('frota');
        CentroCusto::create(['nome' => 'Vendas']);

        $this->carroA = VeiculoConta::create(['placa' => 'AAA1B25', 'marca' => 'Fiat', 'modelo' => 'Strada', 'ano' => 2022]);
        $this->carroB = VeiculoConta::create(['placa' => 'BBB2C36', 'marca' => 'VW', 'modelo' => 'Saveiro', 'ano' => 2022]);
        $this->motorista = Motorista::create(['nome' => 'Maria', 'cnh_numero' => '123456', 'cnh_validade' => '2035-01-01']);
    }

    private function reservar(VeiculoConta $carro, string $inicio, string $fim): array
    {
        return [
            'veiculo_conta_id' => $carro->id,
            'motorista_id' => $this->motorista->id,
            'inicio_previsto' => $inicio,
            'fim_previsto' => $fim,
        ];
    }

    public function test_nao_reserva_dois_carros_no_mesmo_horario(): void
    {
        $this->postJson('/api/conta/usos', $this->reservar($this->carroA, '2026-11-10 08:00', '2026-11-10 12:00'), $this->headers)
            ->assertStatus(201);

        $this->postJson('/api/conta/usos', $this->reservar($this->carroB, '2026-11-10 09:00', '2026-11-10 11:00'), $this->headers)
            ->assertStatus(422);
    }

    public function test_nao_sai_com_outro_carro_enquanto_a_viagem_anterior_esta_aberta(): void
    {
        $a = $this->postJson('/api/conta/usos', $this->reservar($this->carroA, '2026-11-10 08:00', '2026-11-10 10:00'), $this->headers)
            ->assertStatus(201)->json('id');
        $b = $this->postJson('/api/conta/usos', $this->reservar($this->carroB, '2026-11-10 10:30', '2026-11-10 12:00'), $this->headers)
            ->assertStatus(201)->json('id');

        $this->postJson("/api/conta/usos/{$a}/saida", ['km_saida' => 100], $this->headers)->assertOk();

        // A chegada do carro A não foi registrada: a Maria não pode sair com o carro B.
        $this->postJson("/api/conta/usos/{$b}/saida", ['km_saida' => 500], $this->headers)->assertStatus(422);
    }
}
