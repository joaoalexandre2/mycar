<?php

namespace Tests\Feature;

use App\Mail\LembreteContaEmail;
use App\Models\Documento;
use App\Models\VeiculoConta;
use App\Services\LembretesContaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class DocumentoTest extends TestCase
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

    // Placa final 5, sem UF: sem estimativas de IPVA/licenciamento, o aviso só tem o que o teste cadastra.
    private function veiculo(array $extra = []): VeiculoConta
    {
        return VeiculoConta::create(array_merge([
            'placa' => 'AAA1B25', 'marca' => 'Fiat', 'modelo' => 'Uno', 'ano' => 2018,
        ], $extra));
    }

    private function documento(array $headers, VeiculoConta $veiculo, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/api/conta/veiculos/{$veiculo->id}/documentos", array_merge([
            'tipo' => 'crlv', 'vencimento' => '2026-03-20',
        ], $extra), $headers);
    }

    public function test_exige_autenticacao_e_perfil_de_conta(): void
    {
        $this->getJson('/api/conta/veiculos/1/documentos')->assertStatus(401);

        [, $headers] = $this->autenticar();
        $this->getJson('/api/conta/veiculos/1/documentos', $headers)->assertStatus(403);
    }

    public function test_cadastra_lista_e_remove_documentos_com_situacao(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $veiculo = $this->veiculo();

        $this->documento($headers, $veiculo, ['vencimento' => '2026-03-20'])->assertStatus(201);
        $this->documento($headers, $veiculo, ['tipo' => 'vistoria', 'vencimento' => '2026-03-01']);
        $this->documento($headers, $veiculo, ['tipo' => 'outro', 'titulo' => 'CNH do motorista', 'vencimento' => '2027-01-01']);
        $r = $this->documento($headers, $veiculo, ['tipo' => 'outro', 'titulo' => 'Manual', 'vencimento' => null]);

        $r->assertJsonCount(4, 'documentos')->assertJsonPath('crlv.vencimento', '2026-03-20');

        $porRotulo = collect($r->json('documentos'))->keyBy('rotulo');
        $this->assertSame('vence_em_breve', $porRotulo['CRLV']['situacao']);
        $this->assertSame(15, $porRotulo['CRLV']['dias_para_vencer']);
        $this->assertSame('vencido', $porRotulo['Vistoria']['situacao']);
        $this->assertSame('em_dia', $porRotulo['CNH do motorista']['situacao']);
        $this->assertSame('sem_data', $porRotulo['Manual']['situacao']);
        $this->assertArrayNotHasKey('alertado_em', $porRotulo['CRLV']);

        $id = $porRotulo['Manual']['id'];
        $this->deleteJson("/api/conta/veiculos/{$veiculo->id}/documentos/{$id}", [], $headers)
            ->assertStatus(200)->assertJsonCount(3, 'documentos');
        $this->deleteJson("/api/conta/veiculos/{$veiculo->id}/documentos/999", [], $headers)->assertStatus(404);
    }

    public function test_valida_tipo_e_exige_titulo_so_em_outro(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $veiculo = $this->veiculo();

        $this->postJson("/api/conta/veiculos/{$veiculo->id}/documentos", [], $headers)
            ->assertStatus(422)->assertJsonValidationErrors('tipo');
        $this->documento($headers, $veiculo, ['tipo' => 'passaporte'])->assertStatus(422);
        $this->documento($headers, $veiculo, ['tipo' => 'outro'])->assertStatus(422)->assertJsonValidationErrors('titulo');
        $this->documento($headers, $veiculo, ['vencimento' => 'ontem'])->assertStatus(422)->assertJsonValidationErrors('vencimento');

        // Título enviado num CRLV é ignorado.
        $this->documento($headers, $veiculo, ['titulo' => 'qualquer'])->assertStatus(201);
        $this->assertNull(Documento::withoutGlobalScopes()->first()->titulo);
    }

    public function test_uma_conta_nao_ve_nem_mexe_nos_documentos_de_outra(): void
    {
        [, $headersA] = $this->autenticarConta('pessoa');
        $veiculoA = $this->veiculo();
        $id = $this->documento($headersA, $veiculoA)->json('documentos.0.id');

        [, $headersB] = $this->autenticarConta('frota');

        $this->getJson("/api/conta/veiculos/{$veiculoA->id}/documentos", $headersB)->assertStatus(404);
        $this->documento($headersB, $veiculoA)->assertStatus(404);
        $this->deleteJson("/api/conta/veiculos/{$veiculoA->id}/documentos/{$id}", [], $headersB)->assertStatus(404);

        $this->assertSame(1, Documento::withoutGlobalScopes()->count());
    }

    public function test_documento_perto_de_vencer_aparece_na_visao_geral(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $veiculo = $this->veiculo();
        $this->documento($headers, $veiculo, ['tipo' => 'outro', 'titulo' => 'CNH', 'vencimento' => '2026-03-25']);
        $this->documento($headers, $veiculo, ['tipo' => 'outro', 'titulo' => 'Longe', 'vencimento' => '2027-03-25']);

        $r = $this->getJson('/api/conta/resumo', $headers)->assertStatus(200);

        $this->assertCount(1, $r->json('vencimentos'));
        $this->assertSame('documento', $r->json('vencimentos.0.tipo'));
        $this->assertSame('CNH', $r->json('vencimentos.0.rotulo'));
    }

    public function test_crlv_com_data_real_substitui_a_estimativa_de_licenciamento(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $veiculo = $this->veiculo(['uf' => 'SP']);

        $estimativa = $veiculo->fresh()->proximo_vencimento_licenciamento;
        $this->assertNotNull($estimativa);
        Carbon::setTestNow(Carbon::parse($estimativa)->subDays(10));

        $tipos = fn () => collect($this->getJson('/api/conta/resumo', $headers)->json('vencimentos'))->pluck('tipo');
        $this->assertContains('licenciamento', $tipos());

        // CRLV com data real longe: a estimativa some e não há aviso de licenciamento.
        $this->documento($headers, $veiculo, ['vencimento' => Carbon::now()->addYear()->toDateString()]);

        $this->assertNotContains('licenciamento', $tipos());
        $this->assertNotContains('licenciamento', collect(app(LembretesContaService::class)->pendentes())->pluck('tipo'));
    }

    public function test_lembrete_por_email_avisa_uma_vez_e_de_novo_se_a_data_mudar(): void
    {
        Mail::fake();
        [, $headers] = $this->autenticarConta('pessoa');
        $veiculo = $this->veiculo();
        $this->documento($headers, $veiculo, ['vencimento' => '2026-03-20']);

        $this->artisan('contas:alertar-vencimentos')->assertExitCode(0);
        Mail::assertSent(LembreteContaEmail::class, function ($mail) {
            return collect($mail->itens)->contains(fn ($l) => $l['tipo'] === 'documento' && $l['rotulo'] === 'CRLV');
        });
        Mail::assertSent(LembreteContaEmail::class, 1);

        // Segunda rodada: nada novo.
        $this->artisan('contas:alertar-vencimentos')->assertExitCode(0);
        Mail::assertSent(LembreteContaEmail::class, 1);

        // Nova data de vencimento reabre o aviso.
        $documento = Documento::withoutGlobalScopes()->first();
        $documento->vencimento = '2026-03-28';
        $documento->save();

        $this->artisan('contas:alertar-vencimentos')->assertExitCode(0);
        Mail::assertSent(LembreteContaEmail::class, 2);
    }

    public function test_so_o_crlv_mais_recente_gera_aviso(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $veiculo = $this->veiculo();
        $this->documento($headers, $veiculo, ['vencimento' => '2026-03-10']); // antigo
        $this->documento($headers, $veiculo, ['vencimento' => '2027-03-10']); // renovado

        $this->assertSame([], app(LembretesContaService::class)->pendentes());
    }
}
