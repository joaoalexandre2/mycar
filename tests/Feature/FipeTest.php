<?php

namespace Tests\Feature;

use App\Models\Veiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class FipeTest extends TestCase
{
    use RefreshDatabase;
    use AutenticaUsuario;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_fipe_exige_autenticacao(): void
    {
        $this->getJson('/api/fipe/marcas')->assertStatus(401);
    }

    public function test_lista_marcas(): void
    {
        [, $headers] = $this->autenticar();
        Http::fake(['*/carros/marcas' => Http::response([['codigo' => '23', 'nome' => 'GM - Chevrolet']])]);

        $this->getJson('/api/fipe/marcas', $headers)
            ->assertStatus(200)
            ->assertJsonPath('0.nome', 'GM - Chevrolet');
    }

    public function test_lista_modelos_da_marca(): void
    {
        [, $headers] = $this->autenticar();
        Http::fake(['*/carros/marcas/23/modelos' => Http::response(['modelos' => [['codigo' => 1, 'nome' => 'Onix']]])]);

        $this->getJson('/api/fipe/marcas/23/modelos', $headers)
            ->assertStatus(200)
            ->assertJsonPath('modelos.0.nome', 'Onix');
    }

    public function test_lista_anos_do_modelo(): void
    {
        [, $headers] = $this->autenticar();
        Http::fake(['*/carros/marcas/23/modelos/1/anos' => Http::response([['codigo' => '2020-1', 'nome' => '2020 Gasolina']])]);

        $this->getJson('/api/fipe/marcas/23/modelos/1/anos', $headers)
            ->assertStatus(200)
            ->assertJsonPath('0.codigo', '2020-1');
    }

    public function test_consulta_valor_fipe(): void
    {
        [, $headers] = $this->autenticar();
        Http::fake(['*/carros/marcas/23/modelos/1/anos/2020-1' => Http::response(['Valor' => 'R$ 60.000,00'])]);

        $this->getJson('/api/fipe/marcas/23/modelos/1/anos/2020-1', $headers)
            ->assertStatus(200)
            ->assertJsonPath('Valor', 'R$ 60.000,00');
    }

    public function test_usa_cache_na_segunda_consulta(): void
    {
        [, $headers] = $this->autenticar();
        Http::fake(['*' => Http::response([['codigo' => '1', 'nome' => 'Fiat']])]);

        $this->getJson('/api/fipe/marcas', $headers)->assertStatus(200);
        $this->getJson('/api/fipe/marcas', $headers)->assertStatus(200);

        Http::assertSentCount(1);
    }

    public function test_tipo_invalido_retorna_422(): void
    {
        [, $headers] = $this->autenticar();

        $this->getJson('/api/fipe/marcas?tipo=avioes', $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors('tipo');
    }

    public function test_consulta_e_salva_valor_fipe_do_veiculo(): void
    {
        [, $headers] = $this->autenticar();
        Http::fake(['*/carros/marcas/23/modelos/1/anos/2020-1' => Http::response(['Valor' => 'R$ 60.250,50'])]);
        $veiculo = Veiculo::factory()->create([
            'fipe_marca_id' => 23,
            'fipe_modelo_id' => 1,
            'fipe_ano' => '2020-1',
        ]);

        $this->postJson("/api/veiculos/{$veiculo->id}/fipe", [], $headers)
            ->assertStatus(200)
            ->assertJsonPath('fipe_valor', '60250.50');

        $this->assertDatabaseHas('veiculos', ['id' => $veiculo->id, 'fipe_valor' => 60250.50]);
    }

    public function test_criar_veiculo_com_codigos_fipe_ja_busca_o_valor(): void
    {
        [, $headers] = $this->autenticar();
        Http::fake(['*/carros/marcas/23/modelos/1/anos/2020-1' => Http::response(['Valor' => 'R$ 60.250,50'])]);

        $this->postJson('/api/veiculos', [
            'cliente_id' => \App\Models\Cliente::factory()->create()->id,
            'placa' => 'FIP1A23',
            'marca' => 'GM - Chevrolet',
            'modelo' => 'Onix',
            'ano' => 2020,
            'fipe_marca_id' => 23,
            'fipe_modelo_id' => 1,
            'fipe_ano' => '2020-1',
        ], $headers)
            ->assertStatus(201)
            ->assertJsonPath('fipe_valor', '60250.50');
    }

    public function test_criar_veiculo_salva_mesmo_com_fipe_fora_do_ar(): void
    {
        [, $headers] = $this->autenticar();
        Http::fake(['*' => Http::response('erro', 500)]);

        $this->postJson('/api/veiculos', [
            'cliente_id' => \App\Models\Cliente::factory()->create()->id,
            'placa' => 'FIP2B34',
            'marca' => 'GM - Chevrolet',
            'modelo' => 'Onix',
            'ano' => 2020,
            'fipe_marca_id' => 23,
            'fipe_modelo_id' => 1,
            'fipe_ano' => '2020-1',
        ], $headers)
            ->assertStatus(201)
            ->assertJsonPath('fipe_valor', null);
    }

    public function test_editar_veiculo_sem_valor_busca_o_valor_em_branco(): void
    {
        [, $headers] = $this->autenticar();
        Http::fake(['*/carros/marcas/23/modelos/1/anos/2020-1' => Http::response(['Valor' => 'R$ 55.000,00'])]);
        $veiculo = Veiculo::factory()->create([
            'fipe_marca_id' => 23,
            'fipe_modelo_id' => 1,
            'fipe_ano' => '2020-1',
            'fipe_valor' => null,
        ]);

        $this->putJson("/api/veiculos/{$veiculo->id}", [
            'cliente_id' => $veiculo->cliente_id,
            'placa' => $veiculo->placa,
            'marca' => $veiculo->marca,
            'modelo' => $veiculo->modelo,
            'ano' => $veiculo->ano,
            'fipe_marca_id' => 23,
            'fipe_modelo_id' => 1,
            'fipe_ano' => '2020-1',
        ], $headers)
            ->assertStatus(200)
            ->assertJsonPath('fipe_valor', '55000.00');
    }

    public function test_consulta_fipe_exige_codigos_no_veiculo(): void
    {
        [, $headers] = $this->autenticar();
        $veiculo = Veiculo::factory()->create();

        $this->postJson("/api/veiculos/{$veiculo->id}/fipe", [], $headers)->assertStatus(422);
    }

    public function test_consulta_fipe_de_veiculo_inexistente(): void
    {
        [, $headers] = $this->autenticar();

        $this->postJson('/api/veiculos/99999/fipe', [], $headers)->assertStatus(404);
    }

    public function test_retorna_502_quando_fipe_esta_fora_do_ar(): void
    {
        [, $headers] = $this->autenticar();
        Http::fake(['*' => Http::response('erro', 500)]);

        $this->getJson('/api/fipe/marcas', $headers)->assertStatus(502);
    }
}
