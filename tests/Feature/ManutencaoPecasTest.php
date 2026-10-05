<?php

namespace Tests\Feature;

use App\Models\Manutencao;
use App\Models\Veiculo;
use App\Models\VeiculoPeca;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class ManutencaoPecasTest extends TestCase
{
    use RefreshDatabase;
    use AutenticaUsuario;

    private function payload(Veiculo $veiculo, array $extra = []): array
    {
        return array_merge([
            'veiculo_id' => $veiculo->id,
            'tipo' => 'Troca de pastilhas',
            'descricao' => 'Pastilhas dianteiras',
            'data_manutencao' => now()->subDays(2)->toDateString(),
        ], $extra);
    }

    private function pecaDePastilha(array $extra = []): array
    {
        return array_merge([
            'tipo' => 'pastilha_freio',
            'especificacao' => 'Cód. PF-123',
            'marca' => 'Marca Teste',
        ], $extra);
    }

    public function test_criar_manutencao_com_pecas_registra_as_pecas_no_veiculo(): void
    {
        [, $headers] = $this->autenticar();
        $veiculo = Veiculo::factory()->create();
        $data = now()->subDays(2)->toDateString();

        $resposta = $this->postJson('/api/manutencoes', $this->payload($veiculo, [
            'data_manutencao' => $data,
            'pecas' => [
                $this->pecaDePastilha(),
                ['tipo' => 'bateria', 'especificacao' => '60Ah'],
            ],
        ]), $headers)->assertStatus(201);

        $manutencaoId = $resposta->json('id');

        // A própria manutenção já devolve as peças.
        $resposta->assertJsonCount(2, 'pecas')
            ->assertJsonPath('pecas.0.especificacao', 'Cód. PF-123');

        // E elas aparecem no cartão de peças do veículo, como "serviço" e com a data do serviço.
        $peca = VeiculoPeca::where('manutencao_id', $manutencaoId)->where('tipo', 'pastilha_freio')->first();

        $this->assertNotNull($peca);
        $this->assertSame($veiculo->id, $peca->veiculo_id);
        $this->assertSame('Cód. PF-123', $peca->especificacao);
        $this->assertSame('Marca Teste', $peca->marca);
        $this->assertSame('servico', $peca->fonte);
        $this->assertSame($data, $peca->usado_em->toDateString());

        $this->getJson("/api/veiculos/{$veiculo->id}/pecas", $headers)
            ->assertStatus(200)
            ->assertJsonCount(2, 'pecas');
    }

    public function test_manutencao_sem_pecas_continua_funcionando(): void
    {
        [, $headers] = $this->autenticar();
        $veiculo = Veiculo::factory()->create();

        $this->postJson('/api/manutencoes', $this->payload($veiculo), $headers)
            ->assertStatus(201)
            ->assertJsonCount(0, 'pecas');

        $this->assertDatabaseCount('veiculo_pecas', 0);
    }

    public function test_listagem_e_detalhe_trazem_as_pecas_da_manutencao(): void
    {
        [, $headers] = $this->autenticar();
        $veiculo = Veiculo::factory()->create();

        $id = $this->postJson('/api/manutencoes', $this->payload($veiculo, [
            'pecas' => [$this->pecaDePastilha()],
        ]), $headers)->json('id');

        $this->getJson("/api/manutencoes/{$id}", $headers)
            ->assertJsonPath('pecas.0.tipo', 'pastilha_freio');

        $this->getJson('/api/manutencoes?all=1', $headers)
            ->assertJsonPath('0.pecas.0.especificacao', 'Cód. PF-123');

        $this->getJson('/api/manutencoes', $headers)
            ->assertJsonPath('data.0.pecas.0.especificacao', 'Cód. PF-123');
    }

    public function test_atualizar_com_lista_substitui_as_pecas_da_manutencao(): void
    {
        [, $headers] = $this->autenticar();
        $veiculo = Veiculo::factory()->create();

        $id = $this->postJson('/api/manutencoes', $this->payload($veiculo, [
            'pecas' => [$this->pecaDePastilha(), ['tipo' => 'bateria', 'especificacao' => '60Ah']],
        ]), $headers)->json('id');

        $this->putJson("/api/manutencoes/{$id}", $this->payload($veiculo, [
            'pecas' => [['tipo' => 'velas', 'especificacao' => 'NGK BKR6E']],
        ]), $headers)
            ->assertStatus(200)
            ->assertJsonCount(1, 'pecas')
            ->assertJsonPath('pecas.0.tipo', 'velas');

        $this->assertDatabaseCount('veiculo_pecas', 1);
        $this->assertDatabaseMissing('veiculo_pecas', ['tipo' => 'pastilha_freio']);
    }

    public function test_atualizar_com_lista_vazia_remove_as_pecas(): void
    {
        [, $headers] = $this->autenticar();
        $veiculo = Veiculo::factory()->create();

        $id = $this->postJson('/api/manutencoes', $this->payload($veiculo, [
            'pecas' => [$this->pecaDePastilha()],
        ]), $headers)->json('id');

        $this->putJson("/api/manutencoes/{$id}", $this->payload($veiculo, ['pecas' => []]), $headers)
            ->assertStatus(200)
            ->assertJsonCount(0, 'pecas');

        $this->assertDatabaseCount('veiculo_pecas', 0);
    }

    public function test_atualizar_sem_o_campo_pecas_nao_mexe_nas_pecas_existentes(): void
    {
        [, $headers] = $this->autenticar();
        $veiculo = Veiculo::factory()->create();

        $id = $this->postJson('/api/manutencoes', $this->payload($veiculo, [
            'pecas' => [$this->pecaDePastilha()],
        ]), $headers)->json('id');

        // Cliente antigo: não conhece "pecas".
        $this->putJson("/api/manutencoes/{$id}", $this->payload($veiculo, ['descricao' => 'Só mudei o texto']), $headers)
            ->assertStatus(200)
            ->assertJsonCount(1, 'pecas');

        $this->assertDatabaseHas('veiculo_pecas', ['manutencao_id' => $id, 'especificacao' => 'Cód. PF-123']);
    }

    public function test_mudar_data_ou_veiculo_da_manutencao_leva_as_pecas_junto(): void
    {
        [, $headers] = $this->autenticar();
        $veiculoA = Veiculo::factory()->create();
        $veiculoB = Veiculo::factory()->create();

        $id = $this->postJson('/api/manutencoes', $this->payload($veiculoA, [
            'pecas' => [$this->pecaDePastilha()],
        ]), $headers)->json('id');

        $novaData = now()->subDays(10)->toDateString();

        $this->putJson("/api/manutencoes/{$id}", $this->payload($veiculoB, ['data_manutencao' => $novaData]), $headers)
            ->assertStatus(200);

        $peca = VeiculoPeca::where('manutencao_id', $id)->first();

        $this->assertSame($veiculoB->id, $peca->veiculo_id);
        $this->assertSame($novaData, $peca->usado_em->toDateString());
    }

    public function test_apagar_a_manutencao_remove_so_as_pecas_que_vieram_dela(): void
    {
        [, $headers] = $this->autenticar();
        $veiculo = Veiculo::factory()->create();

        $id = $this->postJson('/api/manutencoes', $this->payload($veiculo, [
            'pecas' => [$this->pecaDePastilha()],
        ]), $headers)->json('id');

        // Peça lançada à mão no veículo, sem manutenção.
        $this->postJson("/api/veiculos/{$veiculo->id}/pecas", [
            'tipo' => 'palheta',
            'especificacao' => '24"',
            'fonte' => 'ficha',
        ], $headers)->assertStatus(201);

        $this->deleteJson("/api/manutencoes/{$id}", [], $headers)->assertStatus(200);

        $this->assertDatabaseMissing('veiculo_pecas', ['especificacao' => 'Cód. PF-123']);
        $this->assertDatabaseHas('veiculo_pecas', ['tipo' => 'palheta']);
    }

    public function test_peca_invalida_barra_tudo_e_nao_cria_a_manutencao(): void
    {
        [, $headers] = $this->autenticar();
        $veiculo = Veiculo::factory()->create();

        $this->postJson('/api/manutencoes', $this->payload($veiculo, [
            'pecas' => [
                $this->pecaDePastilha(),
                ['tipo' => 'turbina_de_foguete', 'especificacao' => 'x'],
            ],
        ]), $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors('pecas.1.tipo');

        $this->postJson('/api/manutencoes', $this->payload($veiculo, [
            'pecas' => [['tipo' => 'bateria', 'especificacao' => '']],
        ]), $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors('pecas.0.especificacao');

        $this->assertDatabaseCount('manutencoes', 0);
        $this->assertDatabaseCount('veiculo_pecas', 0);
    }

    public function test_limita_a_quantidade_de_pecas_por_manutencao(): void
    {
        [, $headers] = $this->autenticar();
        $veiculo = Veiculo::factory()->create();

        $muitas = array_fill(0, 21, $this->pecaDePastilha());

        $this->postJson('/api/manutencoes', $this->payload($veiculo, ['pecas' => $muitas]), $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors('pecas');
    }

    public function test_pecas_da_manutencao_de_uma_oficina_nao_vazam_para_outra(): void
    {
        [, $headersA] = $this->autenticar();
        $veiculoA = Veiculo::factory()->create();
        $manutencaoId = $this->postJson('/api/manutencoes', $this->payload($veiculoA, [
            'pecas' => [$this->pecaDePastilha(['especificacao' => 'Segredo da Oficina A'])],
        ]), $headersA)->json('id');

        [, $headersB] = $this->autenticar();

        $this->getJson('/api/manutencoes?all=1', $headersB)
            ->assertJsonMissing(['especificacao' => 'Segredo da Oficina A']);
        $this->getJson("/api/manutencoes/{$manutencaoId}", $headersB)->assertStatus(404);
        $this->putJson("/api/manutencoes/{$manutencaoId}", $this->payload($veiculoA, ['pecas' => []]), $headersB)
            ->assertStatus(404);

        $this->assertDatabaseHas('veiculo_pecas', ['especificacao' => 'Segredo da Oficina A']);
        $this->assertSame(1, Manutencao::withoutGlobalScopes()->count());
        $this->assertSame(1, VeiculoPeca::withoutGlobalScopes()->count());
    }
}
