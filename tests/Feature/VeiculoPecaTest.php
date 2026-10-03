<?php

namespace Tests\Feature;

use App\Models\Oficina;
use App\Models\Veiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class VeiculoPecaTest extends TestCase
{
    use RefreshDatabase;
    use AutenticaUsuario;

    private function payload(array $extra = []): array
    {
        return array_merge([
            'tipo' => 'pastilha_freio',
            'especificacao' => 'Código XYZ-123',
            'marca' => 'Marca Teste',
            'fonte' => 'servico',
            'usado_em' => now()->subDays(3)->toDateString(),
        ], $extra);
    }

    public function test_exige_autenticacao(): void
    {
        $this->getJson('/api/veiculos/1/pecas')->assertStatus(401);
        $this->postJson('/api/veiculos/1/pecas', [])->assertStatus(401);
        $this->deleteJson('/api/veiculos/1/pecas/1')->assertStatus(401);
    }

    public function test_veiculo_inexistente_retorna_404(): void
    {
        [, $headers] = $this->autenticar();

        $this->getJson('/api/veiculos/999/pecas', $headers)->assertStatus(404);
        $this->postJson('/api/veiculos/999/pecas', $this->payload(), $headers)->assertStatus(404);
    }

    public function test_registra_e_lista_pecas_mais_recentes_primeiro(): void
    {
        [, $headers] = $this->autenticar();
        $veiculo = Veiculo::factory()->create();

        $this->postJson("/api/veiculos/{$veiculo->id}/pecas", $this->payload([
            'especificacao' => 'antiga',
            'usado_em' => now()->subYear()->toDateString(),
        ]), $headers)->assertStatus(201);
        $this->postJson("/api/veiculos/{$veiculo->id}/pecas", $this->payload(['especificacao' => 'recente']), $headers)
            ->assertStatus(201);

        $this->getJson("/api/veiculos/{$veiculo->id}/pecas", $headers)
            ->assertStatus(200)
            ->assertJsonCount(2, 'pecas')
            ->assertJsonPath('pecas.0.especificacao', 'recente')
            ->assertJsonPath('tipos.pastilha_freio', 'Pastilha de freio');
    }

    public function test_aceita_peca_de_ficha_sem_data(): void
    {
        [, $headers] = $this->autenticar();
        $veiculo = Veiculo::factory()->create();

        $this->postJson("/api/veiculos/{$veiculo->id}/pecas", $this->payload([
            'fonte' => 'ficha',
            'usado_em' => null,
            'marca' => null,
        ]), $headers)->assertStatus(201)->assertJsonPath('fonte', 'ficha');
    }

    public function test_valida_tipo_fonte_e_data_futura(): void
    {
        [, $headers] = $this->autenticar();
        $veiculo = Veiculo::factory()->create();

        $this->postJson("/api/veiculos/{$veiculo->id}/pecas", $this->payload([
            'tipo' => 'turbina',
            'fonte' => 'inventada',
            'usado_em' => now()->addDay()->toDateString(),
            'especificacao' => '',
        ]), $headers)->assertStatus(422)
            ->assertJsonValidationErrors(['tipo', 'fonte', 'usado_em', 'especificacao']);
    }

    public function test_remove_peca(): void
    {
        [, $headers] = $this->autenticar();
        $veiculo = Veiculo::factory()->create();
        $id = $this->postJson("/api/veiculos/{$veiculo->id}/pecas", $this->payload(), $headers)->json('id');

        $this->deleteJson("/api/veiculos/{$veiculo->id}/pecas/{$id}", [], $headers)->assertStatus(200);
        $this->deleteJson("/api/veiculos/{$veiculo->id}/pecas/{$id}", [], $headers)->assertStatus(404);
    }

    public function test_nao_acessa_pecas_de_outra_oficina(): void
    {
        [, $headers] = $this->autenticar();

        app()->instance('oficina.atual', Oficina::factory()->create()->id);
        $veiculoOutro = Veiculo::factory()->create();
        $pecaId = $veiculoOutro->pecas()->create($this->payload())->id;

        $this->getJson("/api/veiculos/{$veiculoOutro->id}/pecas", $headers)->assertStatus(404);
        $this->postJson("/api/veiculos/{$veiculoOutro->id}/pecas", $this->payload(), $headers)->assertStatus(404);
        $this->deleteJson("/api/veiculos/{$veiculoOutro->id}/pecas/{$pecaId}", [], $headers)->assertStatus(404);
    }
}
