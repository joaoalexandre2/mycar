<?php

namespace Tests\Feature;

use App\Mail\AlertaManutencaoEmail;
use App\Models\Cliente;
use App\Models\Manutencao;
use App\Models\Oficina;
use App\Models\Veiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class AlertaManutencaoTest extends TestCase
{
    use RefreshDatabase;
    use AutenticaUsuario;

    private function criarManutencao(?string $proximaData = null, ?string $email = 'cliente@exemplo.com'): Manutencao
    {
        $cliente = Cliente::factory()->create(['email' => $email]);
        $veiculo = Veiculo::factory()->create(['cliente_id' => $cliente->id]);

        return Manutencao::factory()->create([
            'veiculo_id' => $veiculo->id,
            'proxima_data' => $proximaData,
        ]);
    }

    public function test_envia_alerta_para_manutencao_atrasada(): void
    {
        Mail::fake();
        $this->autenticar();
        $manutencao = $this->criarManutencao(now()->subDays(3)->toDateString());

        $this->artisan('manutencoes:alertar');

        Mail::assertSent(AlertaManutencaoEmail::class, function ($mail) use ($manutencao) {
            return $mail->manutencao->id === $manutencao->id && $mail->atrasada === true;
        });
        $this->assertNotNull($manutencao->fresh()->alertado_em);
    }

    public function test_envia_alerta_para_manutencao_proxima_dentro_de_30_dias(): void
    {
        Mail::fake();
        $this->autenticar();
        $manutencao = $this->criarManutencao(now()->addDays(15)->toDateString());

        $this->artisan('manutencoes:alertar');

        Mail::assertSent(AlertaManutencaoEmail::class, function ($mail) use ($manutencao) {
            return $mail->manutencao->id === $manutencao->id && $mail->atrasada === false;
        });
    }

    public function test_nao_envia_para_manutencao_em_dia(): void
    {
        Mail::fake();
        $this->autenticar();
        $this->criarManutencao(now()->addDays(60)->toDateString());

        $this->artisan('manutencoes:alertar');

        Mail::assertNothingSent();
    }

    public function test_nao_envia_sem_proxima_data(): void
    {
        Mail::fake();
        $this->autenticar();
        $this->criarManutencao(null);

        $this->artisan('manutencoes:alertar');

        Mail::assertNothingSent();
    }

    public function test_nao_envia_sem_email_do_cliente(): void
    {
        Mail::fake();
        $this->autenticar();
        $this->criarManutencao(now()->addDays(5)->toDateString(), null);

        $this->artisan('manutencoes:alertar');

        Mail::assertNothingSent();
    }

    public function test_nao_envia_duas_vezes_para_a_mesma_manutencao(): void
    {
        Mail::fake();
        $this->autenticar();
        $this->criarManutencao(now()->addDays(5)->toDateString());

        $this->artisan('manutencoes:alertar');
        $this->artisan('manutencoes:alertar');

        Mail::assertSentCount(1);
    }

    public function test_reenvia_quando_proxima_data_e_alterada_apos_alerta(): void
    {
        Mail::fake();
        $this->autenticar();
        $manutencao = $this->criarManutencao(now()->addDays(5)->toDateString());

        $this->artisan('manutencoes:alertar');
        // Recarrega antes de atualizar, como o repositório faz de verdade
        // (Manutencao::find($id) fresco a cada request), para não escrever
        // por cima do alertado_em já setado por outra instância do model.
        $manutencao->fresh()->update(['proxima_data' => now()->addDays(10)->toDateString()]);
        $this->artisan('manutencoes:alertar');

        Mail::assertSentCount(2);
    }

    public function test_processa_todas_as_oficinas(): void
    {
        Mail::fake();
        $this->autenticar();
        $manutencaoA = $this->criarManutencao(now()->addDays(5)->toDateString());

        $outraOficina = Oficina::factory()->create();
        app()->instance('oficina.atual', $outraOficina->id);
        $manutencaoB = $this->criarManutencao(now()->addDays(5)->toDateString());

        $this->artisan('manutencoes:alertar');

        Mail::assertSentCount(2);
        Mail::assertSent(AlertaManutencaoEmail::class, fn ($mail) => $mail->manutencao->id === $manutencaoA->id);
        Mail::assertSent(AlertaManutencaoEmail::class, fn ($mail) => $mail->manutencao->id === $manutencaoB->id);
    }
}
