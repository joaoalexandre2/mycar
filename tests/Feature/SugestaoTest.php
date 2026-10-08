<?php

namespace Tests\Feature;

use App\Models\Sugestao;
use App\Models\SugestaoAnexo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class SugestaoTest extends TestCase
{
    use RefreshDatabase;
    use AutenticaUsuario;

    private function dados(array $extra = []): array
    {
        return array_merge([
            'categoria' => 'nova_funcao',
            'titulo' => 'Avisar a troca de pneus',
            'descricao' => 'Seria bom receber um aviso quando os pneus chegarem perto dos 40 mil km.',
        ], $extra);
    }

    /** @return array{0: User, 1: array<string, string>} */
    private function autenticarEquipe(): array
    {
        [$admin, $headers] = $this->autenticar();
        $admin->forceFill(['is_super_admin' => true])->save();

        return [$admin, $headers];
    }

    public function test_exige_autenticacao(): void
    {
        $this->getJson('/api/sugestoes')->assertStatus(401);
        $this->postJson('/api/sugestoes', $this->dados())->assertStatus(401);
    }

    public function test_qualquer_perfil_envia_e_ve_as_proprias(): void
    {
        [, $oficina] = $this->autenticar();
        [, $pessoa] = $this->autenticarConta('pessoa');

        $this->postJson('/api/sugestoes', $this->dados(), $oficina)
            ->assertStatus(201)->assertJsonPath('status', 'nova')->assertJsonPath('titulo', 'Avisar a troca de pneus');
        $this->postJson('/api/sugestoes', $this->dados(['titulo' => 'Exportar relatório em PDF']), $pessoa)->assertStatus(201);

        $this->getJson('/api/sugestoes', $oficina)->assertStatus(200)->assertJsonCount(1)->assertJsonPath('0.titulo', 'Avisar a troca de pneus');
        $this->getJson('/api/sugestoes', $pessoa)->assertJsonCount(1)->assertJsonPath('0.titulo', 'Exportar relatório em PDF');

        $this->assertSame(['oficina', 'pessoa'], Sugestao::orderBy('id')->pluck('perfil')->all());
    }

    public function test_valida_o_envio(): void
    {
        [, $headers] = $this->autenticar();

        $this->postJson('/api/sugestoes', [], $headers)
            ->assertStatus(422)->assertJsonValidationErrors(['categoria', 'titulo', 'descricao']);
        $this->postJson('/api/sugestoes', $this->dados(['categoria' => 'xingamento']), $headers)->assertStatus(422)->assertJsonValidationErrors('categoria');
        $this->postJson('/api/sugestoes', $this->dados(['titulo' => 'ab']), $headers)->assertStatus(422)->assertJsonValidationErrors('titulo');
        $this->postJson('/api/sugestoes', $this->dados(['descricao' => 'curta']), $headers)->assertStatus(422)->assertJsonValidationErrors('descricao');
        $this->postJson('/api/sugestoes', $this->dados(['descricao' => str_repeat('a', 2001)]), $headers)->assertStatus(422);
    }

    public function test_o_usuario_nao_ve_a_resposta_de_outro_nem_as_rotas_da_equipe(): void
    {
        [, $a] = $this->autenticar();
        [, $b] = $this->autenticar();
        $this->postJson('/api/sugestoes', $this->dados(), $a)->assertStatus(201);

        $this->getJson('/api/sugestoes', $b)->assertJsonCount(0);
        $this->getJson('/api/admin/sugestoes', $a)->assertStatus(403);
        $this->putJson('/api/admin/sugestoes/1', ['status' => 'feita'], $a)->assertStatus(403);
    }

    public function test_a_equipe_ve_todas_com_o_autor_e_filtra_por_status(): void
    {
        [, $usuario] = $this->autenticarConta('frota');
        $this->postJson('/api/sugestoes', $this->dados(), $usuario)->assertStatus(201);
        $this->postJson('/api/sugestoes', $this->dados(['titulo' => 'Outra ideia boa']), $usuario)->assertStatus(201);
        Sugestao::where('titulo', 'Outra ideia boa')->update(['status' => 'planejada']);

        [, $equipe] = $this->autenticarEquipe();

        $todas = $this->getJson('/api/admin/sugestoes', $equipe)->assertStatus(200)->assertJsonCount(2);
        $this->assertSame('frota', $todas->json('0.autor.perfil'));
        $this->assertNotEmpty($todas->json('0.autor.nome'));

        $this->getJson('/api/admin/sugestoes?status=planejada', $equipe)->assertJsonCount(1)->assertJsonPath('0.titulo', 'Outra ideia boa');
        $this->getJson('/api/admin/sugestoes?status=invalido', $equipe)->assertStatus(422);
    }

    public function test_a_equipe_responde_e_o_usuario_ve_o_retorno(): void
    {
        [, $usuario] = $this->autenticar();
        $id = $this->postJson('/api/sugestoes', $this->dados(), $usuario)->json('id');

        [, $equipe] = $this->autenticarEquipe();

        $this->putJson("/api/admin/sugestoes/{$id}", ['status' => 'planejada', 'resposta' => 'Boa ideia, entra no próximo mês.'], $equipe)
            ->assertStatus(200)->assertJsonPath('status', 'planejada');

        $this->getJson('/api/sugestoes', $usuario)
            ->assertJsonPath('0.status', 'planejada')->assertJsonPath('0.resposta', 'Boa ideia, entra no próximo mês.');

        $this->putJson("/api/admin/sugestoes/{$id}", ['status' => 'voando'], $equipe)->assertStatus(422);
        $this->putJson('/api/admin/sugestoes/999', ['status' => 'feita'], $equipe)->assertStatus(404);
    }

    public function test_a_lista_do_usuario_nao_expoe_dados_do_autor(): void
    {
        [, $usuario] = $this->autenticar();
        $this->postJson('/api/sugestoes', $this->dados(), $usuario)->assertStatus(201);

        $this->assertArrayNotHasKey('autor', $this->getJson('/api/sugestoes', $usuario)->json('0'));
    }
    // ----------------------------------------------------------- imagens anexadas

    /** PNG 1x1 válido. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    /** Arquivo de verdade: o tipo é detectado pelo CONTEÚDO (o UploadedFile::fake adivinha pela extensão). */
    private function arquivo(string $conteudo, string $nome): UploadedFile
    {
        $caminho = tempnam(sys_get_temp_dir(), 'mycar');
        file_put_contents($caminho, $conteudo);

        return new UploadedFile($caminho, $nome, null, null, true);
    }

    private function enviarComImagens(array $headers, array $imagens, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->post('/api/sugestoes', $this->dados($extra) + ['imagens' => $imagens], $headers + ['Accept' => 'application/json']);
    }

    public function test_anexa_imagens_e_o_dono_ve_com_link_assinado(): void
    {
        Storage::fake('local');
        [, $headers] = $this->autenticar();

        $r = $this->enviarComImagens($headers, [
            $this->arquivo(base64_decode(self::PNG), 'print1.png'),
            $this->arquivo(base64_decode(self::PNG), 'print2.png'),
        ])->assertStatus(201);

        $r->assertJsonCount(2, 'anexos');
        $this->assertStringContainsString('/api/sugestoes/anexos/', $r->json('anexos.0.url'));
        $this->assertStringContainsString('signature=', $r->json('anexos.0.url'));

        $anexo = SugestaoAnexo::first();
        Storage::disk('local')->assertExists($anexo->caminho);
        $this->assertSame('image/png', $anexo->mime);

        $this->getJson('/api/sugestoes', $headers)->assertJsonCount(2, '0.anexos');
        $this->get($r->json('anexos.0.url'))->assertStatus(200)->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_sugestao_sem_imagem_continua_valendo(): void
    {
        [, $headers] = $this->autenticar();

        $this->postJson('/api/sugestoes', $this->dados(), $headers)->assertStatus(201)->assertJsonCount(0, 'anexos');
    }

    public function test_valida_as_imagens_pelo_conteudo_e_o_limite(): void
    {
        Storage::fake('local');
        [, $headers] = $this->autenticar();

        $png = fn () => $this->arquivo(base64_decode(self::PNG), 'a.png');

        $this->enviarComImagens($headers, [$png(), $png(), $png(), $png()])
            ->assertStatus(422)->assertJsonValidationErrors('imagens');

        $this->enviarComImagens($headers, [$this->arquivo('<?php echo "oi"; ?>', 'golpe.png')])
            ->assertStatus(422)->assertJsonValidationErrors('imagens.0');
        $this->enviarComImagens($headers, [$this->arquivo('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'a.svg')])
            ->assertStatus(422);
        $this->enviarComImagens($headers, [$this->arquivo(base64_decode(self::PNG).str_repeat('0', 4 * 1024 * 1024), 'grande.png')])
            ->assertStatus(422)->assertJsonValidationErrors('imagens.0');

        $this->assertSame(0, Sugestao::count());
        $this->assertSame(0, SugestaoAnexo::count());
    }

    public function test_a_equipe_ve_os_anexos_e_o_link_adulterado_e_barrado(): void
    {
        Storage::fake('local');
        [, $usuario] = $this->autenticar();
        $url = $this->enviarComImagens($usuario, [$this->arquivo(base64_decode(self::PNG), 'a.png')])->json('anexos.0.url');

        [, $equipe] = $this->autenticarEquipe();
        $this->getJson('/api/admin/sugestoes', $equipe)->assertJsonCount(1, '0.anexos');

        $this->get(strtok($url, '?'))->assertStatus(403);
        $this->get(preg_replace('/signature=[a-f0-9]+/', 'signature=000', $url))->assertStatus(403);

        $this->travel(3)->hours();
        $this->get($url)->assertStatus(403);
    }

    public function test_outro_usuario_nao_recebe_os_anexos_de_quem_enviou(): void
    {
        Storage::fake('local');
        [, $a] = $this->autenticar();
        [, $b] = $this->autenticar();
        $this->enviarComImagens($a, [$this->arquivo(base64_decode(self::PNG), 'a.png')])->assertStatus(201);

        $this->getJson('/api/sugestoes', $b)->assertJsonCount(0);
    }

    public function test_apagar_o_anexo_remove_o_arquivo(): void
    {
        Storage::fake('local');
        [, $headers] = $this->autenticar();
        $this->enviarComImagens($headers, [$this->arquivo(base64_decode(self::PNG), 'a.png')])->assertStatus(201);

        $anexo = SugestaoAnexo::first();
        $anexo->delete();

        Storage::disk('local')->assertMissing($anexo->caminho);
    }
}
