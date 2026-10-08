<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\VeiculoConta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

/**
 * "ano" é o ano do MODELO (o da FIPE). "ano_fabricacao" é opcional e, quando
 * informado, é igual ao do modelo ou um ano antes (2018/2019, 2018/2018).
 */
class AnoFabricacaoTest extends TestCase
{
    use RefreshDatabase;
    use AutenticaUsuario;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-07');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function conta(array $extra = []): array
    {
        return array_merge(['placa' => 'AAA1B25', 'marca' => 'Fiat', 'modelo' => 'Uno', 'ano' => 2019], $extra);
    }

    // ----------------------------------------------------------- pessoa/frota

    public function test_e_opcional_e_o_ano_completo_usa_so_o_modelo(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');

        $this->postJson('/api/conta/veiculos', $this->conta(), $headers)
            ->assertStatus(201)->assertJsonPath('ano_fabricacao', null)->assertJsonPath('ano_completo', '2019');
    }

    public function test_aceita_fabricacao_um_ano_antes_do_modelo_e_mostra_como_no_crlv(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');

        $this->postJson('/api/conta/veiculos', $this->conta(['ano_fabricacao' => 2018]), $headers)
            ->assertStatus(201)->assertJsonPath('ano_fabricacao', 2018)->assertJsonPath('ano', 2019)->assertJsonPath('ano_completo', '2018/2019');
    }

    public function test_fabricacao_igual_ao_modelo_mostra_um_ano_so(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');

        $this->postJson('/api/conta/veiculos', $this->conta(['ano_fabricacao' => 2019]), $headers)
            ->assertStatus(201)->assertJsonPath('ano_completo', '2019');
    }

    public function test_rejeita_fabricacao_depois_do_modelo_ou_com_mais_de_um_ano_de_diferenca(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');

        // 2020 fabricado, modelo 2019: impossível.
        $this->postJson('/api/conta/veiculos', $this->conta(['ano_fabricacao' => 2020]), $headers)
            ->assertStatus(422)->assertJsonValidationErrors('ano_fabricacao');
        // 2017/2019: dois anos de diferença.
        $this->postJson('/api/conta/veiculos', $this->conta(['ano_fabricacao' => 2017]), $headers)
            ->assertStatus(422)->assertJsonValidationErrors('ano_fabricacao');
        // Fora da faixa geral.
        $this->postJson('/api/conta/veiculos', $this->conta(['ano_fabricacao' => 1850]), $headers)->assertStatus(422);
        $this->postJson('/api/conta/veiculos', $this->conta(['ano' => 2027, 'ano_fabricacao' => 2027]), $headers)
            ->assertStatus(422)->assertJsonValidationErrors('ano_fabricacao'); // ainda não foi fabricado
    }

    public function test_carro_modelo_do_ano_seguinte_fabricado_este_ano(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');

        $this->postJson('/api/conta/veiculos', $this->conta(['ano' => 2027, 'ano_fabricacao' => 2026]), $headers)
            ->assertStatus(201)->assertJsonPath('ano_completo', '2026/2027');
    }

    public function test_atualiza_e_limpa_o_ano_de_fabricacao(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $id = $this->postJson('/api/conta/veiculos', $this->conta(['ano_fabricacao' => 2018]), $headers)->json('id');

        $this->putJson("/api/conta/veiculos/{$id}", $this->conta(['ano_fabricacao' => null]), $headers)
            ->assertStatus(200)->assertJsonPath('ano_fabricacao', null)->assertJsonPath('ano_completo', '2019');
    }

    // ----------------------------------------------------------- aviso de isenção

    public function test_avisa_possivel_isencao_so_a_partir_dos_15_anos_de_fabricacao(): void
    {
        $novo = new VeiculoConta(['ano' => 2020, 'ano_fabricacao' => 2019]);
        $this->assertSame(7, $novo->idade_anos);
        $this->assertFalse($novo->possivel_isencao_ipva);

        // 2026 - 2011 = 15 anos pela fabricação (mesmo com modelo 2012).
        $limite = new VeiculoConta(['ano' => 2012, 'ano_fabricacao' => 2011]);
        $this->assertSame(15, $limite->idade_anos);
        $this->assertTrue($limite->possivel_isencao_ipva);

        // Sem a fabricação, conta pelo modelo.
        $semFabricacao = new VeiculoConta(['ano' => 2012]);
        $this->assertSame(14, $semFabricacao->idade_anos);
        $this->assertFalse($semFabricacao->possivel_isencao_ipva);
    }

    public function test_o_aviso_nao_altera_a_estimativa_de_ipva(): void
    {
        $antigo = new VeiculoConta(['ano' => 2005, 'ano_fabricacao' => 2005, 'uf' => 'SP', 'fipe_valor' => 20000]);
        $novo = new VeiculoConta(['ano' => 2024, 'uf' => 'SP', 'fipe_valor' => 20000]);

        $this->assertTrue($antigo->possivel_isencao_ipva);
        $this->assertSame($novo->ipva_estimado, $antigo->ipva_estimado);
    }

    // ----------------------------------------------------------------- oficina

    public function test_oficina_tambem_guarda_e_valida_o_ano_de_fabricacao(): void
    {
        [, $headers] = $this->autenticar();
        $cliente = Cliente::factory()->create();
        $dados = ['cliente_id' => $cliente->id, 'placa' => 'BBB2C36', 'marca' => 'Fiat', 'modelo' => 'Pulse', 'ano' => 2023];

        $this->postJson('/api/veiculos', $dados + ['ano_fabricacao' => 2022], $headers)
            ->assertStatus(201)->assertJsonPath('ano_completo', '2022/2023');

        $this->postJson('/api/veiculos', ['placa' => 'CCC3D47'] + $dados + ['ano_fabricacao' => 2020], $headers)
            ->assertStatus(422)->assertJsonValidationErrors('ano_fabricacao');
    }
}
