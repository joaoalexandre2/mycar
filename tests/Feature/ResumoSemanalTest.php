<?php

namespace Tests\Feature;

use App\Mail\ResumoSemanalEmail;
use App\Models\Cliente;
use App\Models\Manutencao;
use App\Models\Oficina;
use App\Models\User;
use App\Models\Veiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class ResumoSemanalTest extends TestCase
{
    use RefreshDatabase;
    use AutenticaUsuario;

    // O IPVA vence sempre em 31/01. Com a data fixa em 05/03/2026: final de placa 3
    // tem licenciamento em 31/05 (fora da janela) e final 1 em 31/03 (26 dias);
    // o IPVA só entra na janela nos testes que usam 05/01.
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

    /**
     * @param string|false|null $email false = gera um e-mail único; null = cliente sem e-mail
     */
    private function criarVeiculo(string $placa, string|false|null $email = false, string $nome = 'Cliente Teste'): Veiculo
    {
        $cliente = Cliente::factory()->create([
            'nome' => $nome,
            'email' => $email === false ? fake()->unique()->safeEmail() : $email,
            'telefone' => fake()->unique()->numerify('(51) 9####-####'),
        ]);

        return Veiculo::factory()->create([
            'cliente_id' => $cliente->id,
            'placa' => $placa,
            'marca' => 'Fiat',
            'modelo' => 'Uno',
        ]);
    }

    private function criarManutencao(Veiculo $veiculo, ?string $proximaData, string $tipo = 'Troca de óleo', string $feitaEm = '2025-12-01'): Manutencao
    {
        return Manutencao::factory()->create([
            'veiculo_id' => $veiculo->id,
            'tipo' => $tipo,
            'data_manutencao' => $feitaEm,
            'proxima_data' => $proximaData,
        ]);
    }

    /** Placa que termina em um dígito sem entrar em nenhum calendário (final 2: IPVA fev, lic. abr/2026). */
    private function placaNeutra(string $prefixo = 'AAA1B2'): string
    {
        return $prefixo . '2';
    }

    public function test_envia_o_resumo_para_o_usuario_da_oficina(): void
    {
        Mail::fake();
        [$usuario] = $this->autenticar();

        $veiculo = $this->criarVeiculo($this->placaNeutra(), null, 'Cliente Sem Email');
        $this->criarManutencao($veiculo, '2026-02-20', 'Freios');
        $this->criarManutencao($veiculo, '2026-03-20', 'Alinhamento', '2026-01-10');

        $this->artisan('oficinas:resumo-semanal')->assertExitCode(0);

        Mail::assertSent(ResumoSemanalEmail::class, function (ResumoSemanalEmail $mail) use ($usuario) {
            $resumo = $mail->resumo;

            return $mail->hasTo($usuario->email)
                && $resumo['manutencoes_atrasadas']['total'] === 1
                && $resumo['manutencoes_atrasadas']['itens'][0]['descricao'] === 'Freios'
                && $resumo['manutencoes_atrasadas']['itens'][0]['dias'] === -13
                && $resumo['manutencoes_proximas']['total'] === 1
                && $resumo['manutencoes_proximas']['itens'][0]['descricao'] === 'Alinhamento'
                && $resumo['manutencoes_proximas']['itens'][0]['dias'] === 15
                && $resumo['sem_email'] === 1
                && $resumo['total'] === 2;
        });
    }

    public function test_marca_quem_nao_pode_ser_avisado_pelo_sistema_por_falta_de_email(): void
    {
        Mail::fake();
        $this->autenticar();

        $comEmail = $this->criarVeiculo($this->placaNeutra('BBB1C2'), 'tem@exemplo.com', 'Com Email');
        $semEmail = $this->criarVeiculo($this->placaNeutra('CCC1D2'), null, 'Sem Email');
        $this->criarManutencao($comEmail, '2026-03-10');
        $this->criarManutencao($semEmail, '2026-03-11');

        $this->artisan('oficinas:resumo-semanal');

        Mail::assertSent(ResumoSemanalEmail::class, function (ResumoSemanalEmail $mail) {
            $itens = collect($mail->resumo['manutencoes_proximas']['itens'])->keyBy('cliente_nome');

            return $itens['Com Email']['cliente_tem_email'] === true
                && $itens['Sem Email']['cliente_tem_email'] === false
                && $mail->resumo['sem_email'] === 1;
        });
    }

    public function test_inclui_licenciamento_a_vencer_pela_estimativa_da_placa(): void
    {
        Mail::fake();
        $this->autenticar();

        $this->criarVeiculo('DDD1E23', 'a@exemplo.com', 'Final Tres');   // IPVA só em jan/2027; lic. 31/05 (fora)
        $this->criarVeiculo('EEE1F21', 'b@exemplo.com', 'Final Um');     // licenciamento 31/03
        $this->criarVeiculo('FFF1G25', 'c@exemplo.com', 'Final Cinco');  // nada na janela

        $this->artisan('oficinas:resumo-semanal');

        Mail::assertSent(ResumoSemanalEmail::class, function (ResumoSemanalEmail $mail) {
            $resumo = $mail->resumo;

            return $resumo['ipva']['total'] === 0
                && $resumo['licenciamento']['total'] === 1
                && $resumo['licenciamento']['itens'][0]['cliente_nome'] === 'Final Um'
                && $resumo['licenciamento']['itens'][0]['data'] === '31/03/2026'
                && $resumo['total'] === 1;
        });
    }

    public function test_inclui_o_ipva_de_janeiro_para_todos_os_veiculos(): void
    {
        Carbon::setTestNow('2026-01-05');
        Mail::fake();
        $this->autenticar();

        $this->criarVeiculo('DDD1E23', 'a@exemplo.com', 'Final Tres');
        $this->criarVeiculo('FFF1G25', 'c@exemplo.com', 'Final Cinco');

        $this->artisan('oficinas:resumo-semanal');

        Mail::assertSent(ResumoSemanalEmail::class, function (ResumoSemanalEmail $mail) {
            $resumo = $mail->resumo;

            return $resumo['ipva']['total'] === 2
                && $resumo['ipva']['itens'][0]['data'] === '31/01/2026'
                && $resumo['licenciamento']['total'] === 0;
        });
    }

    public function test_ignora_manutencao_que_ja_foi_refeita(): void
    {
        Mail::fake();
        $this->autenticar();

        $veiculo = $this->criarVeiculo($this->placaNeutra());
        // A troca de óleo antiga tinha próxima data vencida, mas foi refeita depois.
        $this->criarManutencao($veiculo, '2026-02-01', 'Troca de óleo', '2025-08-01');
        $this->criarManutencao($veiculo, '2026-09-01', 'troca de óleo', '2026-02-10');
        // Outro tipo continua pendente.
        $this->criarManutencao($veiculo, '2026-02-15', 'Freios', '2025-08-15');

        $this->artisan('oficinas:resumo-semanal');

        Mail::assertSent(ResumoSemanalEmail::class, function (ResumoSemanalEmail $mail) {
            $atrasadas = collect($mail->resumo['manutencoes_atrasadas']['itens'])->pluck('descricao')->all();

            return $atrasadas === ['Freios'] && $mail->resumo['manutencoes_proximas']['total'] === 0;
        });
    }

    public function test_nao_envia_quando_nao_ha_nada_a_reportar(): void
    {
        Mail::fake();
        $this->autenticar();

        $veiculo = $this->criarVeiculo($this->placaNeutra());
        $this->criarManutencao($veiculo, '2026-12-01'); // longe
        $this->criarManutencao($veiculo, null, 'Revisão'); // sem próxima data

        $this->artisan('oficinas:resumo-semanal')->assertExitCode(0);

        Mail::assertNothingSent();
    }

    public function test_respeita_a_oficina_que_desligou_o_resumo(): void
    {
        Mail::fake();
        [$usuario] = $this->autenticar();
        $usuario->oficina->update(['resumo_semanal' => false]);

        $this->criarManutencao($this->criarVeiculo($this->placaNeutra()), '2026-02-20');

        $this->artisan('oficinas:resumo-semanal');

        Mail::assertNothingSent();
    }

    public function test_so_envia_para_usuarios_com_email_confirmado(): void
    {
        Mail::fake();
        [$confirmado] = $this->autenticar();
        $pendente = User::factory()->unverified()->create(['oficina_id' => $confirmado->oficina_id]);

        $this->criarManutencao($this->criarVeiculo($this->placaNeutra()), '2026-02-20');

        $this->artisan('oficinas:resumo-semanal');

        Mail::assertSent(ResumoSemanalEmail::class, fn (ResumoSemanalEmail $mail) => $mail->hasTo($confirmado->email) && !$mail->hasTo($pendente->email));
    }

    public function test_nao_envia_se_a_oficina_nao_tem_nenhum_usuario_confirmado(): void
    {
        Mail::fake();
        [$usuario] = $this->autenticar();
        $usuario->forceFill(['email_verified_at' => null])->save();

        $this->criarManutencao($this->criarVeiculo($this->placaNeutra()), '2026-02-20');

        $this->artisan('oficinas:resumo-semanal');

        Mail::assertNothingSent();
    }

    public function test_cada_oficina_recebe_so_os_proprios_dados(): void
    {
        Mail::fake();

        [$usuarioA] = $this->autenticar();
        $this->criarManutencao($this->criarVeiculo($this->placaNeutra('GGG1H2'), 'a@exemplo.com', 'Cliente da Oficina A'), '2026-02-20');

        [$usuarioB] = $this->autenticar();
        $this->criarManutencao($this->criarVeiculo($this->placaNeutra('HHH1I2'), 'b@exemplo.com', 'Cliente da Oficina B'), '2026-02-21');

        $this->artisan('oficinas:resumo-semanal');

        Mail::assertSent(ResumoSemanalEmail::class, 2);

        Mail::assertSent(ResumoSemanalEmail::class, function (ResumoSemanalEmail $mail) use ($usuarioA) {
            if (!$mail->hasTo($usuarioA->email)) {
                return false;
            }

            $clientes = collect($mail->resumo['manutencoes_atrasadas']['itens'])->pluck('cliente_nome')->all();

            return $clientes === ['Cliente da Oficina A'];
        });

        Mail::assertSent(ResumoSemanalEmail::class, function (ResumoSemanalEmail $mail) use ($usuarioB) {
            if (!$mail->hasTo($usuarioB->email)) {
                return false;
            }

            $clientes = collect($mail->resumo['manutencoes_atrasadas']['itens'])->pluck('cliente_nome')->all();

            return $clientes === ['Cliente da Oficina B'];
        });
    }

    public function test_limita_cada_secao_e_informa_quantos_ficaram_de_fora(): void
    {
        Mail::fake();
        $this->autenticar();

        $veiculo = $this->criarVeiculo($this->placaNeutra());
        foreach (range(1, 18) as $i) {
            $this->criarManutencao($veiculo, '2026-02-01', "Servico {$i}", '2025-01-01');
        }

        $this->artisan('oficinas:resumo-semanal');

        Mail::assertSent(ResumoSemanalEmail::class, function (ResumoSemanalEmail $mail) {
            $secao = $mail->resumo['manutencoes_atrasadas'];

            return $secao['total'] === 18 && count($secao['itens']) === 15 && $secao['restantes'] === 3;
        });
    }

    public function test_dry_run_nao_envia_nada(): void
    {
        Mail::fake();
        $this->autenticar();
        $this->criarManutencao($this->criarVeiculo($this->placaNeutra()), '2026-02-20');

        $this->artisan('oficinas:resumo-semanal', ['--dry-run' => true])
            ->expectsOutputToContain('[teste]')
            ->assertExitCode(0);

        Mail::assertNothingSent();
    }

    public function test_opcao_oficina_envia_so_para_a_escolhida(): void
    {
        Mail::fake();

        [$usuarioA] = $this->autenticar();
        $this->criarManutencao($this->criarVeiculo($this->placaNeutra('III1J2')), '2026-02-20');

        [$usuarioB] = $this->autenticar();
        $this->criarManutencao($this->criarVeiculo($this->placaNeutra('JJJ1K2')), '2026-02-20');

        $this->artisan('oficinas:resumo-semanal', ['--oficina' => $usuarioB->oficina_id]);

        Mail::assertSent(ResumoSemanalEmail::class, 1);
        Mail::assertSent(ResumoSemanalEmail::class, fn (ResumoSemanalEmail $mail) => $mail->hasTo($usuarioB->email));
        Mail::assertNotSent(ResumoSemanalEmail::class, fn (ResumoSemanalEmail $mail) => $mail->hasTo($usuarioA->email));
    }

    public function test_o_email_renderiza_com_os_dados_e_o_aviso_de_cliente_sem_email(): void
    {
        [$usuario] = $this->autenticar();
        $usuario->oficina->update(['nome' => 'Oficina do Teste']);

        $veiculo = $this->criarVeiculo($this->placaNeutra(), null, 'Fulano de Tal');
        $this->criarManutencao($veiculo, '2026-02-20', 'Freios');

        $resumo = app(\App\Services\ResumoSemanalService::class)->montar();
        $html = (new ResumoSemanalEmail($usuario->oficina->fresh(), $resumo))->render();

        $this->assertStringContainsString('Oficina do Teste', $html);
        $this->assertStringContainsString('Manutenções atrasadas', $html);
        $this->assertStringContainsString('Freios', $html);
        $this->assertStringContainsString('Fulano de Tal', $html);
        $this->assertStringContainsString('sem e-mail', $html);
        $this->assertStringContainsString('13 dia(s) de atraso', $html);
        $this->assertStringContainsString('Configurações', $html);
    }

    public function test_alternar_o_resumo_pela_api_de_configuracoes(): void
    {
        [, $headers] = $this->autenticar();

        // Padrão: ligado.
        $this->getJson('/api/oficina', $headers)->assertJsonPath('resumo_semanal', true);

        $this->putJson('/api/oficina', ['nome' => 'Oficina X', 'resumo_semanal' => false], $headers)
            ->assertStatus(200)
            ->assertJsonPath('resumo_semanal', false);

        $this->getJson('/api/oficina', $headers)->assertJsonPath('resumo_semanal', false);

        // Cliente antigo, que não envia o campo, não religa nem desliga nada.
        $this->putJson('/api/oficina', ['nome' => 'Oficina Y'], $headers)
            ->assertJsonPath('nome', 'Oficina Y')
            ->assertJsonPath('resumo_semanal', false);

        $this->putJson('/api/oficina', ['nome' => 'Oficina Y', 'resumo_semanal' => 'talvez'], $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors('resumo_semanal');
    }

    public function test_oficina_nova_ja_nasce_com_o_resumo_ligado(): void
    {
        $oficina = Oficina::factory()->create();

        $this->assertTrue($oficina->fresh()->resumo_semanal);
    }
}
