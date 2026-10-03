<?php

namespace Tests\Feature;

use App\Models\Oficina;
use App\Models\Veiculo;
use App\Services\Catalogo\CatalogoTecnico;
use App\Services\Catalogo\CatalogoTecnicoService;
use App\Services\Catalogo\FichaManualCatalogo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class FichaTecnicaTest extends TestCase
{
    use RefreshDatabase;
    use AutenticaUsuario;

    public function test_exige_autenticacao(): void
    {
        $this->getJson('/api/veiculos/1/ficha-tecnica')->assertStatus(401);
        $this->putJson('/api/veiculos/1/ficha-tecnica', [])->assertStatus(401);
    }

    public function test_veiculo_inexistente_retorna_404(): void
    {
        [, $headers] = $this->autenticar();

        $this->getJson('/api/veiculos/999/ficha-tecnica', $headers)->assertStatus(404);
        $this->putJson('/api/veiculos/999/ficha-tecnica', [], $headers)->assertStatus(404);
    }

    public function test_sem_ficha_retorna_fonte_e_dados_nulos(): void
    {
        [, $headers] = $this->autenticar();
        $veiculo = Veiculo::factory()->create();

        $this->getJson("/api/veiculos/{$veiculo->id}/ficha-tecnica", $headers)
            ->assertStatus(200)
            ->assertJsonPath('fonte', null)
            ->assertJsonPath('dados', null);
    }

    public function test_salva_e_le_ficha_manual(): void
    {
        [, $headers] = $this->autenticar();
        $veiculo = Veiculo::factory()->create();

        $this->putJson("/api/veiculos/{$veiculo->id}/ficha-tecnica", [
            'oleo_viscosidade' => '5W30',
            'oleo_especificacao' => 'API SN',
            'oleo_capacidade_litros' => 3.8,
            'pneu_medida' => '185/65 R15',
            'pneu_pressao_dianteira' => 32,
        ], $headers)->assertStatus(200)->assertJsonPath('fonte', 'manual');

        $this->getJson("/api/veiculos/{$veiculo->id}/ficha-tecnica", $headers)
            ->assertStatus(200)
            ->assertJsonPath('fonte', 'manual')
            ->assertJsonPath('dados.oleo_viscosidade', '5W30')
            ->assertJsonPath('dados.pneu_medida', '185/65 R15')
            ->assertJsonPath('dados.filtro_ar', null);
    }

    public function test_salvar_de_novo_atualiza_sem_duplicar(): void
    {
        [, $headers] = $this->autenticar();
        $veiculo = Veiculo::factory()->create();

        $this->putJson("/api/veiculos/{$veiculo->id}/ficha-tecnica", ['oleo_viscosidade' => '5W30'], $headers);
        $this->putJson("/api/veiculos/{$veiculo->id}/ficha-tecnica", ['oleo_viscosidade' => '0W20'], $headers);

        $this->assertSame(1, DB::table('fichas_tecnicas')->count());
        $this->assertDatabaseHas('fichas_tecnicas', ['veiculo_id' => $veiculo->id, 'oleo_viscosidade' => '0W20']);
    }

    public function test_valida_pressao_e_capacidade(): void
    {
        [, $headers] = $this->autenticar();
        $veiculo = Veiculo::factory()->create();

        $this->putJson("/api/veiculos/{$veiculo->id}/ficha-tecnica", [
            'pneu_pressao_dianteira' => 'abc',
            'oleo_capacidade_litros' => 500,
        ], $headers)->assertStatus(422)
            ->assertJsonValidationErrors(['pneu_pressao_dianteira', 'oleo_capacidade_litros']);
    }

    public function test_nao_acessa_ficha_de_outra_oficina(): void
    {
        [, $headers] = $this->autenticar();

        app()->instance('oficina.atual', Oficina::factory()->create()->id);
        $veiculoOutro = Veiculo::factory()->create();

        $this->getJson("/api/veiculos/{$veiculoOutro->id}/ficha-tecnica", $headers)->assertStatus(404);
        $this->putJson("/api/veiculos/{$veiculoOutro->id}/ficha-tecnica", ['filtro_ar' => 'x'], $headers)->assertStatus(404);
    }

    public function test_fonte_de_maior_prioridade_vence_e_a_manual_e_fallback(): void
    {
        $this->autenticar();
        $veiculo = Veiculo::factory()->create();

        $catalogoComercial = new class implements CatalogoTecnico {
            public bool $responde = true;

            public function nome(): string
            {
                return 'comercial';
            }

            public function fichaPara(Veiculo $veiculo): ?array
            {
                return $this->responde ? ['oleo_viscosidade' => '0W16'] : null;
            }
        };

        $servico = new CatalogoTecnicoService([$catalogoComercial, new FichaManualCatalogo()]);

        $this->assertSame('comercial', $servico->fichaPara($veiculo)['fonte']);

        $catalogoComercial->responde = false;
        $this->assertNull($servico->fichaPara($veiculo));

        \App\Models\FichaTecnica::create(['veiculo_id' => $veiculo->id, 'oleo_viscosidade' => '5W30']);
        $this->assertSame('manual', $servico->fichaPara($veiculo)['fonte']);
    }
}
