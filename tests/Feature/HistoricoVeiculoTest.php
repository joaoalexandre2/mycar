<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Manutencao;
use App\Models\OrdemServico;
use App\Models\Oficina;
use App\Models\Veiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class HistoricoVeiculoTest extends TestCase
{
    use RefreshDatabase;
    use AutenticaUsuario;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_historico_exige_autenticacao(): void
    {
        $this->getJson('/api/veiculos/1/historico')->assertStatus(401);
    }

    public function test_historico_de_veiculo_inexistente(): void
    {
        [, $headers] = $this->autenticar();

        $this->getJson('/api/veiculos/999/historico', $headers)->assertStatus(404);
    }

    public function test_historico_junta_ordens_manutencoes_e_fipe(): void
    {
        [, $headers] = $this->autenticar();
        $veiculo = Veiculo::factory()->create();
        $ordem = OrdemServico::factory()->create(['veiculo_id' => $veiculo->id]);
        $manutencao = Manutencao::factory()->create(['veiculo_id' => $veiculo->id]);

        $this->getJson("/api/veiculos/{$veiculo->id}/historico", $headers)
            ->assertStatus(200)
            ->assertJsonPath('veiculo.id', $veiculo->id)
            ->assertJsonPath('veiculo.cliente.id', $veiculo->cliente_id)
            ->assertJsonPath('ordens_servico.0.id', $ordem->id)
            ->assertJsonPath('manutencoes.0.id', $manutencao->id)
            ->assertJsonPath('fipe_historico', []);
    }

    public function test_consultar_fipe_registra_linha_no_historico(): void
    {
        [, $headers] = $this->autenticar();
        Http::fake(['*/carros/marcas/23/modelos/1/anos/2020-1' => Http::response(['Valor' => 'R$ 60.250,50'])]);
        $veiculo = Veiculo::factory()->create([
            'fipe_marca_id' => 23,
            'fipe_modelo_id' => 1,
            'fipe_ano' => '2020-1',
        ]);

        $this->postJson("/api/veiculos/{$veiculo->id}/fipe", [], $headers)->assertStatus(200);

        $this->assertDatabaseHas('fipe_historicos', [
            'veiculo_id' => $veiculo->id,
            'valor' => 60250.50,
        ]);

        $resposta = $this->getJson("/api/veiculos/{$veiculo->id}/historico", $headers);

        $resposta->assertStatus(200)
            ->assertJsonCount(1, 'fipe_historico')
            ->assertJsonPath('fipe_historico.0.valor', '60250.50');
    }

    public function test_consultar_fipe_varias_vezes_acumula_historico(): void
    {
        [, $headers] = $this->autenticar();
        $veiculo = Veiculo::factory()->create([
            'fipe_marca_id' => 23,
            'fipe_modelo_id' => 1,
            'fipe_ano' => '2020-1',
        ]);

        Http::fake(['*/carros/marcas/23/modelos/1/anos/2020-1' => Http::response(['Valor' => 'R$ 60.000,00'])]);
        $this->postJson("/api/veiculos/{$veiculo->id}/fipe", [], $headers)->assertStatus(200);

        Http::fake(['*/carros/marcas/23/modelos/1/anos/2020-1' => Http::response(['Valor' => 'R$ 61.000,00'])]);
        Cache::flush();
        $this->postJson("/api/veiculos/{$veiculo->id}/fipe", [], $headers)->assertStatus(200);

        $this->getJson("/api/veiculos/{$veiculo->id}/historico", $headers)
            ->assertStatus(200)
            ->assertJsonCount(2, 'fipe_historico');
    }

    public function test_fipe_fora_do_ar_nao_cria_linha_no_historico(): void
    {
        [, $headers] = $this->autenticar();
        Http::fake(['*' => Http::response('erro', 500)]);
        $veiculo = Veiculo::factory()->create([
            'fipe_marca_id' => 23,
            'fipe_modelo_id' => 1,
            'fipe_ano' => '2020-1',
        ]);

        $this->postJson("/api/veiculos/{$veiculo->id}/fipe", [], $headers)->assertStatus(502);

        $this->assertDatabaseCount('fipe_historicos', 0);
    }

    public function test_historico_nao_vaza_entre_oficinas(): void
    {
        [, $headers] = $this->autenticar();
        $veiculo = Veiculo::factory()->create();

        $outraOficina = Oficina::factory()->create();
        app()->instance('oficina.atual', $outraOficina->id);
        $clienteOutro = Cliente::factory()->create();
        $veiculoOutro = Veiculo::factory()->create(['cliente_id' => $clienteOutro->id]);
        OrdemServico::factory()->create(['veiculo_id' => $veiculoOutro->id]);

        // Volta para a oficina autenticada: o veículo da outra oficina não
        // deve nem ser encontrado (404), já que o escopo global filtra por
        // oficina.atual.
        $this->getJson("/api/veiculos/{$veiculoOutro->id}/historico", $headers)->assertStatus(404);
    }
}
