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

    /** @return array<string, mixed> */
    private function dadosVeiculo(int $clienteId, string $placa = 'XYZ9K88'): array
    {
        return [
            'cliente_id' => $clienteId,
            'placa' => $placa,
            'marca' => 'Fiat',
            'modelo' => 'Uno',
            'ano' => 2015,
        ];
    }

    public function test_nao_aceita_cliente_de_outra_oficina_ao_criar_ou_editar_veiculo(): void
    {
        [, $headersA] = $this->autenticar();
        $clienteA = Cliente::factory()->create();

        [, $headersB] = $this->autenticar();
        $clienteB = Cliente::factory()->create();
        $veiculoB = Veiculo::factory()->create(['cliente_id' => $clienteB->id, 'placa' => 'BBB1B11']);

        $this->postJson('/api/veiculos', $this->dadosVeiculo($clienteA->id), $headersB)
            ->assertStatus(422)
            ->assertJsonValidationErrors('cliente_id');

        $this->putJson("/api/veiculos/{$veiculoB->id}", $this->dadosVeiculo($clienteA->id, 'BBB1B11'), $headersB)
            ->assertStatus(422)
            ->assertJsonValidationErrors('cliente_id');

        $this->assertDatabaseMissing('veiculos', ['cliente_id' => $clienteA->id]);
        $this->assertNotNull($headersA);
    }

    public function test_nao_aceita_veiculo_de_outra_oficina_em_ordem_de_servico_e_manutencao(): void
    {
        [, $headersA] = $this->autenticar();
        $veiculoA = Veiculo::factory()->create();

        [, $headersB] = $this->autenticar();
        $clienteB = Cliente::factory()->create();
        $veiculoB = Veiculo::factory()->create(['cliente_id' => $clienteB->id]);
        $ordemB = OrdemServico::factory()->create(['veiculo_id' => $veiculoB->id]);

        $this->postJson('/api/ordens-servico', [
            'veiculo_id' => $veiculoA->id,
            'descricao' => 'Troca de óleo',
            'data_abertura' => '2026-10-01',
        ], $headersB)->assertStatus(422)->assertJsonValidationErrors('veiculo_id');

        $this->putJson("/api/ordens-servico/{$ordemB->id}", [
            'veiculo_id' => $veiculoA->id,
            'descricao' => 'Troca de óleo',
            'status' => 'aberta',
            'data_abertura' => '2026-10-01',
        ], $headersB)->assertStatus(422)->assertJsonValidationErrors('veiculo_id');

        $this->postJson('/api/manutencoes', [
            'veiculo_id' => $veiculoA->id,
            'tipo' => 'Revisão',
            'data_manutencao' => '2026-10-01',
        ], $headersB)->assertStatus(422)->assertJsonValidationErrors('veiculo_id');

        $this->assertDatabaseMissing('ordens_servico', ['veiculo_id' => $veiculoA->id, 'descricao' => 'Troca de óleo']);
        $this->assertDatabaseMissing('manutencoes', ['veiculo_id' => $veiculoA->id, 'tipo' => 'Revisão']);
        $this->assertNotNull($headersA);
    }

    public function test_placa_cpf_e_telefone_podem_repetir_em_outra_oficina(): void
    {
        [, $headersA] = $this->autenticar();
        $clienteA = Cliente::factory()->create(['cpf' => '11122233344', 'telefone' => '11911112222']);
        Veiculo::factory()->create(['cliente_id' => $clienteA->id, 'placa' => 'ABC1D23']);

        [, $headersB] = $this->autenticar();

        $cliente = $this->postJson('/api/clientes', [
            'nome' => 'Mesmo cliente, outra oficina',
            'cpf' => '11122233344',
            'telefone' => '11911112222',
            'ativo' => true,
        ], $headersB);
        $cliente->assertStatus(201);

        $this->postJson('/api/veiculos', $this->dadosVeiculo($cliente->json('id'), 'ABC1D23'), $headersB)
            ->assertStatus(201);

        $this->assertNotNull($headersA);
    }

    public function test_placa_cpf_e_telefone_continuam_unicos_dentro_da_oficina(): void
    {
        [, $headers] = $this->autenticar();
        $cliente = Cliente::factory()->create(['cpf' => '11122233344', 'telefone' => '11911112222']);
        Veiculo::factory()->create(['cliente_id' => $cliente->id, 'placa' => 'ABC1D23']);

        $this->postJson('/api/clientes', [
            'nome' => 'Outro', 'cpf' => '11122233344', 'telefone' => '11933334444', 'ativo' => true,
        ], $headers)->assertStatus(422)->assertJsonValidationErrors('cpf');

        $this->postJson('/api/clientes', [
            'nome' => 'Outro', 'cpf' => '55566677788', 'telefone' => '11911112222', 'ativo' => true,
        ], $headers)->assertStatus(422)->assertJsonValidationErrors('telefone');

        $this->postJson('/api/veiculos', $this->dadosVeiculo($cliente->id, 'ABC1D23'), $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors('placa');
    }
}
