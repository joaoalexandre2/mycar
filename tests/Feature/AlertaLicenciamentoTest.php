<?php

namespace Tests\Feature;

use App\Mail\AlertaLicenciamentoEmail;
use App\Models\Cliente;
use App\Models\Oficina;
use App\Models\Veiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class AlertaLicenciamentoTest extends TestCase
{
    use RefreshDatabase;
    use AutenticaUsuario;

    protected function setUp(): void
    {
        parent::setUp();

        // Data fixa: garante que sempre exista um final de placa "próximo"
        // (março, a 26 dias) e um "distante" (abril, a 56 dias), não
        // importa em que dia real o teste rode — o calendário tem meses
        // sem cobertura (ex.: tabela não tem janeiro/fevereiro/agosto),
        // então perto de certas datas reais nenhum final estaria "próximo".
        Carbon::setTestNow('2026-03-05');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function criarVeiculo(int $finalPlaca, ?string $email = 'cliente@exemplo.com'): Veiculo
    {
        $cliente = Cliente::factory()->create(['email' => $email]);
        $prefixo = strtoupper(fake()->unique()->bothify('???#?#'));

        return Veiculo::factory()->create([
            'cliente_id' => $cliente->id,
            'placa' => "{$prefixo}{$finalPlaca}",
        ]);
    }

    /**
     * Calcula, pela mesma regra do model (config/licenciamento.php), a
     * próxima data de vencimento para um final de placa — para achar um
     * final cujo vencimento caia dentro ou fora da janela de 30 dias,
     * não importa em que dia o teste rode.
     */
    private function proximoVencimentoParaFinal(int $final): \Illuminate\Support\Carbon
    {
        $mes = config("licenciamento.meses_por_final_placa.{$final}");
        $vencimento = now()->setDate(now()->year, $mes, 1)->endOfMonth();

        return $vencimento->isPast() ? $vencimento->addYear() : $vencimento;
    }

    private function finalComVencimentoProximo(): int
    {
        foreach (range(0, 9) as $final) {
            if ($this->proximoVencimentoParaFinal($final)->lte(now()->addDays(30))) {
                return $final;
            }
        }

        $this->fail('Nenhum final de placa com vencimento dentro de 30 dias para a data atual do teste.');
    }

    private function finalComVencimentoDistante(): int
    {
        foreach (range(0, 9) as $final) {
            if ($this->proximoVencimentoParaFinal($final)->gt(now()->addDays(30))) {
                return $final;
            }
        }

        $this->fail('Nenhum final de placa com vencimento distante para a data atual do teste.');
    }

    public function test_envia_alerta_quando_vencimento_esta_proximo(): void
    {
        Mail::fake();
        $this->autenticar();
        $veiculo = $this->criarVeiculo($this->finalComVencimentoProximo());

        $this->artisan('veiculos:alertar-licenciamento');

        Mail::assertSent(AlertaLicenciamentoEmail::class, fn ($mail) => $mail->veiculo->id === $veiculo->id);
    }

    public function test_nao_envia_quando_vencimento_esta_distante(): void
    {
        Mail::fake();
        $this->autenticar();
        $this->criarVeiculo($this->finalComVencimentoDistante());

        $this->artisan('veiculos:alertar-licenciamento');

        Mail::assertNothingSent();
    }

    public function test_nao_envia_sem_email_do_cliente(): void
    {
        Mail::fake();
        $this->autenticar();
        $this->criarVeiculo($this->finalComVencimentoProximo(), null);

        $this->artisan('veiculos:alertar-licenciamento');

        Mail::assertNothingSent();
    }

    public function test_nao_envia_duas_vezes_no_mesmo_ciclo(): void
    {
        Mail::fake();
        $this->autenticar();
        $this->criarVeiculo($this->finalComVencimentoProximo());

        $this->artisan('veiculos:alertar-licenciamento');
        $this->artisan('veiculos:alertar-licenciamento');

        Mail::assertSentCount(1);
    }

    public function test_reenvia_em_outro_ciclo(): void
    {
        Mail::fake();
        $this->autenticar();
        $veiculo = $this->criarVeiculo($this->finalComVencimentoProximo());

        $this->artisan('veiculos:alertar-licenciamento');
        // Simula a virada de ciclo (ano) sem esperar um ano de verdade.
        $veiculo->licenciamento_alertado_ano = $veiculo->fresh()->licenciamento_alertado_ano - 1;
        $veiculo->saveQuietly();
        $this->artisan('veiculos:alertar-licenciamento');

        Mail::assertSentCount(2);
    }

    public function test_processa_todas_as_oficinas(): void
    {
        Mail::fake();
        $this->autenticar();
        $veiculoA = $this->criarVeiculo($this->finalComVencimentoProximo());

        $outraOficina = Oficina::factory()->create();
        app()->instance('oficina.atual', $outraOficina->id);
        $veiculoB = $this->criarVeiculo($this->finalComVencimentoProximo());

        $this->artisan('veiculos:alertar-licenciamento');

        Mail::assertSentCount(2);
        Mail::assertSent(AlertaLicenciamentoEmail::class, fn ($mail) => $mail->veiculo->id === $veiculoA->id);
        Mail::assertSent(AlertaLicenciamentoEmail::class, fn ($mail) => $mail->veiculo->id === $veiculoB->id);
    }
}
