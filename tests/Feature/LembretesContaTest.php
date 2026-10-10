<?php

namespace Tests\Feature;

use App\Mail\LembreteContaEmail;
use App\Models\Conta;
use App\Models\User;
use App\Models\VeiculoConta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

/**
 * Lembretes de IPVA, licenciamento e revisão para pessoa e frota.
 * O IPVA vence sempre em 31/01. Datas fixas: 05/03/2026 (IPVA só em 2027); final 3
 * tem licenciamento em 31/05 (fora) e final 1 em 31/03. Os testes de IPVA usam 05/01.
 */
class LembretesContaTest extends TestCase
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

    private function veiculo(string $placa, array $extra = []): VeiculoConta
    {
        return VeiculoConta::create(array_merge([
            'placa' => $placa, 'marca' => 'Fiat', 'modelo' => 'Uno', 'ano' => 2018, 'uf' => 'RS',
        ], $extra));
    }
    public function test_avisa_ipva_e_revisao_em_um_unico_email(): void
    {
        Carbon::setTestNow('2026-01-05'); // o IPVA vence em 31/01, 26 dias
        Mail::fake();
        [$usuario] = $this->autenticarConta('pessoa');

        $this->veiculo('AAA1B23', ['apelido' => 'Meu Uno', 'fipe_valor' => 40000]);          // IPVA 31/01
        $this->veiculo('EEE1F25', ['revisao_prevista_em' => '2026-01-20']);                   // revisão em 15 dias

        $this->artisan('contas:alertar-vencimentos')->assertExitCode(0);

        Mail::assertSent(LembreteContaEmail::class, 1);
        Mail::assertSent(LembreteContaEmail::class, function (LembreteContaEmail $mail) use ($usuario) {
            $tipos = collect($mail->itens)->pluck('tipo')->unique()->sort()->values()->all();

            return $mail->hasTo($usuario->email) && $tipos === ['ipva', 'revisao'];
        });
    }

    public function test_avisa_o_licenciamento_pelo_final_da_placa(): void
    {
        Mail::fake();
        [$usuario] = $this->autenticarConta('pessoa');

        $this->veiculo('CCC1D21');  // final 1: licenciamento em 31/03 (26 dias); IPVA só em 2027

        $this->artisan('contas:alertar-vencimentos')->assertExitCode(0);

        Mail::assertSent(LembreteContaEmail::class, fn (LembreteContaEmail $mail) => $mail->hasTo($usuario->email)
            && collect($mail->itens)->pluck('tipo')->all() === ['licenciamento']);
    }
    public function test_nao_repete_o_aviso_do_mesmo_ciclo(): void
    {
        Carbon::setTestNow('2026-01-05'); // o IPVA vence em 31/01, 26 dias
        Mail::fake();
        $this->autenticarConta('pessoa');
        $veiculo = $this->veiculo('AAA1B23');

        $this->artisan('contas:alertar-vencimentos');
        $this->artisan('contas:alertar-vencimentos');

        Mail::assertSent(LembreteContaEmail::class, 1);
        $this->assertSame(2026, $veiculo->fresh()->makeVisible('ipva_alertado_ano')->ipva_alertado_ano);
    }

    public function test_revisao_atrasada_e_avisada_uma_vez_e_mudar_a_data_libera_novo_aviso(): void
    {
        Mail::fake();
        $this->autenticarConta('pessoa');
        $veiculo = $this->veiculo('EEE1F25', ['revisao_prevista_em' => '2026-02-20']); // atrasada

        $this->artisan('contas:alertar-vencimentos');
        Mail::assertSent(LembreteContaEmail::class, fn ($mail) => $mail->itens[0]['dias'] === -13);

        $this->artisan('contas:alertar-vencimentos');
        Mail::assertSent(LembreteContaEmail::class, 1);

        $veiculo->fresh()->update(['revisao_prevista_em' => '2026-03-25']);

        $this->artisan('contas:alertar-vencimentos');
        Mail::assertSent(LembreteContaEmail::class, 2);
    }

    public function test_nao_avisa_o_que_esta_longe_nem_sem_estado(): void
    {
        Mail::fake();
        $this->autenticarConta('pessoa');
        $this->veiculo('EEE1F25');                                       // IPVA só em jan/2027, lic. jul/31: fora
        $this->veiculo('GGG1H23', ['uf' => null, 'revisao_prevista_em' => '2026-12-01']); // revisão longe

        $this->artisan('contas:alertar-vencimentos');

        // Em março o IPVA só vence em 2027, o licenciamento (finais 3 e 5) é em maio/julho e a
        // revisão é em dezembro: nada a avisar, tenha a placa estado ou não.
        Mail::assertNothingSent();
    }

    public function test_respeita_a_conta_que_desligou_os_lembretes(): void
    {
        Mail::fake();
        [$usuario] = $this->autenticarConta('pessoa');
        $usuario->conta->update(['lembretes_email' => false]);
        $this->veiculo('AAA1B23');

        $this->artisan('contas:alertar-vencimentos');

        Mail::assertNothingSent();
    }

    public function test_so_envia_para_usuarios_de_conta_com_email_confirmado(): void
    {
        Carbon::setTestNow('2026-01-05'); // o IPVA vence em 31/01, 26 dias
        Mail::fake();
        [$confirmado] = $this->autenticarConta('frota');
        $pendente = User::factory()->unverified()->create(['conta_id' => $confirmado->conta_id, 'perfil' => 'frota']);
        $voltouParaOficina = User::factory()->create(['conta_id' => $confirmado->conta_id, 'perfil' => 'oficina']);
        $this->veiculo('AAA1B23');

        $this->artisan('contas:alertar-vencimentos');

        Mail::assertSent(LembreteContaEmail::class, fn ($mail) => $mail->hasTo($confirmado->email)
            && !$mail->hasTo($pendente->email)
            && !$mail->hasTo($voltouParaOficina->email));
    }

    public function test_nao_marca_como_avisado_quando_ninguem_pode_receber(): void
    {
        Mail::fake();
        [$usuario] = $this->autenticarConta('pessoa');
        $usuario->forceFill(['email_verified_at' => null])->save();
        $veiculo = $this->veiculo('AAA1B23');

        $this->artisan('contas:alertar-vencimentos');

        Mail::assertNothingSent();
        $this->assertNull($veiculo->fresh()->makeVisible('ipva_alertado_ano')->ipva_alertado_ano);
    }

    public function test_cada_conta_recebe_so_os_proprios_veiculos(): void
    {
        Carbon::setTestNow('2026-01-05'); // o IPVA vence em 31/01, 26 dias
        Mail::fake();
        [$donoA] = $this->autenticarConta('pessoa');
        $this->veiculo('AAA1B23', ['apelido' => 'Carro da conta A']);
        [$donoB] = $this->autenticarConta('pessoa');
        $this->veiculo('BBB1C23', ['apelido' => 'Carro da conta B']);

        $this->artisan('contas:alertar-vencimentos');

        Mail::assertSent(LembreteContaEmail::class, 2);
        Mail::assertSent(LembreteContaEmail::class, fn ($mail) => $mail->hasTo($donoA->email)
            && collect($mail->itens)->pluck('veiculo')->unique()->all() === ['Carro da conta A']);
        Mail::assertSent(LembreteContaEmail::class, fn ($mail) => $mail->hasTo($donoB->email)
            && collect($mail->itens)->pluck('veiculo')->unique()->all() === ['Carro da conta B']);
    }

    public function test_dry_run_nao_envia_nem_marca(): void
    {
        Carbon::setTestNow('2026-01-05'); // o IPVA vence em 31/01, 26 dias
        Mail::fake();
        $this->autenticarConta('pessoa');
        $veiculo = $this->veiculo('AAA1B23');

        $this->artisan('contas:alertar-vencimentos', ['--dry-run' => true])
            ->expectsOutputToContain('[teste]')
            ->assertExitCode(0);

        Mail::assertNothingSent();
        $this->assertNull($veiculo->fresh()->makeVisible('ipva_alertado_ano')->ipva_alertado_ano);
    }

    public function test_o_email_renderiza_com_os_dados_e_o_aviso_de_estimativa(): void
    {
        Carbon::setTestNow('2026-01-05'); // o IPVA vence em 31/01, 26 dias
        [$usuario] = $this->autenticarConta('frota');
        $usuario->conta->update(['nome' => 'Transportes Silva']);
        $this->veiculo('AAA1B23', ['apelido' => 'Van da entrega', 'fipe_valor' => 40000]);

        $itens = app(\App\Services\LembretesContaService::class)->pendentes();
        $html = (new LembreteContaEmail($usuario->conta->fresh(), $itens))->render();

        $this->assertStringContainsString('Transportes Silva', $html);
        $this->assertStringContainsString('Van da entrega', $html);
        $this->assertStringContainsString('IPVA', $html);
        $this->assertStringContainsString('31/01/2026', $html);
        $this->assertStringContainsString('estimativas', $html);
        $this->assertStringContainsString('Configurações', $html);
    }

    public function test_revisao_no_cadastro_do_veiculo_e_no_resumo(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');

        $this->postJson('/api/conta/veiculos', [
            'placa' => 'EEE1F25', 'marca' => 'Fiat', 'modelo' => 'Uno', 'ano' => 2018,
            'revisao_prevista_em' => '2026-03-20',
        ], $headers)
            ->assertStatus(201)
            ->assertJsonPath('revisao_prevista_em', '2026-03-20')
            ->assertJsonMissingPath('ipva_alertado_ano');

        $vencimentos = $this->getJson('/api/conta/resumo', $headers)->json('vencimentos');

        $this->assertSame('revisao', $vencimentos[0]['tipo']);
        $this->assertSame(15, $vencimentos[0]['dias']);

        $this->postJson('/api/conta/veiculos', [
            'placa' => 'XXX1Y29', 'marca' => 'Fiat', 'modelo' => 'Uno', 'ano' => 2018, 'revisao_prevista_em' => 'ontem-ou-sei-la',
        ], $headers)->assertStatus(422)->assertJsonValidationErrors('revisao_prevista_em');
    }

    public function test_preferencias_da_conta_pela_api(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');

        $this->getJson('/api/conta/preferencias', $headers)->assertJsonPath('lembretes_email', true);

        $this->putJson('/api/conta/preferencias', ['lembretes_email' => false], $headers)
            ->assertStatus(200)->assertJsonPath('lembretes_email', false);
        $this->getJson('/api/conta/preferencias', $headers)->assertJsonPath('lembretes_email', false);

        $this->putJson('/api/conta/preferencias', ['lembretes_email' => 'talvez'], $headers)
            ->assertStatus(422)->assertJsonValidationErrors('lembretes_email');

        [, $headersOficina] = $this->autenticar();
        $this->getJson('/api/conta/preferencias', $headersOficina)->assertStatus(403);
    }

    public function test_conta_nova_ja_nasce_com_os_lembretes_ligados(): void
    {
        $conta = Conta::create(['tipo' => 'pessoa', 'nome' => 'Nova']);

        $this->assertTrue($conta->fresh()->lembretes_email);
    }
}
