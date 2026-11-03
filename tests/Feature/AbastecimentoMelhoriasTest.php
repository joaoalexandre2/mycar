<?php

namespace Tests\Feature;

use App\Models\Conta;
use App\Models\Motorista;
use App\Models\VeiculoConta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

/**
 * Melhorias no abastecimento: alertas de inconsistência que não bloqueiam, justificativa para conflitos,
 * motorista, hora e fotos do comprovante e do hodômetro.
 */
class AbastecimentoMelhoriasTest extends TestCase
{
    use AutenticaUsuario;
    use RefreshDatabase;

    private const JUSTIFICATIVA = 'Odômetro trocado no painel do carro';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-30 12:00:00');
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function veiculo(): VeiculoConta
    {
        return VeiculoConta::create(['placa' => 'AAA1A11', 'marca' => 'Fiat', 'modelo' => 'Uno', 'ano' => 2018]);
    }

    private function abastecer(array $headers, VeiculoConta $veiculo, array $dados = []): TestResponse
    {
        return $this->postJson("/api/conta/veiculos/{$veiculo->id}/abastecimentos", array_merge([
            'data' => '2026-05-01',
            'km' => 10000,
            'litros' => 40,
            'valor_total' => 200,
            'tanque_cheio' => true,
        ], $dados), $headers);
    }

    /** O alerta de um registro, pelo código. */
    private function alertasDo(array $abastecimento): array
    {
        return collect($abastecimento['alertas'] ?? [])->pluck('codigo')->all();
    }

    public function test_km_que_volta_pede_justificativa_e_com_ela_grava_com_alerta(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $veiculo = $this->veiculo();
        $this->abastecer($headers, $veiculo, ['data' => '2026-05-01', 'km' => 10000])->assertStatus(201);

        $this->abastecer($headers, $veiculo, ['data' => '2026-05-10', 'km' => 9000])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['km', 'justificativa']);

        $registro = $this->abastecer($headers, $veiculo, ['data' => '2026-05-10', 'km' => 9000, 'justificativa' => self::JUSTIFICATIVA])
            ->assertStatus(201)
            ->json('abastecimentos.0');

        $this->assertSame(['km_menor_que_anterior'], $this->alertasDo($registro));
    }

    public function test_possivel_duplicado_pede_justificativa(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $veiculo = $this->veiculo();
        $this->abastecer($headers, $veiculo, ['data' => '2026-05-01', 'km' => 10000])->assertStatus(201);

        $this->abastecer($headers, $veiculo, ['data' => '2026-05-01', 'km' => 10000])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['justificativa']);

        $registro = $this->abastecer($headers, $veiculo, ['data' => '2026-05-01', 'km' => 10000, 'justificativa' => 'Dois tanques no mesmo dia, cada um pago'])
            ->assertStatus(201)
            ->json('abastecimentos.0');

        $this->assertContains('possivel_duplicado', $this->alertasDo($registro));
    }

    public function test_preco_fora_da_faixa_grava_com_alerta_sem_bloquear(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $veiculo = $this->veiculo();

        // R$ 20,00 por 40 litros: R$ 0,50 por litro, abaixo da faixa.
        $registro = $this->abastecer($headers, $veiculo, ['valor_total' => 20])
            ->assertStatus(201)
            ->json('abastecimentos.0');

        $this->assertSame(['preco_fora_da_faixa'], $this->alertasDo($registro));
    }

    public function test_consumo_muito_diferente_da_media_vira_alerta(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $veiculo = $this->veiculo();

        // Dois trechos de 400 km com 40 litros cada: 10 km/l de média.
        $this->abastecer($headers, $veiculo, ['data' => '2026-05-01', 'km' => 1000]);
        $this->abastecer($headers, $veiculo, ['data' => '2026-05-10', 'km' => 1400]);
        $this->abastecer($headers, $veiculo, ['data' => '2026-05-20', 'km' => 1800]);

        // Só 200 km com 40 litros: 5 km/l, metade da média.
        $ultimo = $this->abastecer($headers, $veiculo, ['data' => '2026-05-28', 'km' => 2000])
            ->assertStatus(201)
            ->json('abastecimentos');

        $this->assertContains('consumo_fora_do_padrao', $this->alertasDo($ultimo[0]));
        $this->assertNull($ultimo[1]['alertas'] ?? null);
    }

    public function test_motorista_e_hora_sao_gravados_e_motorista_de_outra_conta_e_recusado(): void
    {
        [, $headers] = $this->autenticarConta('frota');
        $veiculo = $this->veiculo();
        $joao = Motorista::create(['nome' => 'João', 'cnh_numero' => '123', 'cnh_validade' => now()->addYear()->toDateString()]);

        $this->abastecer($headers, $veiculo, ['motorista_id' => $joao->id, 'hora' => '14:35'])
            ->assertStatus(201)
            ->assertJsonPath('abastecimentos.0.motorista_id', $joao->id)
            ->assertJsonPath('abastecimentos.0.hora', '14:35');

        $contaB = Conta::create(['tipo' => 'frota', 'nome' => 'Empresa B']);
        app()->instance('conta.atual', $contaB->id);
        $motoristaB = Motorista::create(['nome' => 'Carla', 'cnh_numero' => '999', 'cnh_validade' => now()->addYear()->toDateString()]);
        app()->instance('conta.atual', Conta::where('tipo', 'frota')->orderBy('id')->first()->id);

        $this->abastecer($headers, $veiculo, ['data' => '2026-05-05', 'km' => 10500, 'motorista_id' => $motoristaB->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['motorista_id']);
    }

    public function test_comprovante_fica_guardado_e_e_servido_por_link_assinado_sem_expor_o_caminho(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $veiculo = $this->veiculo();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');

        $registro = $this->abastecer($headers, $veiculo, [
            'comprovante' => UploadedFile::fake()->createWithContent('nota.png', $png),
        ])->assertStatus(201)->json('abastecimentos.0');

        $this->assertArrayNotHasKey('comprovante_caminho', $registro);
        $this->assertNotNull($registro['comprovante_url']);

        $this->get($registro['comprovante_url'])->assertOk();

        // Sem assinatura, a foto não sai.
        $this->get("/api/abastecimentos/{$registro['id']}/comprovante")->assertStatus(403);
    }
}
