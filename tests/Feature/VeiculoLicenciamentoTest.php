<?php

namespace Tests\Feature;

use App\Models\Veiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class VeiculoLicenciamentoTest extends TestCase
{
    use RefreshDatabase;
    use AutenticaUsuario;

    public function test_calcula_vencimento_no_ano_atual_quando_mes_ainda_nao_passou(): void
    {
        Carbon::setTestNow('2026-01-10');
        $this->autenticar();

        // Final 1 -> Março (config/licenciamento.php), ainda não passou em janeiro.
        $veiculo = Veiculo::factory()->create(['placa' => 'ABC1D01']);

        $this->assertSame('2026-03-31', $veiculo->proximo_vencimento_licenciamento);

        Carbon::setTestNow();
    }

    public function test_calcula_vencimento_no_proximo_ano_quando_mes_ja_passou(): void
    {
        Carbon::setTestNow('2026-12-10');
        $this->autenticar();

        // Final 1 -> Março, já passou em dezembro: vai para o ano seguinte.
        $veiculo = Veiculo::factory()->create(['placa' => 'ABC1D01']);

        $this->assertSame('2027-03-31', $veiculo->proximo_vencimento_licenciamento);

        Carbon::setTestNow();
    }

    public function test_veiculo_com_placa_terminando_em_zero(): void
    {
        Carbon::setTestNow('2026-01-10');
        $this->autenticar();

        // Final 0 -> Dezembro.
        $veiculo = Veiculo::factory()->create(['placa' => 'ABC1D00']);

        $this->assertSame('2026-12-31', $veiculo->proximo_vencimento_licenciamento);

        Carbon::setTestNow();
    }
}
