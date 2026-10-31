<?php

namespace Tests\Feature;

use App\Models\CentroCusto;
use App\Models\Conta;
use App\Models\Motorista;
use App\Models\UsoVeiculo;
use App\Models\VeiculoConta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class MotoristaCentroCustoTest extends TestCase
{
    use AutenticaUsuario;
    use RefreshDatabase;

    /** @var array<string, string> */
    private array $headersAdmin;

    private Conta $conta;

    private CentroCusto $vendas;

    private CentroCusto $entregas;

    private VeiculoConta $veiculo;

    protected function setUp(): void
    {
        parent::setUp();
        [, $this->headersAdmin] = $this->autenticarConta('frota');
        $this->conta = Conta::find(app('conta.atual'));

        $this->vendas = CentroCusto::create(['nome' => 'Vendas']);
        $this->entregas = CentroCusto::create(['nome' => 'Entregas']);
        $this->veiculo = VeiculoConta::create(['placa' => 'AAA1B25', 'marca' => 'Fiat', 'modelo' => 'Strada', 'ano' => 2022]);
    }

    /** Motorista com login, já ligado ao centro informado. */
    private function motoristaLogado(?CentroCusto $centro): array
    {
        [$usuario, $headers] = $this->autenticarConta('frota', $this->conta);
        $usuario->forceFill(['papel_frota' => 'motorista'])->save();
        app()->instance('conta.atual', $this->conta->id);

        $motorista = Motorista::create([
            'nome' => 'Maria', 'cnh_numero' => '123456', 'cnh_validade' => '2035-01-01',
            'centro_custo_id' => $centro?->id,
        ]);
        $motorista->forceFill(['user_id' => $usuario->id])->save();

        return [$motorista, $headers];
    }

    private function reserva(int $motoristaId, ?int $centroId = null): array
    {
        return [
            'veiculo_conta_id' => $this->veiculo->id,
            'motorista_id' => $motoristaId,
            'centro_custo_id' => $centroId,
            'inicio_previsto' => '2026-11-10 08:00',
            'fim_previsto' => '2026-11-10 12:00',
        ];
    }

    public function test_administrador_amarra_o_motorista_a_um_centro(): void
    {
        $this->postJson('/api/conta/motoristas', [
            'nome' => 'João', 'cnh_numero' => '999', 'cnh_validade' => '2035-01-01',
            'centro_custo_id' => $this->entregas->id,
        ], $this->headersAdmin)
            ->assertStatus(201)
            ->assertJsonPath('centro_custo_id', $this->entregas->id);
    }

    public function test_nao_amarra_a_um_centro_desativado_ou_de_outra_frota(): void
    {
        $this->entregas->update(['ativo' => false]);

        $this->postJson('/api/conta/motoristas', [
            'nome' => 'João', 'cnh_validade' => '2035-01-01', 'centro_custo_id' => $this->entregas->id,
        ], $this->headersAdmin)->assertStatus(422);
    }

    public function test_motorista_reserva_sempre_no_centro_a_que_esta_amarrado(): void
    {
        [$motorista, $headers] = $this->motoristaLogado($this->entregas);

        // Mesmo que ele tente outro centro, sai no do cadastro.
        $this->postJson('/api/conta/usos', $this->reserva($motorista->id, $this->vendas->id), $headers)
            ->assertStatus(201);

        $this->assertSame($this->entregas->id, UsoVeiculo::first()->centro_custo_id);
    }

    public function test_motorista_sem_centro_nao_reserva(): void
    {
        [$motorista, $headers] = $this->motoristaLogado(null);

        $this->postJson('/api/conta/usos', $this->reserva($motorista->id), $headers)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Você ainda não está amarrado a um centro de custo. Peça ao gestor.');

        $this->assertSame(0, UsoVeiculo::count());
    }

    public function test_administrador_continua_escolhendo_o_centro_da_reserva(): void
    {
        $motorista = Motorista::create([
            'nome' => 'Maria', 'cnh_validade' => '2035-01-01', 'centro_custo_id' => $this->vendas->id,
        ]);

        $this->postJson('/api/conta/usos', $this->reserva($motorista->id, $this->entregas->id), $this->headersAdmin)
            ->assertStatus(201);

        $this->assertSame($this->entregas->id, UsoVeiculo::first()->centro_custo_id);
    }

    public function test_me_informa_o_centro_do_motorista(): void
    {
        [, $headers] = $this->motoristaLogado($this->entregas);

        $this->getJson('/api/me', $headers)
            ->assertOk()
            ->assertJsonPath('centro_do_motorista.id', $this->entregas->id)
            ->assertJsonPath('centro_do_motorista.nome', 'Entregas');
    }

    public function test_me_nao_informa_centro_para_quem_nao_e_motorista(): void
    {
        $this->getJson('/api/me', $this->headersAdmin)
            ->assertOk()
            ->assertJsonPath('centro_do_motorista', null);
    }

    public function test_motorista_nao_ve_quem_esta_com_o_veiculo_no_conflito(): void
    {
        $outro = Motorista::create(['nome' => 'Ana Secreta', 'cnh_numero' => '777', 'cnh_validade' => '2035-01-01']);
        $this->postJson('/api/conta/usos', $this->reserva($outro->id, $this->entregas->id), $this->headersAdmin)
            ->assertStatus(201);

        [, $headers] = $this->motoristaLogado($this->entregas);

        $resposta = $this->postJson('/api/conta/usos', $this->reserva(0, $this->entregas->id), $headers)
            ->assertStatus(422);

        $this->assertStringNotContainsString('Ana Secreta', $resposta->json('message'));
        $this->assertArrayNotHasKey('conflito', $resposta->json());
    }
}
