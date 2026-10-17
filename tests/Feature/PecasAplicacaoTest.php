<?php

namespace Tests\Feature;

use App\Models\VeiculoConta;
use App\Services\PecasAplicacao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

/**
 * Os códigos abaixo (TESTE-*) são fictícios e existem só aqui: o app nunca
 * traz código que não tenha vindo de uma planilha com fonte.
 */
class PecasAplicacaoTest extends TestCase
{
    use AutenticaUsuario;
    use RefreshDatabase;

    private string $pasta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pasta = sys_get_temp_dir() . '/pa_' . uniqid();
        mkdir($this->pasta);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->pasta . '/*') ?: [] as $arquivo) {
            unlink($arquivo);
        }

        rmdir($this->pasta);

        parent::tearDown();
    }

    private function gravarGol(): void
    {
        file_put_contents($this->pasta . '/volkswagen-gol.json', json_encode(['modelo' => 'Volkswagen Gol', 'aplicacoes' => [
            ['peca' => 'filtro-de-oleo', 'fabricante' => 'Marca A', 'codigo' => 'TESTE-1', 'ano_de' => 2008, 'ano_ate' => 2012, 'motor' => '1.0', 'observacao' => null, 'fonte' => 'Teste'],
            ['peca' => 'filtro-de-oleo', 'fabricante' => 'Marca B', 'codigo' => 'TESTE-2', 'ano_de' => 2013, 'ano_ate' => null, 'motor' => null, 'observacao' => null, 'fonte' => 'Teste'],
            ['peca' => 'amortecedor-dianteiro', 'fabricante' => 'Marca C', 'codigo' => 'TESTE-3', 'ano_de' => null, 'ano_ate' => null, 'motor' => '1.6', 'observacao' => 'par', 'fonte' => 'Teste'],
        ]]));
    }

    private function csv(string $conteudo): string
    {
        $arquivo = $this->pasta . '/entrada_' . uniqid() . '.csv';
        file_put_contents($arquivo, $conteudo);

        return $arquivo;
    }

    // -------------------------------------------------------------- leitura

    public function test_filtra_por_ano_e_por_motor(): void
    {
        $this->gravarGol();
        $servico = new PecasAplicacao($this->pasta);

        $r2010 = $servico->paraModelo('Volkswagen Gol', 2010, 'Gol 1.0 City (Flex)');
        $this->assertSame(['filtro-de-oleo'], array_keys($r2010));
        $this->assertSame('TESTE-1', $r2010['filtro-de-oleo'][0]['codigo']);

        // 2015: o código sem motor serve; o de 2008-2012 não.
        $r2015 = $servico->paraModelo('Volkswagen Gol', 2015, 'Gol 1.0');
        $this->assertSame(['TESTE-2'], array_column($r2015['filtro-de-oleo'], 'codigo'));

        // Motor 1.6 traz o amortecedor, e motor 1.0 não.
        $this->assertArrayHasKey('amortecedor-dianteiro', $servico->paraModelo('Volkswagen Gol', 2010, 'Gol 1.6'));
        $this->assertArrayNotHasKey('amortecedor-dianteiro', $servico->paraModelo('Volkswagen Gol', 2010, 'Gol 1.0'));
    }

    public function test_ano_e_motor_desconhecidos_nao_filtram(): void
    {
        $this->gravarGol();

        $todos = (new PecasAplicacao($this->pasta))->paraModelo('Volkswagen Gol');

        $this->assertCount(2, $todos['filtro-de-oleo']);
        $this->assertCount(1, $todos['amortecedor-dianteiro']);
    }

    public function test_modelo_sem_arquivo_nao_inventa_codigo(): void
    {
        $servico = new PecasAplicacao($this->pasta);

        $this->assertSame([], $servico->paraModelo('Fiat Uno', 2010, 'Uno 1.0'));
        $this->assertSame(0, $servico->total('Fiat Uno'));
    }

    // ------------------------------------------------------------- importar

    public function test_importa_planilha_com_ponto_e_virgula_bom_e_nome_ou_id_da_peca(): void
    {
        $arquivo = $this->csv("\xEF\xBB\xBF" . implode("\n", [
            'modelo;ano_de;ano_ate;motor;peca;fabricante;codigo;observacao',
            'Volkswagen Gol;2008;2012;1.0;Filtro de óleo;Marca A;TESTE-1;',
            'volkswagen gol;;;;amortecedor-dianteiro;Marca C;TESTE-3;par',
            '',
        ]));

        $this->artisan('pecas:importar', ['arquivo' => $arquivo, '--fonte' => 'Catálogo de teste', '--pasta' => $this->pasta])
            ->expectsOutputToContain('2 linha(s), 2 nova(s)')
            ->assertSuccessful();

        $dados = json_decode(file_get_contents($this->pasta . '/volkswagen-gol.json'), true);

        $this->assertSame('Volkswagen Gol', $dados['modelo']);
        $this->assertCount(2, $dados['aplicacoes']);
        $this->assertSame('Catálogo de teste', $dados['aplicacoes'][0]['fonte']);
        $this->assertSame(['amortecedor-dianteiro', 'filtro-de-oleo'], array_column($dados['aplicacoes'], 'peca'));
        $this->assertNull($dados['aplicacoes'][0]['ano_de']);
    }

    public function test_importar_duas_vezes_nao_duplica(): void
    {
        $arquivo = $this->csv("modelo,peca,fabricante,codigo\nVolkswagen Gol,Filtro de óleo,Marca A,TESTE-1\n");
        $opcoes = ['arquivo' => $arquivo, '--fonte' => 'Teste', '--pasta' => $this->pasta];

        $this->artisan('pecas:importar', $opcoes)->assertSuccessful();
        $this->artisan('pecas:importar', $opcoes)->expectsOutputToContain('0 nova(s), 1 já existia')->assertSuccessful();

        $dados = json_decode(file_get_contents($this->pasta . '/volkswagen-gol.json'), true);
        $this->assertCount(1, $dados['aplicacoes']);
    }

    public function test_simular_nao_grava(): void
    {
        $arquivo = $this->csv("modelo;peca;fabricante;codigo\nVolkswagen Gol;Filtro de óleo;Marca A;TESTE-1\n");

        $this->artisan('pecas:importar', ['arquivo' => $arquivo, '--fonte' => 'Teste', '--pasta' => $this->pasta, '--simular' => true])
            ->assertSuccessful();

        $this->assertFileDoesNotExist($this->pasta . '/volkswagen-gol.json');
    }

    public function test_linha_invalida_aborta_tudo_e_aponta_a_linha(): void
    {
        $arquivo = $this->csv(implode("\n", [
            'modelo;ano_de;ano_ate;motor;peca;fabricante;codigo;observacao',
            'Volkswagen Gol;2008;2012;1.0;Filtro de óleo;Marca A;TESTE-1;',
            'Modelo Que Nao Existe;;;;Filtro de óleo;Marca A;TESTE-9;',
            'Volkswagen Gol;;;;Peça Inexistente;Marca A;TESTE-8;',
            'Volkswagen Gol;2012;2008;;Filtro de óleo;Marca A;TESTE-7;',
            'Volkswagen Gol;abc;;;Filtro de óleo;Marca A;TESTE-6;',
            'Volkswagen Gol;;;;Filtro de óleo;;TESTE-5;',
        ]));

        $this->artisan('pecas:importar', ['arquivo' => $arquivo, '--fonte' => 'Teste', '--pasta' => $this->pasta])
            ->expectsOutputToContain('5 problema(s)')
            ->expectsOutputToContain('linha 3: modelo "Modelo Que Nao Existe" não está no catálogo')
            ->expectsOutputToContain('linha 4: peça "Peça Inexistente" não está no catálogo')
            ->expectsOutputToContain('linha 5: ano_de maior que ano_ate')
            ->expectsOutputToContain('linha 6: ano_de "abc" inválido')
            ->expectsOutputToContain('linha 7: fabricante vazio')
            ->assertFailed();

        // A linha 2 era boa, mas nada é gravado.
        $this->assertFileDoesNotExist($this->pasta . '/volkswagen-gol.json');
    }

    public function test_exige_fonte_e_cabecalho_com_as_colunas_obrigatorias(): void
    {
        $arquivo = $this->csv("modelo;peca;fabricante;codigo\nVolkswagen Gol;Filtro de óleo;Marca A;TESTE-1\n");

        $this->artisan('pecas:importar', ['arquivo' => $arquivo, '--pasta' => $this->pasta])
            ->expectsOutputToContain('Informe a fonte')
            ->assertFailed();

        $semCodigo = $this->csv("modelo;peca;fabricante\nVolkswagen Gol;Filtro de óleo;Marca A\n");

        $this->artisan('pecas:importar', ['arquivo' => $semCodigo, '--fonte' => 'Teste', '--pasta' => $this->pasta])
            ->expectsOutputToContain('Coluna obrigatória ausente no cabeçalho: codigo')
            ->assertFailed();
    }

    // ------------------------------------------------------------------ API

    public function test_catalogo_traz_os_codigos_do_modelo_filtrados_por_ano_do_veiculo(): void
    {
        $this->gravarGol();
        $this->app->instance(PecasAplicacao::class, new PecasAplicacao($this->pasta));

        [, $headers] = $this->autenticarConta('pessoa');
        $gol2010 = VeiculoConta::create(['placa' => 'AAA1B25', 'marca' => 'VW - VolksWagen', 'modelo' => 'Gol 1.0 City (Flex)', 'ano' => 2010]);
        $gol2020 = VeiculoConta::create(['placa' => 'BBB2C36', 'marca' => 'VW - VolksWagen', 'modelo' => 'Gol 1.0 City (Flex)', 'ano' => 2020]);

        $pecas2010 = collect($this->getJson("/api/conta/pecas-catalogo?veiculo_id={$gol2010->id}", $headers)->assertOk()->json('pecas'))->keyBy('id');
        $this->assertSame(['TESTE-1'], array_column($pecas2010['filtro-de-oleo']['codigos_catalogo'], 'codigo'));
        $this->assertSame('Marca A', $pecas2010['filtro-de-oleo']['codigos_catalogo'][0]['fabricante']);
        $this->assertSame([], $pecas2010['filtro-de-ar-do-motor']['codigos_catalogo']);

        $pecas2020 = collect($this->getJson("/api/conta/pecas-catalogo?veiculo_id={$gol2020->id}", $headers)->json('pecas'))->keyBy('id');
        $this->assertSame(['TESTE-2'], array_column($pecas2020['filtro-de-oleo']['codigos_catalogo'], 'codigo'));
    }

    public function test_sem_arquivo_a_lista_de_codigos_vem_vazia(): void
    {
        $this->app->instance(PecasAplicacao::class, new PecasAplicacao($this->pasta));

        [, $headers] = $this->autenticarConta('pessoa');
        $v = VeiculoConta::create(['placa' => 'AAA1B25', 'marca' => 'VW - VolksWagen', 'modelo' => 'Gol 1.0', 'ano' => 2010]);

        $pecas = $this->getJson("/api/conta/pecas-catalogo?veiculo_id={$v->id}", $headers)->assertOk()->json('pecas');

        $this->assertNotEmpty($pecas);
        $this->assertSame([], array_unique(array_merge(...array_column($pecas, 'codigos_catalogo'))));
    }

    public function test_comando_de_cobertura_lista_os_modelos(): void
    {
        $this->gravarGol();
        $this->app->instance(PecasAplicacao::class, new PecasAplicacao($this->pasta));

        $this->artisan('pecas:cobertura')
            ->expectsOutputToContain('1 de ')
            ->assertSuccessful();
    }
}
