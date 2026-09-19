<?php

namespace Tests\Feature;

use App\Models\Manutencao;
use App\Models\Veiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class ManutencaoTest extends TestCase
{
    use RefreshDatabase;
    use AutenticaUsuario;

    public function test_listar_manutencoes_exige_autenticacao(): void
    {
        $this->getJson('/api/manutencoes')->assertStatus(401);
    }

    public function test_criar_manutencao_com_dados_validos(): void
    {
        [, $headers] = $this->autenticar();
        $veiculo = Veiculo::factory()->create();

        $resposta = $this->postJson('/api/manutencoes', [
            'veiculo_id' => $veiculo->id,
            'tipo' => 'Troca de óleo',
            'valor' => 120,
            'data_manutencao' => now()->format('Y-m-d'),
            'quilometragem' => 50000,
        ], $headers);

        $resposta->assertStatus(201)->assertJsonPath('tipo', 'Troca de óleo');
    }

    public function test_criar_sem_veiculo_valido_falha(): void
    {
        [, $headers] = $this->autenticar();

        $resposta = $this->postJson('/api/manutencoes', [
            'veiculo_id' => 99999,
            'tipo' => 'Revisão',
            'data_manutencao' => now()->format('Y-m-d'),
        ], $headers);

        $resposta->assertStatus(422)->assertJsonValidationErrors('veiculo_id');
    }

    /**
     * Replica em teste a mesma regra de statusPorData() do frontend:
     * atrasada = proxima_data no passado
     * proxima  = proxima_data dentro dos próximos 30 dias
     * em_dia   = sem proxima_data ou além dos 30 dias
     */
    public function test_resumo_classifica_situacao_por_data_corretamente(): void
    {
        [, $headers] = $this->autenticar();

        Manutencao::factory()->create(['proxima_data' => now()->subDays(5)->format('Y-m-d')]); // atrasada
        Manutencao::factory()->create(['proxima_data' => now()->addDays(10)->format('Y-m-d')]); // proxima
        Manutencao::factory()->create(['proxima_data' => now()->addDays(29)->format('Y-m-d')]); // proxima (limite)
        Manutencao::factory()->create(['proxima_data' => now()->addDays(60)->format('Y-m-d')]); // em_dia
        Manutencao::factory()->create(['proxima_data' => null]); // em_dia

        $resposta = $this->getJson('/api/manutencoes', $headers);

        $resposta->assertStatus(200)
            ->assertJsonStructure([
                'resumo' => ['total', 'emDia', 'proximas', 'atrasadas', 'veiculosMonitorados'],
            ])
            ->assertJsonPath('resumo.total', 5)
            ->assertJsonPath('resumo.atrasadas', 1)
            ->assertJsonPath('resumo.proximas', 2)
            ->assertJsonPath('resumo.emDia', 2);
    }

    public function test_filtra_por_situacao_atrasada(): void
    {
        [, $headers] = $this->autenticar();

        $atrasada = Manutencao::factory()->create(['proxima_data' => now()->subDays(3)->format('Y-m-d')]);
        Manutencao::factory()->create(['proxima_data' => now()->addDays(15)->format('Y-m-d')]);
        Manutencao::factory()->create(['proxima_data' => null]);

        $resposta = $this->getJson('/api/manutencoes?status=atrasada', $headers);

        $resposta->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $atrasada->id);
    }

    public function test_filtra_por_situacao_proxima(): void
    {
        [, $headers] = $this->autenticar();

        Manutencao::factory()->create(['proxima_data' => now()->subDays(3)->format('Y-m-d')]);
        $proxima = Manutencao::factory()->create(['proxima_data' => now()->addDays(15)->format('Y-m-d')]);
        Manutencao::factory()->create(['proxima_data' => null]);

        $resposta = $this->getJson('/api/manutencoes?status=proxima', $headers);

        $resposta->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $proxima->id);
    }

    public function test_filtra_por_situacao_em_dia_inclui_sem_proxima_data(): void
    {
        [, $headers] = $this->autenticar();

        Manutencao::factory()->create(['proxima_data' => now()->subDays(3)->format('Y-m-d')]);
        Manutencao::factory()->create(['proxima_data' => now()->addDays(15)->format('Y-m-d')]);
        Manutencao::factory()->create(['proxima_data' => null]);
        Manutencao::factory()->create(['proxima_data' => now()->addDays(90)->format('Y-m-d')]);

        $resposta = $this->getJson('/api/manutencoes?status=em_dia', $headers);

        $resposta->assertStatus(200)->assertJsonCount(2, 'data');
    }

    public function test_busca_por_tipo(): void
    {
        [, $headers] = $this->autenticar();

        Manutencao::factory()->create(['tipo' => 'Alinhamento e balanceamento']);
        Manutencao::factory()->create(['tipo' => 'Troca de correia']);

        $resposta = $this->getJson('/api/manutencoes?busca=Alinhamento', $headers);

        $resposta->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_all_retorna_todos_sem_paginar(): void
    {
        [, $headers] = $this->autenticar();

        Manutencao::factory()->count(3)->create();

        $resposta = $this->getJson('/api/manutencoes?all=1', $headers);

        $resposta->assertStatus(200)->assertJsonCount(3);
    }

    public function test_atualizar_manutencao(): void
    {
        [, $headers] = $this->autenticar();

        $manutencao = Manutencao::factory()->create(['tipo' => 'Tipo Antigo']);

        $resposta = $this->putJson("/api/manutencoes/{$manutencao->id}", [
            'veiculo_id' => $manutencao->veiculo_id,
            'tipo' => 'Tipo Novo',
            'data_manutencao' => $manutencao->data_manutencao->format('Y-m-d'),
        ], $headers);

        $resposta->assertStatus(200)->assertJsonPath('tipo', 'Tipo Novo');
    }

    public function test_remover_manutencao(): void
    {
        [, $headers] = $this->autenticar();

        $manutencao = Manutencao::factory()->create();

        $this->deleteJson("/api/manutencoes/{$manutencao->id}", [], $headers)
            ->assertStatus(200);

        $this->assertDatabaseMissing('manutencoes', ['id' => $manutencao->id]);
    }
}
