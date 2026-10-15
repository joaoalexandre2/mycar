<?php

namespace Tests\Feature;

use App\Models\VeiculoConta;
use App\Services\FichaCompletaParser;
use App\Services\FichasCatalogo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class FichaCompletaTest extends TestCase
{
    use AutenticaUsuario;
    use RefreshDatabase;

    private const HTML = <<<'HTML'
        <h1 class="title-entidade">Fiat Strada Adventure 1.8 2016</h1>
        <div class="ent-spec-item"><span class="ent-spec-label">Ano</span><span class="ent-spec-value">2016</span></div>
        <h3 class="ent-ficha-group__title">Motor</h3>
        <div class="ent-spec-item">
            <span class="ent-spec-label">Cilindrada</span>
            <span class="ent-spec-value">1747   cm³</span>
        </div>
        <h3 class="ent-ficha-group__title">Pneus</h3>
        <div class="ent-spec-item"><span class="ent-spec-label">Dianteiros</span><span class="ent-spec-value">205/60 R16</span></div>
        <h3 class="ent-equip-group__title">Conforto</h3>
        <li>Ar-condicionado</li>
        HTML;

    public function test_agrupa_a_ficha_e_deixa_equipamentos_de_fora(): void
    {
        $ficha = (new FichaCompletaParser)->extrair(self::HTML);

        $this->assertSame('Fiat Strada Adventure 1.8 2016', $ficha['titulo']);
        $this->assertSame(['Informações', 'Motor', 'Pneus'], array_column($ficha['grupos'], 'grupo'));
        $this->assertSame(['rotulo' => 'Cilindrada', 'valor' => '1747 cm³'], $ficha['grupos'][1]['campos'][0]);
    }

    public function test_pagina_sem_ficha_devolve_null(): void
    {
        $this->assertNull((new FichaCompletaParser)->extrair('<h1>404</h1>'));
    }

    public function test_escolhe_a_versao_do_ano_mais_recente(): void
    {
        $html = '<a href="/carros/fiat/strada-1-3-ce-2003"></a><a href="/carros/fiat/strada-endurance-2026"></a><a href="/carros/fiat/strada-1-4-cs-2010"></a><a href="/carros/fiat/uno-2030"></a>';

        $this->assertSame(
            ['caminho' => '/carros/fiat/strada-endurance-2026', 'ano' => 2026],
            (new FichaCompletaParser)->versaoMaisRecente($html, 'fiat', 'strada'),
        );
        $this->assertNull((new FichaCompletaParser)->versaoMaisRecente($html, 'fiat', 'toro'));
    }

    public function test_ficha_da_conta_traz_dados_do_catalogo_quando_ha_arquivo(): void
    {
        $pasta = sys_get_temp_dir() . '/fc_' . uniqid();
        mkdir($pasta);
        file_put_contents("{$pasta}/chevrolet.json", json_encode([
            'onix' => ['fonte' => 'Ficha Completa', 'versao' => 'Chevrolet Onix 1.0 2026', 'grupos' => []],
            'onix-plus' => ['fonte' => 'Ficha Completa', 'versao' => 'Chevrolet Onix Plus 1.0 2026', 'grupos' => []],
        ]));
        $this->app->instance(FichasCatalogo::class, new FichasCatalogo($pasta));

        [, $headers] = $this->autenticarConta('pessoa');
        $veiculo = VeiculoConta::create([
            'placa' => 'AAA1B25', 'marca' => 'GM - Chevrolet', 'modelo' => 'Onix Hatch LT 1.0 12V Flex 5p Mec.', 'ano' => 2020,
        ]);

        $this->getJson("/api/conta/veiculos/{$veiculo->id}/ficha-tecnica", $headers)
            ->assertOk()
            ->assertJsonPath('dados_catalogo.fonte', 'Ficha Completa')
            ->assertJsonPath('dados_catalogo.marca', 'chevrolet')
            ->assertJsonPath('dados_catalogo.modelo', 'onix');

        unlink("{$pasta}/chevrolet.json");
        rmdir($pasta);
    }

    public function test_casa_marca_da_fipe_e_prefere_o_modelo_mais_especifico(): void
    {
        $pasta = sys_get_temp_dir() . '/fc_' . uniqid();
        mkdir($pasta);
        file_put_contents("{$pasta}/land-rover.json", json_encode(['range-rover' => ['versao' => 'a'], 'range-rover-evoque' => ['versao' => 'b']]));
        file_put_contents("{$pasta}/volkswagen.json", json_encode(['gol' => ['versao' => 'c']]));
        $catalogo = new FichasCatalogo($pasta);

        $this->assertSame('range-rover-evoque', $catalogo->paraVeiculo('Land Rover', 'Range Rover Evoque Pure 2.0')['modelo']);
        $this->assertSame('range-rover', $catalogo->paraVeiculo('Land Rover', 'Range Rover Sport HSE')['modelo']);
        $this->assertSame('gol', $catalogo->paraVeiculo('VW - VolksWagen', 'Gol 1.0 Mi')['modelo']);
        $this->assertNull($catalogo->paraVeiculo('Fiat', 'Uno Mille'));
        $this->assertNull($catalogo->paraVeiculo(null, null));
        $this->assertSame(3, $catalogo->total());

        array_map('unlink', glob("{$pasta}/*.json"));
        rmdir($pasta);
    }
}
