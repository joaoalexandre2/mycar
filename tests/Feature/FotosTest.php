<?php

namespace Tests\Feature;

use App\Models\FotoVeiculoConta;
use App\Models\VeiculoConta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class FotosTest extends TestCase
{
    use RefreshDatabase;
    use AutenticaUsuario;

    /** PNG 1x1 válido. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function veiculo(string $placa = 'AAA1B25'): VeiculoConta
    {
        return VeiculoConta::create(['placa' => $placa, 'marca' => 'Fiat', 'modelo' => 'Uno', 'ano' => 2018]);
    }

    /**
     * Arquivo de verdade no disco: o tipo é detectado pelo CONTEÚDO (o UploadedFile::fake
     * adivinha pela extensão e não provaria nada).
     */
    private function arquivo(string $conteudo, string $nome): UploadedFile
    {
        $caminho = tempnam(sys_get_temp_dir(), 'mycar');
        file_put_contents($caminho, $conteudo);

        return new UploadedFile($caminho, $nome, null, null, true);
    }

    private function imagem(string $nome = 'foto.png'): UploadedFile
    {
        return $this->arquivo(base64_decode(self::PNG), $nome);
    }

    private function enviar(array $headers, VeiculoConta $v, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->post("/api/conta/veiculos/{$v->id}/fotos", array_merge([
            'foto' => $this->imagem('foto.png'),
            'miniatura' => $this->imagem('mini.png'),
        ], $extra), $headers + ['Accept' => 'application/json']);
    }

    // ------------------------------------------------------- foto de perfil

    public function test_perfil_guarda_e_remove_a_foto(): void
    {
        [$user, $headers] = $this->autenticar();
        $foto = 'data:image/png;base64,'.self::PNG;

        $this->getJson('/api/me', $headers)->assertJsonPath('foto', null);

        $this->putJson('/api/me/foto', ['foto' => $foto], $headers)
            ->assertStatus(200)->assertJsonPath('foto', $foto);
        $this->getJson('/api/me', $headers)->assertJsonPath('foto', $foto);
        $this->assertSame($foto, $user->fresh()->foto);

        $this->deleteJson('/api/me/foto', [], $headers)->assertStatus(200)->assertJsonPath('foto', null);
        $this->assertNull($user->fresh()->foto);
    }

    public function test_perfil_so_aceita_imagem_pequena_em_base64(): void
    {
        [, $headers] = $this->autenticar();

        $this->putJson('/api/me/foto', [], $headers)->assertStatus(422)->assertJsonValidationErrors('foto');
        $this->putJson('/api/me/foto', ['foto' => 'https://exemplo.com/foto.png'], $headers)->assertStatus(422);
        $this->putJson('/api/me/foto', ['foto' => 'data:image/svg+xml;base64,PHN2Zz48L3N2Zz4='], $headers)->assertStatus(422);
        $this->putJson('/api/me/foto', ['foto' => 'data:text/html;base64,PHNjcmlwdD4='], $headers)->assertStatus(422);
        $this->putJson('/api/me/foto', ['foto' => 'data:image/png;base64,'.str_repeat('A', 160001)], $headers)->assertStatus(422);
    }

    public function test_perfil_exige_autenticacao(): void
    {
        $this->putJson('/api/me/foto', ['foto' => 'data:image/png;base64,'.self::PNG])->assertStatus(401);
        $this->deleteJson('/api/me/foto')->assertStatus(401);
    }

    // ---------------------------------------------------------------- álbum

    public function test_album_exige_autenticacao_e_perfil_de_conta(): void
    {
        $this->getJson('/api/conta/veiculos/1/fotos')->assertStatus(401);

        [, $headers] = $this->autenticar();
        $this->getJson('/api/conta/veiculos/1/fotos', $headers)->assertStatus(403);
    }

    public function test_envia_foto_e_miniatura_e_lista_com_links_assinados(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $v = $this->veiculo();

        $r = $this->enviar($headers, $v, ['legenda' => 'Frente'])->assertStatus(201);

        $r->assertJsonCount(1, 'fotos')->assertJsonPath('limite', 30)->assertJsonPath('fotos.0.legenda', 'Frente');
        $this->assertStringContainsString('/api/fotos/', $r->json('fotos.0.url'));
        $this->assertStringContainsString('signature=', $r->json('fotos.0.url'));
        $this->assertStringContainsString('/miniatura', $r->json('fotos.0.url_miniatura'));

        $foto = FotoVeiculoConta::withoutGlobalScopes()->first();
        Storage::disk('local')->assertExists([$foto->caminho, $foto->caminho_miniatura]);
        $this->assertStringStartsWith("fotos/{$v->conta_id}/{$v->id}/", $foto->caminho);
        $this->assertSame('image/png', $foto->mime);

        $this->getJson("/api/conta/veiculos/{$v->id}/fotos", $headers)->assertStatus(200)->assertJsonCount(1, 'fotos');
    }

    public function test_o_link_assinado_mostra_a_imagem_sem_token(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $v = $this->veiculo();
        $url = $this->enviar($headers, $v)->json('fotos.0.url');

        $r = $this->get($url)->assertStatus(200);
        $this->assertSame(base64_decode(self::PNG), $r->streamedContent() ?: $r->getContent());
        $this->assertSame('nosniff', $r->headers->get('X-Content-Type-Options'));
    }

    public function test_link_adulterado_ou_expirado_e_barrado(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $v = $this->veiculo();
        $foto = $this->enviar($headers, $v);
        $url = $foto->json('fotos.0.url');

        // Sem assinatura, com assinatura trocada, com o id de outra foto e expirado.
        $this->get(strtok($url, '?'))->assertStatus(403);
        $this->get(preg_replace('/signature=[a-f0-9]+/', 'signature=000', $url))->assertStatus(403);
        $this->get(str_replace('/fotos/'.$foto->json('fotos.0.id').'/', '/fotos/999/', $url))->assertStatus(403);

        $this->travel(3)->hours();
        $this->get($url)->assertStatus(403);
    }

    public function test_tipo_invalido_no_link_e_404(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $v = $this->veiculo();
        $foto = FotoVeiculoConta::withoutGlobalScopes()->create([
            'conta_id' => $v->conta_id, 'veiculo_conta_id' => $v->id, 'caminho' => 'x.png', 'caminho_miniatura' => 'y.png', 'mime' => 'image/png', 'tamanho' => 1,
        ]);

        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute('fotos.mostrar', now()->addHour(), ['foto' => $foto->id, 'tipo' => 'original'], absolute: false);

        $this->get($url)->assertStatus(404);
    }

    public function test_valida_o_arquivo_pelo_conteudo(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $v = $this->veiculo();

        $this->post("/api/conta/veiculos/{$v->id}/fotos", [], $headers + ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors(['foto', 'miniatura']);

        // Extensão de imagem com conteúdo de outra coisa.
        $falsa = $this->arquivo('<?php echo "oi"; ?>', 'golpe.png');
        $this->enviar($headers, $v, ['foto' => $falsa])->assertStatus(422)->assertJsonValidationErrors('foto');

        // SVG (pode carregar script) não entra.
        $svg = $this->arquivo('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'a.svg');
        $this->enviar($headers, $v, ['foto' => $svg])->assertStatus(422);

        // Grande demais.
        $grande = $this->arquivo(base64_decode(self::PNG).str_repeat('0', 5 * 1024 * 1024), 'g.png');
        $this->enviar($headers, $v, ['foto' => $grande])->assertStatus(422)->assertJsonValidationErrors('foto');

        $this->assertSame(0, FotoVeiculoConta::withoutGlobalScopes()->count());
    }

    public function test_limite_de_fotos_por_veiculo(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $v = $this->veiculo();

        for ($i = 0; $i < FotoVeiculoConta::LIMITE_POR_VEICULO; $i++) {
            FotoVeiculoConta::create(['veiculo_conta_id' => $v->id, 'caminho' => "a{$i}.png", 'caminho_miniatura' => "m{$i}.png", 'mime' => 'image/png', 'tamanho' => 1]);
        }

        $this->enviar($headers, $v)->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'máximo'));
    }

    public function test_remove_a_foto_e_os_arquivos(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $v = $this->veiculo();
        $this->enviar($headers, $v);
        $foto = FotoVeiculoConta::withoutGlobalScopes()->first();

        $this->deleteJson("/api/conta/veiculos/{$v->id}/fotos/{$foto->id}", [], $headers)
            ->assertStatus(200)->assertJsonCount(0, 'fotos');

        Storage::disk('local')->assertMissing([$foto->caminho, $foto->caminho_miniatura]);
        $this->deleteJson("/api/conta/veiculos/{$v->id}/fotos/{$foto->id}", [], $headers)->assertStatus(404);
    }

    public function test_remover_o_veiculo_apaga_as_fotos_do_disco(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $v = $this->veiculo();
        $this->enviar($headers, $v);
        $foto = FotoVeiculoConta::withoutGlobalScopes()->first();

        $this->deleteJson("/api/conta/veiculos/{$v->id}", [], $headers)->assertStatus(200);

        Storage::disk('local')->assertMissing([$foto->caminho, $foto->caminho_miniatura]);
        $this->assertSame(0, FotoVeiculoConta::withoutGlobalScopes()->count());
    }

    public function test_uma_conta_nao_ve_nem_mexe_nas_fotos_de_outra(): void
    {
        [, $headersA] = $this->autenticarConta('pessoa');
        $vA = $this->veiculo();
        $this->enviar($headersA, $vA);
        $foto = FotoVeiculoConta::withoutGlobalScopes()->first();

        [, $headersB] = $this->autenticarConta('frota');

        $this->getJson("/api/conta/veiculos/{$vA->id}/fotos", $headersB)->assertStatus(404);
        $this->enviar($headersB, $vA)->assertStatus(404);
        $this->deleteJson("/api/conta/veiculos/{$vA->id}/fotos/{$foto->id}", [], $headersB)->assertStatus(404);

        $this->assertSame(1, FotoVeiculoConta::withoutGlobalScopes()->count());
        Storage::disk('local')->assertExists($foto->caminho);
    }
}
