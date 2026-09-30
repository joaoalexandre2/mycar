<?php

namespace Tests\Feature;

use App\Models\Veiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class VeiculoTributosTest extends TestCase
{
    use RefreshDatabase;
    use AutenticaUsuario;

    public function test_calcula_ipva_estimado_e_licenciamento_pelo_estado(): void
    {
        [, $headers] = $this->autenticar();
        $veiculo = Veiculo::factory()->create([
            'uf' => 'PR',
            'fipe_valor' => 50000,
        ]);

        $this->getJson("/api/veiculos/{$veiculo->id}", $headers)
            ->assertStatus(200)
            ->assertJsonPath('ipva_estimado', 950)
            ->assertJsonPath('licenciamento_valor', 90.94);
    }

    public function test_sem_valor_fipe_nao_calcula_ipva_mas_mostra_licenciamento(): void
    {
        [, $headers] = $this->autenticar();
        $veiculo = Veiculo::factory()->create(['uf' => 'PR', 'fipe_valor' => null]);

        $this->getJson("/api/veiculos/{$veiculo->id}", $headers)
            ->assertJsonPath('ipva_estimado', null)
            ->assertJsonPath('licenciamento_valor', 90.94);
    }

    public function test_sem_estado_nada_e_calculado(): void
    {
        [, $headers] = $this->autenticar();
        $veiculo = Veiculo::factory()->create(['uf' => null, 'fipe_valor' => 50000]);

        $this->getJson("/api/veiculos/{$veiculo->id}", $headers)
            ->assertJsonPath('ipva_estimado', null)
            ->assertJsonPath('licenciamento_valor', null);
    }

    public function test_estado_sem_aliquota_confirmada_nao_inventa_valor(): void
    {
        [, $headers] = $this->autenticar();
        $veiculo = Veiculo::factory()->create(['uf' => 'GO', 'fipe_valor' => 50000]);

        $this->getJson("/api/veiculos/{$veiculo->id}", $headers)
            ->assertJsonPath('ipva_estimado', null)
            ->assertJsonPath('licenciamento_valor', 251.25);
    }

    public function test_rejeita_uf_invalida(): void
    {
        [, $headers] = $this->autenticar();
        $veiculo = Veiculo::factory()->create();

        $this->putJson("/api/veiculos/{$veiculo->id}", [
            'cliente_id' => $veiculo->cliente_id,
            'placa' => $veiculo->placa,
            'marca' => $veiculo->marca,
            'modelo' => $veiculo->modelo,
            'ano' => $veiculo->ano,
            'uf' => 'XX',
        ], $headers)->assertStatus(422)->assertJsonValidationErrors('uf');
    }
}
