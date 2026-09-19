<?php

namespace Tests\Feature;

use App\Models\OrdemServico;
use App\Models\Veiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class OrdemServicoTest extends TestCase
{
    use RefreshDatabase;
    use AutenticaUsuario;

    public function test_listar_ordens_exige_autenticacao(): void
    {
        $this->getJson('/api/ordens-servico')->assertStatus(401);
    }

    public function test_criar_ordem_servico_sempre_inicia_aberta(): void
    {
        [, $headers] = $this->autenticar();
        $veiculo = Veiculo::factory()->create();

        $resposta = $this->postJson('/api/ordens-servico', [
            'veiculo_id' => $veiculo->id,
            'descricao' => 'Troca de óleo e filtro',
            'valor' => 150.5,
            'data_abertura' => now()->format('Y-m-d'),
            // mesmo enviando um status, ele deve ser ignorado pelo service
            'status' => 'finalizada',
        ], $headers);

        $resposta->assertStatus(201)
            ->assertJsonPath('status', 'aberta')
            ->assertJsonPath('data_fechamento', null);
    }

    public function test_criar_sem_veiculo_valido_falha(): void
    {
        [, $headers] = $this->autenticar();

        $resposta = $this->postJson('/api/ordens-servico', [
            'veiculo_id' => 99999,
            'descricao' => 'Serviço qualquer',
            'data_abertura' => now()->format('Y-m-d'),
        ], $headers);

        $resposta->assertStatus(422)->assertJsonValidationErrors('veiculo_id');
    }

    public function test_listagem_paginada_traz_resumo_completo(): void
    {
        [, $headers] = $this->autenticar();

        OrdemServico::factory()->create(['status' => 'aberta', 'valor' => 100]);
        OrdemServico::factory()->create(['status' => 'em_andamento', 'valor' => 200]);
        OrdemServico::factory()->create(['status' => 'aguardando_peca', 'valor' => 300]);
        OrdemServico::factory()->create(['status' => 'finalizada', 'valor' => 400, 'data_fechamento' => now()]);

        $resposta = $this->getJson('/api/ordens-servico', $headers);

        $resposta->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                'resumo' => ['total', 'abertas', 'emAndamento', 'finalizadas', 'valorTotal'],
            ])
            ->assertJsonPath('resumo.total', 4)
            ->assertJsonPath('resumo.abertas', 1)
            ->assertJsonPath('resumo.emAndamento', 2)
            ->assertJsonPath('resumo.finalizadas', 1)
            ->assertJsonPath('resumo.valorTotal', 1000);
    }

    public function test_filtro_por_status(): void
    {
        [, $headers] = $this->autenticar();

        OrdemServico::factory()->create(['status' => 'aberta']);
        OrdemServico::factory()->create(['status' => 'finalizada', 'data_fechamento' => now()]);

        $resposta = $this->getJson('/api/ordens-servico?status=finalizada', $headers);

        $resposta->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_busca_por_descricao(): void
    {
        [, $headers] = $this->autenticar();

        OrdemServico::factory()->create(['descricao' => 'Troca de pneu dianteiro']);
        OrdemServico::factory()->create(['descricao' => 'Revisão geral']);

        $resposta = $this->getJson('/api/ordens-servico?busca=pneu', $headers);

        $resposta->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_transicao_de_status_invalida_e_rejeitada(): void
    {
        [, $headers] = $this->autenticar();

        $ordem = OrdemServico::factory()->create(['status' => 'aberta']);

        // aberta -> finalizada não é uma transição permitida (precisa passar por em_andamento)
        $resposta = $this->putJson("/api/ordens-servico/{$ordem->id}", [
            'veiculo_id' => $ordem->veiculo_id,
            'descricao' => $ordem->descricao,
            'status' => 'finalizada',
            'valor' => $ordem->valor,
            'data_abertura' => $ordem->data_abertura->format('Y-m-d'),
            'data_fechamento' => now()->format('Y-m-d'),
        ], $headers);

        $resposta->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_transicao_de_status_valida_e_aceita(): void
    {
        [, $headers] = $this->autenticar();

        $ordem = OrdemServico::factory()->create(['status' => 'aberta']);

        $resposta = $this->putJson("/api/ordens-servico/{$ordem->id}", [
            'veiculo_id' => $ordem->veiculo_id,
            'descricao' => $ordem->descricao,
            'status' => 'em_andamento',
            'valor' => $ordem->valor,
            'data_abertura' => $ordem->data_abertura->format('Y-m-d'),
        ], $headers);

        $resposta->assertStatus(200)->assertJsonPath('status', 'em_andamento');
    }

    public function test_finalizar_preenche_data_fechamento_automaticamente(): void
    {
        [, $headers] = $this->autenticar();

        $ordem = OrdemServico::factory()->create(['status' => 'em_andamento']);

        $resposta = $this->putJson("/api/ordens-servico/{$ordem->id}", [
            'veiculo_id' => $ordem->veiculo_id,
            'descricao' => $ordem->descricao,
            'status' => 'finalizada',
            'valor' => $ordem->valor,
            'data_abertura' => $ordem->data_abertura->format('Y-m-d'),
        ], $headers);

        $resposta->assertStatus(200)->assertJsonPath('status', 'finalizada');
        $this->assertNotNull($ordem->fresh()->data_fechamento);
    }

    public function test_nao_permite_alterar_status_de_ordem_finalizada(): void
    {
        [, $headers] = $this->autenticar();

        $ordem = OrdemServico::factory()->create([
            'status' => 'finalizada',
            'data_fechamento' => now(),
        ]);

        $resposta = $this->putJson("/api/ordens-servico/{$ordem->id}", [
            'veiculo_id' => $ordem->veiculo_id,
            'descricao' => $ordem->descricao,
            'status' => 'em_andamento',
            'valor' => $ordem->valor,
            'data_abertura' => $ordem->data_abertura->format('Y-m-d'),
        ], $headers);

        $resposta->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_data_fechamento_so_pode_ser_informada_ao_finalizar(): void
    {
        [, $headers] = $this->autenticar();

        $ordem = OrdemServico::factory()->create(['status' => 'aberta']);

        $resposta = $this->putJson("/api/ordens-servico/{$ordem->id}", [
            'veiculo_id' => $ordem->veiculo_id,
            'descricao' => $ordem->descricao,
            'status' => 'em_andamento',
            'valor' => $ordem->valor,
            'data_abertura' => $ordem->data_abertura->format('Y-m-d'),
            'data_fechamento' => now()->format('Y-m-d'),
        ], $headers);

        $resposta->assertStatus(422)->assertJsonValidationErrors('data_fechamento');
    }

    public function test_nao_permite_excluir_ordem_finalizada(): void
    {
        [, $headers] = $this->autenticar();

        $ordem = OrdemServico::factory()->create([
            'status' => 'finalizada',
            'data_fechamento' => now(),
        ]);

        $resposta = $this->deleteJson("/api/ordens-servico/{$ordem->id}", [], $headers);

        $resposta->assertStatus(422);
        $this->assertDatabaseHas('ordens_servico', ['id' => $ordem->id]);
    }

    public function test_permite_excluir_ordem_aberta(): void
    {
        [, $headers] = $this->autenticar();

        $ordem = OrdemServico::factory()->create(['status' => 'aberta']);

        $this->deleteJson("/api/ordens-servico/{$ordem->id}", [], $headers)
            ->assertStatus(200);

        $this->assertDatabaseMissing('ordens_servico', ['id' => $ordem->id]);
    }
}
