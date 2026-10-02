<?php

namespace Tests\Feature;

use App\Models\Veiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class BackfillFipeHistoricoTest extends TestCase
{
    use RefreshDatabase;
    use AutenticaUsuario;

    public function test_backfill_cria_primeira_linha_so_para_veiculos_com_valor_e_sem_historico(): void
    {
        $this->autenticar();
        $comValor = Veiculo::factory()->create(['fipe_valor' => 50000, 'fipe_consultado_em' => now()->subDays(5)]);
        $semValor = Veiculo::factory()->create();

        $migracao = require base_path('database/migrations/2026_10_02_000000_backfill_fipe_historicos.php');
        $migracao->up();
        $migracao->up();

        $this->assertSame(1, DB::table('fipe_historicos')->count());
        $this->assertDatabaseHas('fipe_historicos', ['veiculo_id' => $comValor->id, 'valor' => 50000]);
        $this->assertDatabaseMissing('fipe_historicos', ['veiculo_id' => $semValor->id]);
    }
}
