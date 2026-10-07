<?php

namespace Tests\Feature;

use App\Models\FichaTecnicaConta;
use App\Models\VeiculoConta;
use App\Services\EspecificacoesDoNome;
use App\Services\FichasModelos;
use App\Services\WikipediaInfobox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class FichaTecnicaContaTest extends TestCase
{
    use RefreshDatabase;
    use AutenticaUsuario;

    private function veiculo(array $extra = []): VeiculoConta
    {
        return VeiculoConta::create(array_merge([
            'placa' => 'AAA1B25', 'marca' => 'GM - Chevrolet', 'modelo' => 'Onix Hatch LT 1.0 12V Flex 5p Mec.', 'ano' => 2020,
        ], $extra));
    }

    // ------------------------------------------------- leitura do nome da versão

    public function test_le_o_que_esta_escrito_no_nome_da_versao(): void
    {
        $le = fn (string $nome) => collect((new EspecificacoesDoNome())->extrair($nome))->pluck('valor', 'chave')->all();

        $this->assertSame(
            ['motor' => '1.4 litros', 'valvulas' => '8', 'combustivel' => 'Flex (gasolina e etanol)', 'cambio' => 'Manual', 'portas' => '5'],
            $le('Onix Hatch LTZ 1.4 8V FlexPower 5p Mec.'),
        );

        $hilux = $le('Hilux CD SRX 2.8 4x4 TB Dies. Aut.');
        $this->assertSame('2.8 litros', $hilux['motor']);
        $this->assertSame('Turbo', $hilux['turbo']);
        $this->assertSame('Diesel', $hilux['combustivel']);
        $this->assertSame('Automático', $hilux['cambio']);
        $this->assertSame('4x4', $hilux['tracao']);

        $this->assertSame('Automático (CVT)', $le('Civic Sedan EXL 2.0 Flex 16V Aut.(CVT)')['cambio'] ?? $le('Civic CVT 2.0')['cambio']);
        $this->assertSame('Turbo', $le('T-Cross Sense 200 TSI 1.0 Flex')['turbo']);
    }

    public function test_nao_inventa_o_que_nao_esta_no_nome(): void
    {
        $this->assertSame([], (new EspecificacoesDoNome())->extrair('Modelo Qualquer'));
        $this->assertSame([], (new EspecificacoesDoNome())->extrair(null));
    }

    // ------------------------------------------------------ leitura do infobox

    public function test_le_o_infobox_da_wikipedia_e_deixa_o_consumo_de_fora(): void
    {
        $campos = (new WikipediaInfobox())->extrair(file_get_contents(base_path('tests/fixtures/wikipedia_onix_infobox.txt')));
        $porChave = collect($campos)->pluck('valor', 'chave');

        $this->assertSame('82 a 116 cv', $porChave['potencia']);
        $this->assertSame('10,6 -16,8 kgfm', $porChave['torque']);
        $this->assertSame('4163 mm', $porChave['comprimento']);
        $this->assertSame('2551 mm', $porChave['entre_eixos']);
        $this->assertSame('44 L', $porChave['tanque']);
        $this->assertSame('200 km/h', $porChave['velocidade_maxima']);
        $this->assertSame('Hatchback (5 portas); Sedan Plus (4 portas)', $porChave['carroceria']);
        $this->assertStringNotContainsString('{{', $porChave->implode(' '));
        $this->assertStringNotContainsString('[[', $porChave->implode(' '));
        // Consumo/autonomia, imagens e antecessores não entram.
        $this->assertNull($porChave->get('autonomia'));
        $this->assertCount(0, collect($campos)->filter(fn ($c) => str_contains($c['valor'], 'urbano')));
    }

    public function test_pagina_sem_infobox_de_automovel_devolve_nulo(): void
    {
        $this->assertNull((new WikipediaInfobox())->extrair("'''Algo''' sem infobox. {{Outro|a=b}}"));
    }

    public function test_as_fichas_coletadas_tem_fonte_e_campos(): void
    {
        $fichas = new FichasModelos();

        $this->assertGreaterThan(0, $fichas->total());
        $onix = $fichas->porNome('Chevrolet Onix');
        $this->assertSame('Wikipédia', $onix['fonte']);
        $this->assertStringStartsWith('https://pt.wikipedia.org/wiki/', $onix['url']);
        $this->assertSame('CC BY-SA 4.0', $onix['licenca']);
        $this->assertNotEmpty($onix['campos']);
        $this->assertNull($fichas->porNome('Modelo Que Nao Existe'));
        $this->assertNull($fichas->porNome(null));
    }

    // ------------------------------------------------------------------- API

    public function test_exige_autenticacao_e_perfil_de_conta(): void
    {
        $this->getJson('/api/conta/veiculos/1/ficha-tecnica')->assertStatus(401);

        [, $headers] = $this->autenticar();
        $this->getJson('/api/conta/veiculos/1/ficha-tecnica', $headers)->assertStatus(403);
    }

    public function test_junta_fipe_nome_da_versao_dados_do_modelo_e_manutencao(): void
    {
        Http::fake(['*' => Http::response([
            'Valor' => 'R$ 60.250,50', 'Marca' => 'GM - Chevrolet', 'Modelo' => 'Onix Hatch LT 1.0 12V Flex 5p Mec.',
            'AnoModelo' => 2020, 'Combustivel' => 'Gasolina', 'CodigoFipe' => '004447-5', 'MesReferencia' => 'outubro de 2026 ',
        ])]);

        [, $headers] = $this->autenticarConta('pessoa');
        $v = $this->veiculo(['fipe_marca_id' => 23, 'fipe_modelo_id' => 5940, 'fipe_ano' => '2020-1']);

        $r = $this->getJson("/api/conta/veiculos/{$v->id}/ficha-tecnica", $headers)->assertStatus(200);

        $r->assertJsonPath('fipe_status', 'ok')
            ->assertJsonPath('fipe.codigo_fipe', '004447-5')
            ->assertJsonPath('fipe.valor', 60250.5)
            ->assertJsonPath('fipe.mes_referencia', 'outubro de 2026')
            ->assertJsonPath('manutencao', null)
            ->assertJsonPath('veiculo.placa', 'AAA1B25');

        $especs = collect($r->json('especificacoes'))->pluck('valor', 'chave');
        $this->assertSame('1.0 litros', $especs['motor']);
        $this->assertSame('12', $especs['valvulas']);
        $this->assertSame('Manual', $especs['cambio']);

        // Onix está no catálogo e na coleta: vem a ficha do modelo com a fonte.
        $r->assertJsonPath('dados_modelo.modelo', 'Chevrolet Onix')->assertJsonPath('dados_modelo.fonte', 'Wikipédia');
    }

    public function test_fipe_fora_do_ar_nao_derruba_a_ficha(): void
    {
        Http::fake(['*' => Http::response('erro', 500)]);

        [, $headers] = $this->autenticarConta('pessoa');
        $v = $this->veiculo(['fipe_marca_id' => 23, 'fipe_modelo_id' => 5940, 'fipe_ano' => '2020-1']);

        $this->getJson("/api/conta/veiculos/{$v->id}/ficha-tecnica", $headers)
            ->assertStatus(200)->assertJsonPath('fipe_status', 'indisponivel')->assertJsonPath('fipe', null);
    }

    public function test_veiculo_sem_codigo_fipe_e_fora_da_coleta(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $v = $this->veiculo(['marca' => 'Lexus', 'modelo' => 'IS 250 2.5 V6']);

        $this->getJson("/api/conta/veiculos/{$v->id}/ficha-tecnica", $headers)
            ->assertStatus(200)->assertJsonPath('fipe_status', 'sem_codigo')
            ->assertJsonPath('dados_modelo', null);
    }

    public function test_grava_e_devolve_a_ficha_de_manutencao(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $v = $this->veiculo();

        $this->putJson("/api/conta/veiculos/{$v->id}/ficha-tecnica", [
            'oleo_viscosidade' => '5W30', 'oleo_especificacao' => 'API SN', 'oleo_capacidade_litros' => 3.8,
            'pneu_medida' => '185/65 R15', 'pneu_pressao_dianteira' => 32, 'pneu_pressao_traseira' => 30,
        ], $headers)->assertStatus(200)
            ->assertJsonPath('manutencao.oleo_viscosidade', '5W30')
            ->assertJsonPath('manutencao.pneu_pressao_dianteira', 32);

        // Segunda gravação atualiza, não duplica.
        $this->putJson("/api/conta/veiculos/{$v->id}/ficha-tecnica", ['oleo_viscosidade' => '0W20'], $headers)->assertStatus(200);
        $this->assertSame(1, FichaTecnicaConta::withoutGlobalScopes()->count());
        $this->getJson("/api/conta/veiculos/{$v->id}/ficha-tecnica", $headers)->assertJsonPath('manutencao.oleo_viscosidade', '0W20');
    }

    public function test_valida_a_ficha_de_manutencao(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $v = $this->veiculo();

        $this->putJson("/api/conta/veiculos/{$v->id}/ficha-tecnica", ['pneu_pressao_dianteira' => 500, 'oleo_capacidade_litros' => 'muito'], $headers)
            ->assertStatus(422)->assertJsonValidationErrors(['pneu_pressao_dianteira', 'oleo_capacidade_litros']);
    }

    public function test_uma_conta_nao_ve_nem_grava_na_ficha_de_outra(): void
    {
        [, $headersA] = $this->autenticarConta('pessoa');
        $v = $this->veiculo();
        $this->putJson("/api/conta/veiculos/{$v->id}/ficha-tecnica", ['filtro_oleo' => 'segredo'], $headersA)->assertStatus(200);

        [, $headersB] = $this->autenticarConta('frota');

        $this->getJson("/api/conta/veiculos/{$v->id}/ficha-tecnica", $headersB)->assertStatus(404);
        $this->putJson("/api/conta/veiculos/{$v->id}/ficha-tecnica", ['filtro_oleo' => 'x'], $headersB)->assertStatus(404);
    }
}
