<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Veiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class VeiculoTest extends TestCase
{
    use RefreshDatabase;
    use AutenticaUsuario;

    public function test_listar_veiculos_exige_autenticacao(): void
    {
        $this->getJson('/api/veiculos')->assertStatus(401);
    }

    public function test_criar_veiculo_com_dados_validos(): void
    {
        [, $headers] = $this->autenticar();
        $cliente = Cliente::factory()->create();

        $resposta = $this->postJson('/api/veiculos', [
            'cliente_id' => $cliente->id,
            'placa' => 'ABC1D23',
            'marca' => 'Fiat',
            'modelo' => 'Uno',
            'ano' => 2020,
        ], $headers);

        $resposta->assertStatus(201)->assertJsonPath('placa', 'ABC1D23');
    }

    public function test_nao_permite_placa_duplicada(): void
    {
        [, $headers] = $this->autenticar();
        $veiculo = Veiculo::factory()->create(['placa' => 'XYZ9K88']);

        $resposta = $this->postJson('/api/veiculos', [
            'cliente_id' => $veiculo->cliente_id,
            'placa' => 'XYZ9K88',
            'marca' => 'Fiat',
            'modelo' => 'Uno',
            'ano' => 2020,
        ], $headers);

        $resposta->assertStatus(422)->assertJsonValidationErrors('placa');
    }

    public function test_rejeita_ano_fora_do_intervalo(): void
    {
        [, $headers] = $this->autenticar();
        $cliente = Cliente::factory()->create();

        $resposta = $this->postJson('/api/veiculos', [
            'cliente_id' => $cliente->id,
            'placa' => 'AAA1111',
            'marca' => 'Fiat',
            'modelo' => 'Uno',
            'ano' => 1850,
        ], $headers);

        $resposta->assertStatus(422)->assertJsonValidationErrors('ano');
    }

    public function test_rejeita_cliente_inexistente(): void
    {
        [, $headers] = $this->autenticar();

        $resposta = $this->postJson('/api/veiculos', [
            'cliente_id' => 99999,
            'placa' => 'BBB2222',
            'marca' => 'Fiat',
            'modelo' => 'Uno',
            'ano' => 2020,
        ], $headers);

        $resposta->assertStatus(422)->assertJsonValidationErrors('cliente_id');
    }

    public function test_listagem_paginada_com_busca_por_placa(): void
    {
        [, $headers] = $this->autenticar();

        Veiculo::factory()->create(['placa' => 'FIP0001']);
        Veiculo::factory()->create(['placa' => 'ZZZ9999']);

        $resposta = $this->getJson('/api/veiculos?busca=FIP', $headers);

        $resposta->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.placa', 'FIP0001')
            ->assertJsonPath('resumo.total', 2);
    }

    public function test_busca_por_nome_do_cliente(): void
    {
        [, $headers] = $this->autenticar();

        $cliente = Cliente::factory()->create(['nome' => 'Carlos Pereira']);
        Veiculo::factory()->create(['cliente_id' => $cliente->id]);
        Veiculo::factory()->create();

        $resposta = $this->getJson('/api/veiculos?busca=Carlos', $headers);

        $resposta->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_all_retorna_todos_sem_paginar(): void
    {
        [, $headers] = $this->autenticar();

        Veiculo::factory()->count(4)->create();

        $resposta = $this->getJson('/api/veiculos?all=1', $headers);

        $resposta->assertStatus(200)->assertJsonCount(4);
    }

    public function test_atualizar_veiculo(): void
    {
        [, $headers] = $this->autenticar();

        $veiculo = Veiculo::factory()->create(['modelo' => 'Modelo Antigo']);

        $resposta = $this->putJson("/api/veiculos/{$veiculo->id}", [
            'cliente_id' => $veiculo->cliente_id,
            'placa' => $veiculo->placa,
            'marca' => $veiculo->marca,
            'modelo' => 'Modelo Novo',
            'ano' => $veiculo->ano,
        ], $headers);

        $resposta->assertStatus(200)->assertJsonPath('modelo', 'Modelo Novo');
    }

    public function test_remover_veiculo(): void
    {
        [, $headers] = $this->autenticar();

        $veiculo = Veiculo::factory()->create();

        $this->deleteJson("/api/veiculos/{$veiculo->id}", [], $headers)
            ->assertStatus(200);

        $this->assertDatabaseMissing('veiculos', ['id' => $veiculo->id]);
    }
}
