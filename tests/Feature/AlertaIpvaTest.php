<?php

namespace Tests\Feature;

use App\Mail\AlertaIpvaEmail;
use App\Models\Cliente;
use App\Models\Oficina;
use App\Models\Veiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class AlertaIpvaTest extends TestCase
{
    use RefreshDatabase;
    use AutenticaUsuario;

    // O IPVA vence sempre em 31/01, qualquer que seja o final da placa. Com a
    // data fixa em 05/01 ele está próximo; em 05/03 o próximo é só em 2027.
    private const FINAL_PROXIMO = 3;
    private const FINAL_DISTANTE = 5;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-01-05');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function criarVeiculo(int $final, ?string $email = 'cliente@exemplo.com'): Veiculo
    {
        $cliente = Cliente::factory()->create(['email' => $email]);
        $prefixo = strtoupper(fake()->unique()->bothify('???#?#'));

        return Veiculo::factory()->create(['cliente_id' => $cliente->id, 'placa' => "{$prefixo}{$final}"]);
    }

    public function test_calcula_vencimento_no_ano_atual_e_no_proximo(): void
    {
        $this->autenticar();

        $this->assertSame('2026-01-31', $this->criarVeiculo(3)->proximo_vencimento_ipva);
        $this->assertSame('2026-01-31', $this->criarVeiculo(7)->proximo_vencimento_ipva);

        // Passou de janeiro: o próximo vencimento é o do ano seguinte.
        Carbon::setTestNow('2026-03-05');
        $this->assertSame('2027-01-31', $this->criarVeiculo(1)->proximo_vencimento_ipva);
    }

    public function test_envia_alerta_quando_vencimento_esta_proximo(): void
    {
        Mail::fake();
        $this->autenticar();
        $veiculo = $this->criarVeiculo(self::FINAL_PROXIMO);

        $this->artisan('veiculos:alertar-ipva');

        Mail::assertSent(AlertaIpvaEmail::class, fn ($m) => $m->veiculo->id === $veiculo->id && $m->atrasado === false);
    }

    public function test_nao_envia_quando_distante(): void
    {
        Carbon::setTestNow('2026-03-05');
        Mail::fake();
        $this->autenticar();
        $this->criarVeiculo(self::FINAL_DISTANTE);

        $this->artisan('veiculos:alertar-ipva');

        Mail::assertNothingSent();
    }

    public function test_nao_envia_sem_email_do_cliente(): void
    {
        Mail::fake();
        $this->autenticar();
        $this->criarVeiculo(self::FINAL_PROXIMO, null);

        $this->artisan('veiculos:alertar-ipva');

        Mail::assertNothingSent();
    }

    public function test_nao_envia_duas_vezes_no_mesmo_ciclo(): void
    {
        Mail::fake();
        $this->autenticar();
        $this->criarVeiculo(self::FINAL_PROXIMO);

        $this->artisan('veiculos:alertar-ipva');
        $this->artisan('veiculos:alertar-ipva');

        Mail::assertSentCount(1);
    }

    public function test_reenvia_em_outro_ciclo(): void
    {
        Mail::fake();
        $this->autenticar();
        $veiculo = $this->criarVeiculo(self::FINAL_PROXIMO);

        $this->artisan('veiculos:alertar-ipva');
        $veiculo->ipva_alertado_ano = $veiculo->fresh()->ipva_alertado_ano - 1;
        $veiculo->saveQuietly();
        $this->artisan('veiculos:alertar-ipva');

        Mail::assertSentCount(2);
    }

    public function test_processa_todas_as_oficinas(): void
    {
        Mail::fake();
        $this->autenticar();
        $this->criarVeiculo(self::FINAL_PROXIMO);

        app()->instance('oficina.atual', Oficina::factory()->create()->id);
        $this->criarVeiculo(self::FINAL_PROXIMO);

        $this->artisan('veiculos:alertar-ipva');

        Mail::assertSentCount(2);
    }

    public function test_email_renderiza_com_nome_do_veiculo_e_data_formatada(): void
    {
        $this->autenticar();
        $veiculo = $this->criarVeiculo(self::FINAL_PROXIMO);

        $html = (new AlertaIpvaEmail($veiculo->load('cliente'), '2026-01-31', false))->render();

        $this->assertStringContainsString('31/01/2026', $html);
        $this->assertStringContainsString("{$veiculo->marca} {$veiculo->modelo}", $html);
        $this->assertStringContainsString('estimativa', $html);
    }
}
