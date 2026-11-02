<?php

namespace Tests\Feature;

use App\Mail\AlertaIpvaEmail;
use App\Mail\AlertaLicenciamentoEmail;
use App\Mail\AlertaManutencaoEmail;
use App\Models\Cliente;
use App\Models\Manutencao;
use App\Models\Veiculo;
use App\Support\FusoDaFrota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

/**
 * Os alertas da oficina contam os dias de Brasília. Às 22h em Brasília o servidor (UTC) já está no dia
 * seguinte: o que vence hoje não pode aparecer como atrasado.
 */
class AlertasOficinaDataTest extends TestCase
{
    use AutenticaUsuario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->autenticar();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function veiculoComCliente(): Veiculo
    {
        $cliente = Cliente::factory()->create(['email' => 'cliente@exemplo.com']);

        return Veiculo::factory()->create(['cliente_id' => $cliente->id]);
    }

    /** Põe o relógio às 22h desse dia, em Brasília. */
    private function as22hDeBrasilia(string $dia): void
    {
        Carbon::setTestNow(Carbon::parse("{$dia} 22:00:00", FusoDaFrota::NOME));
    }

    public function test_ipva_que_vence_hoje_as_22h_em_brasilia_nao_aparece_como_atrasado(): void
    {
        Mail::fake();
        $veiculo = $this->veiculoComCliente();
        $vencimento = $veiculo->proximo_vencimento_ipva;
        $this->assertNotNull($vencimento);

        $this->as22hDeBrasilia($vencimento);
        $this->artisan('veiculos:alertar-ipva')->assertSuccessful();

        Mail::assertQueued(AlertaIpvaEmail::class, fn (AlertaIpvaEmail $mail) => $mail->atrasado === false);
    }

    public function test_licenciamento_que_vence_hoje_as_22h_em_brasilia_nao_aparece_como_atrasado(): void
    {
        Mail::fake();
        $veiculo = $this->veiculoComCliente();
        $vencimento = $veiculo->proximo_vencimento_licenciamento;
        $this->assertNotNull($vencimento);

        $this->as22hDeBrasilia($vencimento);
        $this->artisan('veiculos:alertar-licenciamento')->assertSuccessful();

        Mail::assertQueued(AlertaLicenciamentoEmail::class, fn (AlertaLicenciamentoEmail $mail) => $mail->atrasado === false);
    }

    public function test_manutencao_que_vence_hoje_as_22h_em_brasilia_nao_aparece_como_atrasada(): void
    {
        Mail::fake();
        $manutencao = Manutencao::factory()->create([
            'veiculo_id' => $this->veiculoComCliente()->id,
            'proxima_data' => '2027-01-31',
        ]);

        $this->as22hDeBrasilia('2027-01-31');
        $this->artisan('manutencoes:alertar')->assertSuccessful();

        Mail::assertQueued(AlertaManutencaoEmail::class, fn (AlertaManutencaoEmail $mail) => $mail->manutencao->is($manutencao) && $mail->atrasada === false);
    }
}
