<?php

namespace Tests\Feature;

use App\Models\VeiculoConta;
use App\Services\ImagensModelos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

/** Foto livre do modelo (Wikimedia): vem no veículo e é servida por rota pública. */
class ImagemModeloTest extends TestCase
{
    use RefreshDatabase;
    use AutenticaUsuario;

    private function slugDoStrada(): string
    {
        $imagem = app(ImagensModelos::class)->porNome('Fiat Strada');

        $this->assertNotNull($imagem, 'database/data/imagens_modelos.json deve trazer a Fiat Strada');

        return $imagem['slug'];
    }

    public function test_veiculo_do_catalogo_traz_a_foto_do_modelo_com_autor_e_licenca(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        VeiculoConta::create(['placa' => 'AAA1B23', 'marca' => 'Fiat', 'modelo' => 'Strada Working', 'ano' => 2020]);

        $dados = $this->getJson('/api/conta/veiculos?all=1', $headers)->assertStatus(200)->json('0');

        $this->assertSame('Fiat Strada', $dados['foto_modelo']['modelo']);
        $this->assertSame('/api/imagens-modelos/'.$this->slugDoStrada(), $dados['foto_modelo']['url']);
        $this->assertNotEmpty($dados['foto_modelo']['autor']);
        $this->assertStringStartsWith('CC', $dados['foto_modelo']['licenca']);
        $this->assertStringContainsString('commons.wikimedia.org', $dados['foto_modelo']['pagina']);
    }

    public function test_modelo_fora_do_catalogo_nao_tem_foto(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        VeiculoConta::create(['placa' => 'AAA1B23', 'marca' => 'Marca Inventada', 'modelo' => 'Carro Raro', 'ano' => 2020]);

        $this->getJson('/api/conta/veiculos?all=1', $headers)
            ->assertJsonPath('0.foto_modelo', null);
    }

    public function test_a_foto_do_modelo_e_publica_e_vem_com_cache(): void
    {
        $resposta = $this->get('/api/imagens-modelos/'.$this->slugDoStrada());

        $resposta->assertStatus(200);
        $this->assertStringContainsString('image/', (string) $resposta->headers->get('Content-Type'));
        $this->assertStringContainsString('max-age', (string) $resposta->headers->get('Cache-Control'));
        $this->assertSame('nosniff', $resposta->headers->get('X-Content-Type-Options'));
    }

    public function test_slug_desconhecido_ou_malicioso_da_404(): void
    {
        $this->get('/api/imagens-modelos/nao-existe')->assertStatus(404);
        $this->get('/api/imagens-modelos/..%2F..%2F.env')->assertStatus(404);
        $this->get('/api/imagens-modelos/ABC.php')->assertStatus(404);
    }

    public function test_todo_arquivo_do_json_existe_e_tem_licenca_livre(): void
    {
        $json = json_decode((string) file_get_contents(base_path('database/data/imagens_modelos.json')), true);

        $this->assertNotEmpty($json);

        foreach ($json as $nome => $imagem) {
            $this->assertFileExists(base_path('database/data/imagens_modelos/'.$imagem['arquivo']), $nome);
            $this->assertNotEmpty($imagem['autor'], $nome);
            $this->assertMatchesRegularExpression('/^(CC BY|CC0|Public domain)/i', $imagem['licenca'], $nome);
            $this->assertStringNotContainsString('logo', strtolower($imagem['pagina']), $nome);
        }
    }
}
