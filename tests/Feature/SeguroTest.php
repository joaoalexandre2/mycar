<?php

namespace Tests\Feature;

use App\Mail\LembreteContaEmail;
use App\Models\Seguro;
use App\Models\VeiculoConta;
use App\Services\ComparadorSeguro;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class SeguroTest extends TestCase
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

    // Placa final 5: sem IPVA nem licenciamento perto de 05/03, para o e-mail só ter o seguro.
    private function veiculo(array $extra = []): VeiculoConta
    {
        return VeiculoConta::create(array_merge([
            'placa' => 'AAA1B25', 'marca' => 'Fiat', 'modelo' => 'Uno', 'ano' => 2018,
        ], $extra));
    }

    private function seguro(array $headers, VeiculoConta $veiculo, array $extra): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/api/conta/veiculos/{$veiculo->id}/seguros", array_merge([
            'tipo' => 'proposta', 'seguradora' => 'Seguradora A', 'valor_anual' => 2400,
        ], $extra), $headers);
    }

    // --------------------------------------------------------- comparação pura

    public function test_faixa_de_referencia_e_comparacao_das_propostas(): void
    {
        $r = (new ComparadorSeguro())->comparar(collect([
            ['id' => 1, 'tipo' => 'apolice', 'valor_anual' => 3000.0, 'vigencia_fim' => '2026-06-30'],
            ['id' => 2, 'tipo' => 'proposta', 'valor_anual' => 2400.0, 'vigencia_fim' => null],
            ['id' => 3, 'tipo' => 'proposta', 'valor_anual' => 3300.0, 'vigencia_fim' => null],
        ]), 50000.0);

        // 3%, 4,7% e 8% de R$ 50.000
        $this->assertSame(['baixo' => 1500.0, 'medio' => 2350.0, 'alto' => 4000.0], $r['referencia']);
        $this->assertSame(1, $r['apolice_atual_id']);
        $this->assertSame(2, $r['melhor_proposta_id']);

        $itens = collect($r['itens'])->keyBy('id');
        $this->assertSame(250.0, $itens[1]['valor_mensal']);
        $this->assertSame(600.0, $itens[2]['economia_vs_apolice']);     // 3000 - 2400
        $this->assertSame(-300.0, $itens[3]['economia_vs_apolice']);    // proposta mais cara que a apólice
        $this->assertNull($itens[1]['economia_vs_apolice']);            // a apólice não se compara consigo
        $this->assertSame(2.1, $itens[2]['vs_referencia_pct']);         // 2400 / 2350
    }

    public function test_sem_valor_fipe_nao_ha_referencia(): void
    {
        $r = (new ComparadorSeguro())->comparar(collect([
            ['id' => 1, 'tipo' => 'proposta', 'valor_anual' => 2400.0, 'vigencia_fim' => null],
        ]), null);

        $this->assertNull($r['referencia']);
        $this->assertNull($r['itens'][0]['vs_referencia_pct']);
        $this->assertNull($r['apolice_atual_id']);
        $this->assertSame(1, $r['melhor_proposta_id']);
    }

    // ------------------------------------------------------------------ API

    public function test_exige_autenticacao_e_perfil_de_conta(): void
    {
        $this->getJson('/api/conta/veiculos/1/seguros')->assertStatus(401);

        [, $headers] = $this->autenticar();
        $this->getJson('/api/conta/veiculos/1/seguros', $headers)->assertStatus(403);
    }

    public function test_cadastra_apolice_e_propostas_e_compara(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $veiculo = $this->veiculo(['fipe_valor' => 50000]);

        $this->seguro($headers, $veiculo, ['tipo' => 'apolice', 'seguradora' => 'Atual', 'valor_anual' => 3000, 'vigencia_fim' => '2026-06-30', 'franquia' => 2500])
            ->assertStatus(201);
        $this->seguro($headers, $veiculo, ['seguradora' => 'Barata', 'valor_anual' => 2400, 'observacoes' => 'Cobertura compreensiva']);
        $resposta = $this->seguro($headers, $veiculo, ['seguradora' => 'Cara', 'valor_anual' => 3300]);

        $resposta->assertJsonCount(3, 'seguros')
            ->assertJsonPath('referencia.medio', 2350)
            ->assertJsonPath('aviso', fn ($aviso) => str_contains($aviso, 'não é cotação'));

        $porSeguradora = collect($resposta->json('seguros'))->keyBy('seguradora');

        $this->assertSame(600, $porSeguradora['Barata']['economia_vs_apolice']);
        $this->assertSame(200, $porSeguradora['Barata']['valor_mensal']);
        $this->assertSame($porSeguradora['Atual']['id'], $resposta->json('apolice_atual_id'));
        $this->assertSame($porSeguradora['Barata']['id'], $resposta->json('melhor_proposta_id'));
        $this->assertArrayNotHasKey('alertado_em', $porSeguradora['Atual']);
    }

    public function test_valida_e_exige_o_fim_da_vigencia_so_na_apolice(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $veiculo = $this->veiculo();

        $this->postJson("/api/conta/veiculos/{$veiculo->id}/seguros", [], $headers)
            ->assertStatus(422)->assertJsonValidationErrors(['tipo', 'seguradora', 'valor_anual']);

        $this->seguro($headers, $veiculo, ['tipo' => 'apolice'])
            ->assertStatus(422)->assertJsonValidationErrors('vigencia_fim');

        $this->seguro($headers, $veiculo, ['valor_anual' => 0])->assertStatus(422)->assertJsonValidationErrors('valor_anual');
        $this->seguro($headers, $veiculo, ['tipo' => 'plano-b'])->assertStatus(422)->assertJsonValidationErrors('tipo');

        // Proposta ignora a vigência enviada.
        $this->seguro($headers, $veiculo, ['vigencia_fim' => '2026-12-01'])->assertStatus(201);
        $this->assertNull(Seguro::withoutGlobalScopes()->first()->vigencia_fim);
    }

    public function test_remove_um_seguro(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $veiculo = $this->veiculo();
        $id = $this->seguro($headers, $veiculo, [])->json('seguros.0.id');

        $this->deleteJson("/api/conta/veiculos/{$veiculo->id}/seguros/{$id}", [], $headers)
            ->assertStatus(200)->assertJsonCount(0, 'seguros');
        $this->deleteJson("/api/conta/veiculos/{$veiculo->id}/seguros/999", [], $headers)->assertStatus(404);
    }

    public function test_uma_conta_nao_ve_nem_mexe_nos_seguros_de_outra(): void
    {
        [, $headersA] = $this->autenticarConta('pessoa');
        $veiculoA = $this->veiculo();
        $id = $this->seguro($headersA, $veiculoA, ['seguradora' => 'Segredo da conta A'])->json('seguros.0.id');

        [, $headersB] = $this->autenticarConta('frota');

        $this->getJson("/api/conta/veiculos/{$veiculoA->id}/seguros", $headersB)->assertStatus(404);
        $this->seguro($headersB, $veiculoA, [])->assertStatus(404);
        $this->deleteJson("/api/conta/veiculos/{$veiculoA->id}/seguros/{$id}", [], $headersB)->assertStatus(404);

        $this->assertSame(1, Seguro::withoutGlobalScopes()->count());
    }

    public function test_apagar_o_veiculo_leva_os_seguros_junto(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $veiculo = $this->veiculo();
        $this->seguro($headers, $veiculo, []);

        $this->deleteJson("/api/conta/veiculos/{$veiculo->id}", [], $headers)->assertStatus(200);

        $this->assertSame(0, Seguro::withoutGlobalScopes()->count());
    }

    // ------------------------------------------------------------ lembrete

    public function test_avisa_a_renovacao_da_apolice_45_dias_antes_uma_unica_vez(): void
    {
        Mail::fake();
        [$usuario, $headers] = $this->autenticarConta('pessoa');
        $veiculo = $this->veiculo();
        $this->seguro($headers, $veiculo, ['tipo' => 'apolice', 'valor_anual' => 3000, 'vigencia_fim' => '2026-04-14']); // 40 dias

        $this->artisan('contas:alertar-vencimentos')->assertExitCode(0);
        $this->artisan('contas:alertar-vencimentos');

        Mail::assertSent(LembreteContaEmail::class, 1);
        Mail::assertSent(LembreteContaEmail::class, function (LembreteContaEmail $mail) use ($usuario) {
            return $mail->hasTo($usuario->email)
                && $mail->itens[0]['tipo'] === 'seguro'
                && $mail->itens[0]['dias'] === 40
                && $mail->itens[0]['valor_estimado'] === 3000.0;
        });
    }

    public function test_nao_avisa_apolice_longe_nem_proposta(): void
    {
        Mail::fake();
        [, $headers] = $this->autenticarConta('pessoa');
        $veiculo = $this->veiculo();
        $this->seguro($headers, $veiculo, ['tipo' => 'apolice', 'vigencia_fim' => '2026-06-30']); // 117 dias
        $this->seguro($headers, $veiculo, ['seguradora' => 'Proposta']);

        $this->artisan('contas:alertar-vencimentos');

        Mail::assertNothingSent();
    }

    public function test_mudar_a_vigencia_libera_novo_aviso_e_so_conta_a_apolice_mais_longa(): void
    {
        Mail::fake();
        [, $headers] = $this->autenticarConta('pessoa');
        $veiculo = $this->veiculo();
        $id = $this->seguro($headers, $veiculo, ['tipo' => 'apolice', 'vigencia_fim' => '2026-04-01'])->json('seguros.0.id');

        $this->artisan('contas:alertar-vencimentos');
        Mail::assertSent(LembreteContaEmail::class, 1);

        // Renovou: a nova vigência é longe, não avisa; depois chega perto de novo.
        Seguro::withoutGlobalScopes()->find($id)->update(['vigencia_fim' => '2027-04-01']);
        $this->artisan('contas:alertar-vencimentos');
        Mail::assertSent(LembreteContaEmail::class, 1);

        Carbon::setTestNow('2027-03-01');
        $this->artisan('contas:alertar-vencimentos');
        Mail::assertSent(LembreteContaEmail::class, 2);
    }

    public function test_o_fim_do_seguro_aparece_no_resumo_da_conta(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $veiculo = $this->veiculo();
        $this->seguro($headers, $veiculo, ['tipo' => 'apolice', 'valor_anual' => 3000, 'vigencia_fim' => '2026-04-14']);

        $vencimentos = $this->getJson('/api/conta/resumo', $headers)->json('vencimentos');

        $this->assertSame('seguro', $vencimentos[0]['tipo']);
        $this->assertSame(40, $vencimentos[0]['dias']);
        $this->assertSame(3000, $vencimentos[0]['valor_estimado']);
    }
}
