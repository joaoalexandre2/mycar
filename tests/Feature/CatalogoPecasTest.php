<?php

namespace Tests\Feature;

use App\Models\VeiculoConta;
use App\Services\CatalogoPecas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class CatalogoPecasTest extends TestCase
{
    use RefreshDatabase;
    use AutenticaUsuario;

    private function veiculo(string $marca, string $modelo, string $placa = 'AAA1B25'): VeiculoConta
    {
        return VeiculoConta::create(['placa' => $placa, 'marca' => $marca, 'modelo' => $modelo, 'ano' => 2020]);
    }

    // ------------------------------------------------------ dados do catálogo

    public function test_os_dados_do_catalogo_estao_consistentes(): void
    {
        $sistemas = array_keys(config('pecas_catalogo.sistemas'));
        $categorias = array_keys(config('pecas_catalogo.categorias'));
        $nomes = [];

        foreach (config('pecas_catalogo.pecas') as $linha) {
            $this->assertCount(7, $linha, 'Peça com colunas a mais ou a menos: '.json_encode($linha));
            [$sis, $nome, , $posicao, $km, , $cats] = $linha;

            $this->assertContains($sis, $sistemas, $nome);
            $this->assertContains($posicao, [null, 'dianteiro', 'traseiro'], $nome);
            $this->assertTrue($km === null || is_int($km), $nome);
            $this->assertTrue($cats === null || !array_diff($cats, $categorias), $nome);
            $this->assertNotContains($nome, $nomes, "Peça repetida: {$nome}");
            $nomes[] = $nome;
        }

        foreach (config('pecas_catalogo.modelos') as $linha) {
            $this->assertCount(4, $linha, json_encode($linha));
            $this->assertContains($linha[2], $categorias, $linha[3]);
            $this->assertSame(CatalogoPecas::normalizar($linha[1]), $linha[1], "Palavras fora do padrão em {$linha[3]}");
        }

        $this->assertGreaterThanOrEqual(70, count(config('pecas_catalogo.modelos')));
        $this->assertGreaterThanOrEqual(100, count(config('pecas_catalogo.pecas')));
    }

    // ------------------------------------------------------ modelo do veículo

    public function test_acha_o_modelo_pelo_nome_que_vem_da_fipe(): void
    {
        $catalogo = new CatalogoPecas();

        $this->assertSame('Volkswagen Gol', $catalogo->modeloDoVeiculo('VW - VolksWagen', 'Gol 1.0 City (Flex)')['nome']);
        $this->assertSame('Chevrolet Onix', $catalogo->modeloDoVeiculo('GM - Chevrolet', 'Onix Hatch LTZ 1.4 8V FlexPower 5p Mec.')['nome']);
        $this->assertSame('Hyundai HB20', $catalogo->modeloDoVeiculo('Hyundai', 'HB20 1.0 Comfort Plus')['nome']);
        $this->assertSame('Honda HR-V', $catalogo->modeloDoVeiculo('Honda', 'HR-V EXL 1.8 Flex 16V 5p Aut.')['nome']);
        $this->assertSame('Volkswagen T-Cross', $catalogo->modeloDoVeiculo('VW - VolksWagen', 'T-Cross Sense 200 TSI 1.0 Flex')['nome']);
        $this->assertSame('Citroën C3', $catalogo->modeloDoVeiculo('Citroën', 'C3 Live 1.2')['nome']);
    }

    public function test_o_modelo_mais_especifico_vence(): void
    {
        $catalogo = new CatalogoPecas();

        $this->assertSame('Toyota Corolla Cross', $catalogo->modeloDoVeiculo('Toyota', 'Corolla Cross XRE 2.0')['nome']);
        $this->assertSame('Toyota Corolla', $catalogo->modeloDoVeiculo('Toyota', 'Corolla XEi 2.0 Flex')['nome']);
        $this->assertSame('sedan', $catalogo->modeloDoVeiculo('GM - Chevrolet', 'Onix Plus Premier 1.0')['categoria']);
        $this->assertSame('hatch', $catalogo->modeloDoVeiculo('GM - Chevrolet', 'Onix LT 1.0')['categoria']);
    }

    public function test_marca_errada_ou_modelo_fora_do_catalogo_nao_casa(): void
    {
        $catalogo = new CatalogoPecas();

        $this->assertNull($catalogo->modeloDoVeiculo('BMW', '320iA 2.0 TB M Sport A.Flex/M.Sport 4p'));
        $this->assertNull($catalogo->modeloDoVeiculo('Ford', 'Gol 1.0')); // Gol existe, mas na VW
        $this->assertNull($catalogo->modeloDoVeiculo(null, null));
    }

    // ----------------------------------------------------------------- busca

    public function test_busca_ignora_acento_caixa_e_plural(): void
    {
        $catalogo = new CatalogoPecas();

        $nomes = fn (string $q) => array_column($catalogo->pecas(null, $q), 'nome');

        $this->assertContains('Amortecedor dianteiro', $nomes('amortecedor'));
        $this->assertContains('Amortecedor dianteiro', $nomes('AMORTECEDORES'));
        $this->assertContains('Coifa da homocinética (lado roda)', $nomes('coifa'));
        $this->assertContains('Kit coifa e batente do amortecedor (dianteiro)', $nomes('coifa'));
        $this->assertContains('Coifa da caixa de direção', $nomes('coifa'));
        $this->assertContains('Pastilhas de freio dianteiras', $nomes('pastilha de freio'));
        $this->assertContains('Kit de embreagem', $nomes('embreagem'));
        $this->assertContains('Kit de embreagem', $nomes('platô'));
        $this->assertSame([], $nomes('turbina de foguete'));
    }

    public function test_busca_com_varias_palavras_exige_todas(): void
    {
        $nomes = array_column((new CatalogoPecas())->pecas(null, 'coifa amortecedor'), 'nome');

        $this->assertContains('Kit coifa e batente do amortecedor (dianteiro)', $nomes);
        $this->assertNotContains('Coifa da caixa de direção', $nomes);
    }

    public function test_acertar_o_nome_vem_antes_de_acertar_so_o_sinonimo(): void
    {
        $nomes = array_column((new CatalogoPecas())->pecas(null, 'amortecedor'), 'nome');

        $this->assertSame('Amortecedor dianteiro', $nomes[0]);
    }

    // ------------------------------------------------------------------- API

    public function test_exige_autenticacao_e_perfil_de_conta(): void
    {
        $this->getJson('/api/conta/pecas-catalogo')->assertStatus(401);

        [, $headers] = $this->autenticar();
        $this->getJson('/api/conta/pecas-catalogo', $headers)->assertStatus(403);
    }

    public function test_sem_veiculo_traz_o_catalogo_inteiro_com_contadores(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');

        $r = $this->getJson('/api/conta/pecas-catalogo', $headers)->assertStatus(200);

        $r->assertJsonPath('escopo', 'catalogo')->assertJsonPath('veiculo', null)->assertJsonPath('modelo', null);
        $this->assertSame(count(config('pecas_catalogo.pecas')), $r->json('total'));
        $this->assertCount(12, $r->json('sistemas'));
        $this->assertSame(array_sum(array_column($r->json('sistemas'), 'total')), $r->json('total'));
        $this->assertStringContainsString('chassi', $r->json('aviso'));
    }

    public function test_com_veiculo_traz_as_pecas_da_categoria_dele(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $hatch = $this->veiculo('GM - Chevrolet', 'Onix LT 1.0');
        $picape = $this->veiculo('Fiat', 'Strada Freedom 1.3', 'BBB2C36');

        $rHatch = $this->getJson("/api/conta/pecas-catalogo?veiculo_id={$hatch->id}", $headers)->assertStatus(200);
        $rHatch->assertJsonPath('escopo', 'modelo')
            ->assertJsonPath('modelo.nome', 'Chevrolet Onix')
            ->assertJsonPath('modelo.categoria_rotulo', 'Hatch')
            ->assertJsonPath('veiculo.id', $hatch->id);

        $nomesHatch = array_column($rHatch->json('pecas'), 'nome');
        $nomesPicape = array_column($this->getJson("/api/conta/pecas-catalogo?veiculo_id={$picape->id}", $headers)->json('pecas'), 'nome');

        // Palheta traseira: hatch tem, picape não. Cardã: picape tem, hatch não.
        $this->assertContains('Palheta do limpador traseiro', $nomesHatch);
        $this->assertNotContains('Palheta do limpador traseiro', $nomesPicape);
        $this->assertContains('Cardã e cruzeta', $nomesPicape);
        $this->assertNotContains('Cardã e cruzeta', $nomesHatch);
    }

    public function test_veiculo_fora_do_catalogo_recebe_so_as_pecas_comuns(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $bmw = $this->veiculo('BMW', '320iA 2.0 TB M Sport A.Flex/M.Sport 4p');

        $r = $this->getJson("/api/conta/pecas-catalogo?veiculo_id={$bmw->id}", $headers)->assertStatus(200);

        $r->assertJsonPath('escopo', 'geral')->assertJsonPath('modelo', null);
        $nomes = array_column($r->json('pecas'), 'nome');
        $this->assertContains('Amortecedor dianteiro', $nomes);
        $this->assertNotContains('Cardã e cruzeta', $nomes);
        $this->assertNotContains('Palheta do limpador traseiro', $nomes);
    }

    public function test_busca_e_filtro_de_sistema_pela_api(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $v = $this->veiculo('Fiat', 'Uno Way 1.0');

        $r = $this->getJson("/api/conta/pecas-catalogo?veiculo_id={$v->id}&q=coifa", $headers)->assertStatus(200);
        $this->assertEqualsCanonicalizing(['transmissao', 'suspensao', 'direcao', 'freios'], array_values(array_unique(array_column($r->json('pecas'), 'sistema'))));
        $this->assertGreaterThanOrEqual(4, $r->json('total'));

        // Contadores respeitam a busca; o filtro de sistema só afeta a lista.
        $suspensao = $this->getJson("/api/conta/pecas-catalogo?veiculo_id={$v->id}&q=coifa&sistema=suspensao", $headers);
        $this->assertSame(['suspensao'], array_values(array_unique(array_column($suspensao->json('pecas'), 'sistema'))));
        $this->assertSame($r->json('sistemas'), $suspensao->json('sistemas'));

        $this->getJson('/api/conta/pecas-catalogo?sistema=naoexiste', $headers)->assertStatus(422)->assertJsonValidationErrors('sistema');
    }

    public function test_nao_le_veiculo_de_outra_conta(): void
    {
        [, $headersA] = $this->autenticarConta('pessoa');
        $veiculoA = $this->veiculo('Fiat', 'Uno');

        [, $headersB] = $this->autenticarConta('frota');

        $this->getJson("/api/conta/pecas-catalogo?veiculo_id={$veiculoA->id}", $headersB)->assertStatus(404);
        $this->getJson("/api/conta/pecas-catalogo?veiculo_id={$veiculoA->id}", $headersA)->assertStatus(200);
    }
}
