<?php

namespace Tests\Feature;

use App\Models\Cliente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class ClienteTest extends TestCase
{
    use RefreshDatabase;
    use AutenticaUsuario;

    public function test_listar_clientes_exige_autenticacao(): void
    {
        $this->getJson('/api/clientes')->assertStatus(401);
    }

    public function test_criar_cliente_com_dados_validos(): void
    {
        [, $headers] = $this->autenticar();

        $resposta = $this->postJson('/api/clientes', [
            'nome' => 'Maria Silva',
            'cpf' => '12345678901',
            'telefone' => '11999998888',
            'ativo' => true,
        ], $headers);

        $resposta->assertStatus(201)
            ->assertJsonPath('nome', 'Maria Silva');

        $this->assertDatabaseHas('clientes', ['cpf' => '12345678901']);
    }

    public function test_nao_permite_cpf_duplicado(): void
    {
        [, $headers] = $this->autenticar();

        Cliente::factory()->create(['cpf' => '12345678901']);

        $resposta = $this->postJson('/api/clientes', [
            'nome' => 'Outro Cliente',
            'cpf' => '12345678901',
            'telefone' => '11988887777',
        ], $headers);

        $resposta->assertStatus(422)
            ->assertJsonValidationErrors('cpf');
    }

    public function test_rejeita_cpf_com_tamanho_invalido(): void
    {
        [, $headers] = $this->autenticar();

        $resposta = $this->postJson('/api/clientes', [
            'nome' => 'Cliente Teste',
            'cpf' => '123',
            'telefone' => '11988887777',
        ], $headers);

        $resposta->assertStatus(422)
            ->assertJsonValidationErrors('cpf');
    }

    public function test_listagem_paginada_traz_meta_e_resumo(): void
    {
        [, $headers] = $this->autenticar();

        Cliente::factory()->count(20)->create();

        $resposta = $this->getJson('/api/clientes?per_page=5', $headers);

        $resposta->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                'resumo' => ['total', 'ativos', 'inativos', 'totalVeiculos'],
            ])
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.total', 20)
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('resumo.total', 20);
    }

    public function test_all_retorna_lista_simples_sem_paginacao(): void
    {
        [, $headers] = $this->autenticar();

        Cliente::factory()->count(3)->create();

        $resposta = $this->getJson('/api/clientes?all=1', $headers);

        $resposta->assertStatus(200)->assertJsonCount(3);
    }

    public function test_filtro_de_busca_por_nome(): void
    {
        [, $headers] = $this->autenticar();

        Cliente::factory()->create(['nome' => 'Ana Souza']);
        Cliente::factory()->create(['nome' => 'Bruno Lima']);

        $resposta = $this->getJson('/api/clientes?busca=Ana', $headers);

        $resposta->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nome', 'Ana Souza');
    }

    public function test_filtro_por_status(): void
    {
        [, $headers] = $this->autenticar();

        Cliente::factory()->create(['ativo' => true]);
        Cliente::factory()->create(['ativo' => false]);

        $resposta = $this->getJson('/api/clientes?status=inativos', $headers);

        $resposta->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('resumo.total', 2);
    }

    public function test_atualizar_cliente(): void
    {
        [, $headers] = $this->autenticar();

        $cliente = Cliente::factory()->create(['nome' => 'Nome Antigo']);

        $resposta = $this->putJson("/api/clientes/{$cliente->id}", [
            'nome' => 'Nome Novo',
            'cpf' => $cliente->cpf,
            'telefone' => $cliente->telefone,
            'ativo' => true,
        ], $headers);

        $resposta->assertStatus(200)->assertJsonPath('nome', 'Nome Novo');
    }

    public function test_remover_cliente(): void
    {
        [, $headers] = $this->autenticar();

        $cliente = Cliente::factory()->create();

        $this->deleteJson("/api/clientes/{$cliente->id}", [], $headers)
            ->assertStatus(200);

        $this->assertDatabaseMissing('clientes', ['id' => $cliente->id]);
    }

    public function test_buscar_cliente_inexistente_retorna_404(): void
    {
        [, $headers] = $this->autenticar();

        $this->getJson('/api/clientes/99999', $headers)->assertStatus(404);
    }
}
