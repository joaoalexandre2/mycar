<?php

namespace Tests\Feature;

use App\Mail\LembreteContaEmail;
use App\Models\Abastecimento;
use App\Models\Servico;
use App\Models\VeiculoConta;
use App\Services\LembretesContaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class ServicoTest extends TestCase
{
    use RefreshDatabase;
    use AutenticaUsuario;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-03-05');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // Placa final 5, sem UF: sem estimativas de IPVA/licenciamento nos avisos.
    private function veiculo(): VeiculoConta
    {
        return VeiculoConta::create(['placa' => 'AAA1B25', 'marca' => 'Fiat', 'modelo' => 'Uno', 'ano' => 2018]);
    }

    private function servico(array $headers, VeiculoConta $veiculo, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/conta/servicos', array_merge([
            'veiculo_conta_id' => $veiculo->id,
            'tipo' => 'oleo',
            'realizado_em' => '2026-03-01',
            'km' => 50000,
        ], $extra), $headers);
    }

    public function test_exige_autenticacao_e_perfil_de_conta(): void
    {
        $this->getJson('/api/conta/servicos')->assertStatus(401);

        [, $headers] = $this->autenticar();
        $this->getJson('/api/conta/servicos', $headers)->assertStatus(403);
    }

    public function test_calcula_a_proxima_troca_por_prazo_e_por_km(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $veiculo = $this->veiculo();

        $r = $this->servico($headers, $veiculo, ['intervalo_meses' => 6, 'intervalo_km' => 10000])
            ->assertStatus(201);

        $s = $r->json('servicos.0');
        $this->assertSame('Troca de óleo', $s['rotulo']);
        $this->assertSame('2026-09-01', $s['proximo_em']);
        $this->assertSame(60000, $s['proxima_km']);
        $this->assertSame(50000, $s['km_atual']);
        $this->assertSame(10000, $s['km_restante']);
        $this->assertSame('em_dia', $s['situacao']);
        $this->assertArrayNotHasKey('alerta_data_em', $s);
        $this->assertCount(9, $r->json('tipos'));
    }

    public function test_sem_intervalo_nao_ha_aviso(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');

        $s = $this->servico($headers, $this->veiculo())->json('servicos.0');

        $this->assertNull($s['proximo_em']);
        $this->assertNull($s['proxima_km']);
        $this->assertSame('sem_aviso', $s['situacao']);
    }

    public function test_valida_campos(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $veiculo = $this->veiculo();

        $this->postJson('/api/conta/servicos', [], $headers)
            ->assertStatus(422)->assertJsonValidationErrors(['veiculo_conta_id', 'tipo', 'realizado_em']);
        $this->servico($headers, $veiculo, ['tipo' => 'turbina'])->assertStatus(422)->assertJsonValidationErrors('tipo');
        $this->servico($headers, $veiculo, ['tipo' => 'outro'])->assertStatus(422)->assertJsonValidationErrors('titulo');
        $this->servico($headers, $veiculo, ['realizado_em' => '2026-04-01'])->assertStatus(422)->assertJsonValidationErrors('realizado_em');
        $this->servico($headers, $veiculo, ['intervalo_meses' => 0])->assertStatus(422)->assertJsonValidationErrors('intervalo_meses');

        // Aviso por km exige saber o km do dia do serviço.
        $this->servico($headers, $veiculo, ['km' => null, 'intervalo_km' => 10000])
            ->assertStatus(422)->assertJsonValidationErrors('km');

        $this->servico($headers, $veiculo, ['veiculo_conta_id' => 999])->assertStatus(404);
    }

    public function test_so_o_servico_mais_recente_do_tipo_e_vigente(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $veiculo = $this->veiculo();

        $this->servico($headers, $veiculo, ['realizado_em' => '2025-09-01', 'km' => 40000, 'intervalo_meses' => 6]);
        $r = $this->servico($headers, $veiculo, ['realizado_em' => '2026-03-01', 'km' => 50000, 'intervalo_meses' => 6]);
        $this->servico($headers, $veiculo, ['tipo' => 'outro', 'titulo' => 'Pastilhas', 'intervalo_meses' => 12]);

        $porData = collect($r->json('servicos'))->keyBy('realizado_em');
        $this->assertTrue($porData['2026-03-01']['vigente']);
        $this->assertFalse($porData['2025-09-01']['vigente']);
        $this->assertSame('anterior', $porData['2025-09-01']['situacao']);
    }

    public function test_km_atual_vem_dos_abastecimentos_e_dos_servicos(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $veiculo = $this->veiculo();
        $this->servico($headers, $veiculo, ['km' => 50000, 'intervalo_km' => 10000]);

        Abastecimento::create([
            'veiculo_conta_id' => $veiculo->id, 'data' => '2026-03-04', 'km' => 59500,
            'litros' => 40, 'valor_total' => 240,
        ]);

        $s = $this->getJson('/api/conta/servicos', $headers)->json('servicos.0');

        $this->assertSame(59500, $s['km_atual']);
        $this->assertSame(500, $s['km_restante']);
        $this->assertSame('vence_em_breve', $s['situacao']);
    }

    public function test_remove_servico_e_isola_por_conta(): void
    {
        [, $headersA] = $this->autenticarConta('pessoa');
        $veiculoA = $this->veiculo();
        $id = $this->servico($headersA, $veiculoA)->json('servicos.0.id');

        [, $headersB] = $this->autenticarConta('frota');
        $this->assertCount(0, $this->getJson('/api/conta/servicos', $headersB)->json('servicos'));
        $this->servico($headersB, $veiculoA)->assertStatus(404);
        $this->deleteJson("/api/conta/servicos/{$id}", [], $headersB)->assertStatus(404);
        $this->assertSame(1, Servico::withoutGlobalScopes()->count());

        $this->deleteJson("/api/conta/servicos/{$id}", [], $headersA)->assertStatus(200)->assertJsonCount(0, 'servicos');
    }

    public function test_aparece_na_visao_geral_por_prazo_e_por_km(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $veiculo = $this->veiculo();

        // Óleo: vence por prazo em 20 dias. Bateria: sem prazo, mas a 500 km do limite (km atual = 50.000).
        $this->servico($headers, $veiculo, ['realizado_em' => '2025-09-25', 'intervalo_meses' => 6, 'km' => 50000]);
        $this->servico($headers, $veiculo, ['tipo' => 'bateria', 'intervalo_km' => 1500, 'km' => 49000]);

        $v = collect($this->getJson('/api/conta/resumo', $headers)->json('vencimentos'))->keyBy('rotulo');

        $this->assertSame('servico', $v['Troca de óleo']['tipo']);
        $this->assertSame('2026-03-25', $v['Troca de óleo']['data']);
        $this->assertFalse($v['Troca de óleo']['por_km']);
        $this->assertTrue($v['Bateria']['por_km']);
        $this->assertSame(500, $v['Bateria']['km_restante']);
    }

    public function test_lembrete_por_email_por_prazo_e_por_km_uma_vez_cada(): void
    {
        Mail::fake();
        [, $headers] = $this->autenticarConta('pessoa');
        $veiculo = $this->veiculo();

        // Óleo feito há 5 meses e 25 dias (prazo de 6 meses chega em ~5 dias) e a 600 km do limite.
        $this->servico($headers, $veiculo, ['realizado_em' => '2025-09-10', 'km' => 50000, 'intervalo_meses' => 6, 'intervalo_km' => 10000]);
        Abastecimento::create(['veiculo_conta_id' => $veiculo->id, 'data' => '2026-03-04', 'km' => 59400, 'litros' => 40, 'valor_total' => 240]);

        $motivos = collect(app(LembretesContaService::class)->pendentes())->pluck('motivo')->sort()->values()->all();
        $this->assertSame(['data', 'km'], $motivos);

        $this->artisan('contas:alertar-vencimentos')->assertExitCode(0);
        Mail::assertSent(LembreteContaEmail::class, 1);

        // Segunda rodada: nada novo.
        $this->artisan('contas:alertar-vencimentos')->assertExitCode(0);
        Mail::assertSent(LembreteContaEmail::class, 1);
    }

    public function test_so_o_servico_vigente_gera_aviso(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $veiculo = $this->veiculo();

        $this->servico($headers, $veiculo, ['realizado_em' => '2025-09-10', 'intervalo_meses' => 6]); // venceria
        $this->servico($headers, $veiculo, ['realizado_em' => '2026-03-01', 'intervalo_meses' => 6]); // renovado

        $this->assertSame([], app(LembretesContaService::class)->pendentes());
    }

    public function test_email_mostra_o_km_que_falta(): void
    {
        $conta = new \App\Models\Conta(['nome' => 'X', 'tipo' => 'pessoa']);

        $email = new LembreteContaEmail($conta, [[
            'tipo' => 'servico', 'rotulo' => 'Troca de óleo', 'veiculo' => 'Uno', 'placa' => 'AAA1B25',
            'data' => '2026-03-05', 'dias' => 0, 'valor_estimado' => null,
            'km_restante' => 600, 'proxima_km' => 60000,
        ]]);

        $this->assertStringContainsString('faltam 600 km (aos 60.000 km)', $email->render());
        $this->assertSame('Lembrete: Troca de óleo - Uno', $email->build()->subject);
    }
}
