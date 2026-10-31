<?php

namespace Tests\Feature;

use App\Models\CentroCusto;
use App\Models\MotivoTrocaCentro;
use App\Models\Motorista;
use App\Models\UsoVeiculo;
use App\Models\VeiculoConta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class MotivoTrocaCentroTest extends TestCase
{
    use AutenticaUsuario;
    use RefreshDatabase;

    /** @var array<string, string> */
    private array $headers;

    private int $usoId;

    private CentroCusto $entregas;

    protected function setUp(): void
    {
        parent::setUp();
        [, $this->headers] = $this->autenticarConta('frota');

        $vendas = CentroCusto::create(['nome' => 'Vendas']);
        $this->entregas = CentroCusto::create(['nome' => 'Entregas']);
        $veiculo = VeiculoConta::create(['placa' => 'AAA1B25', 'marca' => 'Fiat', 'modelo' => 'Strada', 'ano' => 2022]);
        $motorista = Motorista::create(['nome' => 'Maria', 'cnh_numero' => '123456', 'cnh_validade' => '2035-01-01']);

        $this->usoId = $this->postJson('/api/conta/usos', [
            'veiculo_conta_id' => $veiculo->id,
            'motorista_id' => $motorista->id,
            'centro_custo_id' => $vendas->id,
            'inicio_previsto' => '2026-11-10 08:00',
            'fim_previsto' => '2026-11-10 12:00',
        ], $this->headers)->assertStatus(201)->json('id');
    }

    public function test_cadastra_e_lista_motivos_ativos(): void
    {
        $this->postJson('/api/conta/motivos-troca-centro', ['nome' => 'Reclassificação mensal'], $this->headers)
            ->assertStatus(201);

        $desativado = MotivoTrocaCentro::create(['nome' => 'Antigo', 'ativo' => false]);

        $this->getJson('/api/conta/motivos-troca-centro', $this->headers)
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.nome', 'Reclassificação mensal');

        $this->getJson('/api/conta/motivos-troca-centro?todos=1', $this->headers)->assertJsonCount(2);
        $this->assertFalse($desativado->fresh()->ativo);
    }

    public function test_nao_repete_o_nome_do_motivo(): void
    {
        MotivoTrocaCentro::create(['nome' => 'Erro de lançamento']);

        $this->postJson('/api/conta/motivos-troca-centro', ['nome' => 'Erro de lançamento'], $this->headers)
            ->assertStatus(422);
    }

    public function test_troca_usa_o_motivo_escolhido_e_grava_o_nome(): void
    {
        $motivo = MotivoTrocaCentro::create(['nome' => 'Erro de lançamento']);

        $this->putJson("/api/conta/usos/{$this->usoId}/centro-custo", [
            'centro_custo_id' => $this->entregas->id, 'motivo_id' => $motivo->id,
        ], $this->headers)->assertOk();

        $this->getJson("/api/conta/usos/{$this->usoId}/centros-historico", $this->headers)
            ->assertJsonPath('0.motivo', 'Erro de lançamento');
    }

    public function test_nao_aceita_motivo_desativado(): void
    {
        $motivo = MotivoTrocaCentro::create(['nome' => 'Antigo', 'ativo' => false]);

        $this->putJson("/api/conta/usos/{$this->usoId}/centro-custo", [
            'centro_custo_id' => $this->entregas->id, 'motivo_id' => $motivo->id,
        ], $this->headers)->assertStatus(422);

        $this->assertNotSame($this->entregas->id, UsoVeiculo::find($this->usoId)->centro_custo_id);
    }
}
