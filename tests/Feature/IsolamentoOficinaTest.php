<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Manutencao;
use App\Models\Oficina;
use App\Models\OrdemServico;
use App\Models\Veiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

/**
 * Garante que uma oficina nunca vê nem altera dados de outra oficina.
 * É o teste mais importante do sistema multi-oficina: se ele quebrar,
 * há vazamento de dados entre clientes diferentes do MyCar.
 */
class IsolamentoOficinaTest extends TestCase
{
    use RefreshDatabase;
    use AutenticaUsuario;

    public function test_clientes_de_uma_oficina_nao_aparecem_para_outra(): void
    {
        [$usuarioA, $headersA] = $this->autenticar();
        $clienteA = Cliente::factory()->create(['nome' => 'Cliente da Oficina A']);

        [$usuarioB, $headersB] = $this->autenticar();
        $clienteB = Cliente::factory()->create(['nome' => 'Cliente da Oficina B']);

        $this->assertNotEquals($usuarioA->oficina_id, $usuarioB->oficina_id);

        // Oficina B não vê o cliente da oficina A.
        $resposta = $this->getJson('/api/clientes?all=1', $headersB);
        $resposta->assertStatus(200)
            ->assertJsonMissing(['nome' => 'Cliente da Oficina A'])
            ->assertJsonFragment(['nome' => 'Cliente da Oficina B']);

        // Oficina B não consegue ver, editar nem apagar o cliente da oficina A pelo id.
        $this->getJson("/api/clientes/{$clienteA->id}", $headersB)->assertStatus(404);
        $this->putJson("/api/clientes/{$clienteA->id}", ['nome' => 'Hackeado', 'cpf' => '00000000000', 'telefone' => '0', 'ativo' => true], $headersB)
            ->assertStatus(404);
        $this->deleteJson("/api/clientes/{$clienteA->id}", [], $headersB)->assertStatus(404);
        $this->assertDatabaseHas('clientes', ['id' => $clienteA->id]);

        // E a oficina A continua vendo só o próprio cliente.
        $this->getJson('/api/clientes?all=1', $headersA)
            ->assertJsonMissing(['nome' => 'Cliente da Oficina B'])
            ->assertJsonFragment(['nome' => 'Cliente da Oficina A']);
    }

    public function test_veiculos_ordens_e_manutencoes_tambem_sao_isolados(): void
    {
        [, $headersA] = $this->autenticar();
        $veiculoA = Veiculo::factory()->create();
        $ordemA = OrdemServico::factory()->create(['veiculo_id' => $veiculoA->id]);
        $manutencaoA = Manutencao::factory()->create(['veiculo_id' => $veiculoA->id]);

        [, $headersB] = $this->autenticar();

        $this->getJson("/api/veiculos/{$veiculoA->id}", $headersB)->assertStatus(404);
        $this->getJson("/api/ordens-servico/{$ordemA->id}", $headersB)->assertStatus(404);
        $this->getJson("/api/manutencoes/{$manutencaoA->id}", $headersB)->assertStatus(404);
    }

    public function test_cliente_criado_e_automaticamente_vinculado_a_oficina_do_usuario(): void
    {
        $oficina = Oficina::factory()->create();
        [, $headers] = $this->autenticar($oficina);

        $resposta = $this->postJson('/api/clientes', [
            'nome' => 'Novo Cliente',
            'cpf' => '98765432100',
            'telefone' => '11988887777',
            'ativo' => true,
        ], $headers);

        $resposta->assertStatus(201);
        $this->assertDatabaseHas('clientes', [
            'nome' => 'Novo Cliente',
            'oficina_id' => $oficina->id,
        ]);
    }
}
