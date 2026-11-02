<?php

namespace Tests\Feature;

use App\Models\Conta;
use App\Models\Servico;
use App\Models\ServicoAnexo;
use App\Models\ServicoPeca;
use App\Models\VeiculoConta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

/**
 * Evolução da manutenção: preventiva ou corretiva, peças com quantidade e preço, mão de obra, oficina,
 * notas fiscais, e o resumo do que está vencido ou perto de vencer.
 */
class ManutencaoEvolucaoTest extends TestCase
{
    use AutenticaUsuario;
    use RefreshDatabase;

    /** @var array<string, string> */
    private array $headers;

    private VeiculoConta $uno;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-03-15 12:00:00');
        Storage::fake('local');

        [, $this->headers] = $this->autenticarConta('pessoa');
        $this->uno = VeiculoConta::create(['placa' => 'AAA1B25', 'marca' => 'Fiat', 'modelo' => 'Uno', 'ano' => 2018]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function servico(array $extra = []): TestResponse
    {
        return $this->postJson('/api/conta/servicos', array_merge([
            'veiculo_conta_id' => $this->uno->id,
            'tipo' => 'filtros',
            'realizado_em' => '2026-03-01',
            'km' => 50000,
        ], $extra), $this->headers);
    }

    public function test_total_e_a_soma_de_pecas_e_mao_de_obra_e_a_oficina_e_a_natureza_ficam_gravadas(): void
    {
        $this->servico([
            'natureza' => 'corretiva',
            'oficina' => 'Oficina do Zé',
            'valor_mao_obra' => 80,
            'pecas' => [
                ['descricao' => 'Filtro de ar', 'quantidade' => 1, 'valor_unitario' => 45.5],
                ['descricao' => 'Pastilha de freio', 'quantidade' => 2, 'valor_unitario' => 60],
            ],
        ])->assertStatus(201);

        $servico = Servico::with('pecas')->firstOrFail();

        $this->assertSame('corretiva', $servico->natureza);
        $this->assertSame('Oficina do Zé', $servico->oficina);
        $this->assertEqualsWithDelta(165.5, (float) $servico->valor_pecas, 0.001);
        $this->assertEqualsWithDelta(245.5, (float) $servico->valor, 0.001);
        $this->assertCount(2, $servico->pecas);
        $this->assertEqualsWithDelta(120.0, $servico->pecas->last()->total(), 0.001);
    }

    public function test_natureza_fora_da_lista_e_recusada(): void
    {
        $this->servico(['natureza' => 'milagrosa'])->assertStatus(422)->assertJsonValidationErrors('natureza');
    }

    public function test_editar_troca_as_pecas_e_recalcula_o_total(): void
    {
        $id = $this->servico(['valor' => 100])->assertStatus(201)->json('servicos.0.id');
        $this->assertSame(100.0, (float) Servico::find($id)->valor);

        $this->putJson("/api/conta/servicos/{$id}", [
            'veiculo_conta_id' => $this->uno->id,
            'tipo' => 'filtros',
            'realizado_em' => '2026-03-01',
            'km' => 50000,
            'pecas' => [['descricao' => 'Filtro de óleo', 'quantidade' => 2, 'valor_unitario' => 30]],
        ], $this->headers)->assertOk();

        $servico = Servico::with('pecas')->find($id);
        $this->assertEqualsWithDelta(60.0, (float) $servico->valor, 0.001);
        $this->assertCount(1, $servico->pecas);
        $this->assertSame(0, ServicoPeca::where('servico_id', $id)->where('descricao', 'Filtro de ar')->count());
    }

    public function test_nota_fiscal_e_guardada_e_servida_por_link_assinado_sem_expor_o_caminho(): void
    {
        $id = $this->servico()->assertStatus(201)->json('servicos.0.id');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');

        $anexo = $this->postJson("/api/conta/servicos/{$id}/anexos", [
            'arquivo' => UploadedFile::fake()->createWithContent('nota-fiscal.png', $png),
        ], $this->headers)->assertStatus(201);

        $this->assertArrayNotHasKey('caminho', $anexo->json());
        Storage::disk('local')->assertExists(ServicoAnexo::findOrFail($anexo->json('id'))->caminho);

        $this->get($anexo->json('url'))->assertOk();
        $this->get("/api/servicos/anexos/{$anexo->json('id')}")->assertStatus(403);
    }

    public function test_resumo_conta_o_que_esta_vencido_e_o_custo_total(): void
    {
        // Troca de óleo feita em janeiro, com intervalo de 1 mês: vencida em março.
        $this->servico(['tipo' => 'oleo', 'realizado_em' => '2026-01-01', 'intervalo_meses' => 1, 'valor' => 120])->assertStatus(201);

        $this->getJson('/api/conta/servicos', $this->headers)
            ->assertOk()
            ->assertJsonPath('resumo.vencidos', 1)
            ->assertJsonPath('resumo.custo_total', 120);
    }

    public function test_servico_de_outra_conta_nao_e_alterado(): void
    {
        $contaB = Conta::create(['tipo' => 'pessoa', 'nome' => 'Outra pessoa']);
        app()->instance('conta.atual', $contaB->id);
        $veiculoB = VeiculoConta::create(['placa' => 'BBB2B22', 'marca' => 'VW', 'modelo' => 'Gol', 'ano' => 2020]);
        $servicoB = Servico::create(['veiculo_conta_id' => $veiculoB->id, 'tipo' => 'oleo', 'realizado_em' => '2026-03-01', 'valor' => 50]);
        app()->instance('conta.atual', $this->uno->conta_id);

        $this->putJson("/api/conta/servicos/{$servicoB->id}", [
            'veiculo_conta_id' => $veiculoB->id,
            'tipo' => 'oleo',
            'realizado_em' => '2026-03-01',
            'valor' => 1,
        ], $this->headers)->assertStatus(404);

        $this->assertDatabaseHas('servicos_conta', ['id' => $servicoB->id, 'valor' => 50]);
    }
}
