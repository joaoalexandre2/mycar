<?php

namespace Tests\Feature;

use App\Mail\ConvitePessoaEmail;
use App\Mail\RedefinirSenhaEmail;
use App\Models\Conta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class PessoaFrotaTest extends TestCase
{
    use AutenticaUsuario;
    use RefreshDatabase;

    /** @var array<string, string> */
    private array $headersAdmin;

    private Conta $conta;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->admin, $this->headersAdmin] = $this->autenticarConta('frota');
        $this->conta = Conta::find(app('conta.atual'));
    }

    private User $admin;

    /** @return array{0: User, 1: array<string, string>} */
    private function pessoa(string $papel): array
    {
        [$usuario, $headers] = $this->autenticarConta('frota', $this->conta);
        $usuario->forceFill(['papel_frota' => $papel])->save();
        app()->instance('conta.atual', $this->conta->id);

        return [$usuario, $headers];
    }

    public function test_administrador_lista_as_pessoas_da_frota_com_o_papel(): void
    {
        $this->pessoa('gestor');

        $this->getJson('/api/conta/pessoas', $this->headersAdmin)
            ->assertOk()
            ->assertJsonFragment(['papel' => 'gestor'])
            ->assertJsonFragment(['papel' => 'administrador']);
    }

    public function test_lista_mostra_o_que_cada_pessoa_pode_fazer_com_os_grupos(): void
    {
        [$motorista] = $this->pessoa('motorista');

        $this->postJson('/api/conta/grupos-usuarios', [
            'nome' => 'Apoio', 'permissoes' => ['usos_importar'], 'menus_liberados' => ['cadastros'], 'membros' => [$motorista->id],
        ], $this->headersAdmin)->assertStatus(201);

        $this->getJson('/api/conta/pessoas', $this->headersAdmin)
            ->assertOk()
            ->assertJsonFragment(['id' => $motorista->id, 'permissoes' => ['usos_importar'], 'menus' => ['cadastros']]);
    }

    public function test_gestor_nao_mexe_em_pessoas(): void
    {
        [, $headersGestor] = $this->pessoa('gestor');

        $this->getJson('/api/conta/pessoas', $headersGestor)->assertStatus(403);
        $this->postJson('/api/conta/pessoas', [
            'nome' => 'Novo', 'email' => 'novo@example.test', 'papel' => 'gestor',
        ], $headersGestor)->assertStatus(403);
    }

    public function test_administrador_convida_um_novo_gestor(): void
    {
        $this->postJson('/api/conta/pessoas', [
            'nome' => 'Carla Gestora', 'email' => 'carla@example.test', 'papel' => 'gestor',
        ], $this->headersAdmin)
            ->assertStatus(201)
            ->assertJsonPath('papel', 'gestor');

        $nova = User::where('email', 'carla@example.test')->first();
        $this->assertSame('frota', $nova->perfil);
        $this->assertSame($this->conta->id, $nova->conta_id);
        $this->assertSame('gestor', $nova->papelNaConta());
    }

    // ------------------------------------------------------ e-mail do convite

    private function convidar(string $papel = 'gestor', string $email = 'carla@example.test', ?array $headers = null)
    {
        return $this->postJson('/api/conta/pessoas', [
            'nome' => 'Carla', 'email' => $email, 'papel' => $papel,
        ], $headers ?? $this->headersAdmin);
    }

    /** @return array<string, string> */
    private function queryDoLink(ConvitePessoaEmail $mail): array
    {
        parse_str((string) parse_url($mail->urlDefinirSenha, PHP_URL_QUERY), $query);

        return $query;
    }

    public function test_convite_de_gestor_usa_texto_proprio_sem_a_frase_de_redefinir_senha(): void
    {
        Mail::fake();
        $this->admin->forceFill(['name' => 'Ana Admin'])->save();

        $this->convidar('gestor')->assertStatus(201)->assertJsonPath('papel', 'gestor');

        Mail::assertNotQueued(RedefinirSenhaEmail::class);
        Mail::assertQueuedCount(1);
        Mail::assertQueued(ConvitePessoaEmail::class, function (ConvitePessoaEmail $mail) {
            $html = $mail->render();

            $this->assertTrue($mail->hasTo('carla@example.test'));
            $this->assertSame('Você foi convidado para o MyCar Frota', $mail->subject);
            $this->assertStringContainsString('Olá, Carla!', $html);
            $this->assertStringContainsString('Ana Admin convidou você para usar o MyCar Frota como gestor', $html);
            $this->assertStringContainsString('Criar minha senha', $html);
            $this->assertStringContainsString('Este link expira em 60 minutos', $html);
            $this->assertStringContainsString('Equipe MyCar', $html);
            $this->assertStringContainsString('/redefinir-senha?token=', $html);
            $this->assertStringNotContainsString('Recebemos um pedido', $html);
            $this->assertStringNotContainsString('redefinir a senha', $html);
            $this->assertStringNotContainsString('pode ignorar', $html);
            $this->assertStringNotContainsString('Redefinir senha', $html);
            // Nome da conta/cliente não vai no convite.
            $this->assertStringNotContainsString($this->conta->nome, $html);

            return true;
        });
    }

    public function test_convite_de_administrador_explica_o_papel(): void
    {
        Mail::fake();

        $this->convidar('administrador')->assertStatus(201)->assertJsonPath('papel', 'administrador');

        Mail::assertQueued(ConvitePessoaEmail::class, function (ConvitePessoaEmail $mail) {
            $html = $mail->render();
            $this->assertStringContainsString('como administrador', $html);
            $this->assertStringContainsString('decide quem tem acesso', $html);
            $this->assertStringNotContainsString('pode ignorar', $html);

            return true;
        });
    }

    public function test_link_do_convite_traz_o_marcador_e_define_a_senha_de_ponta_a_ponta(): void
    {
        Mail::fake();
        $this->convidar()->assertStatus(201);

        $query = null;
        Mail::assertQueued(ConvitePessoaEmail::class, function (ConvitePessoaEmail $mail) use (&$query) {
            $query = $this->queryDoLink($mail);

            return true;
        });
        $this->assertSame('1', $query['convite'] ?? null);
        $this->assertSame('carla@example.test', $query['email']);

        $this->postJson('/api/password/redefinir', [
            'token' => $query['token'],
            'email' => 'carla@example.test',
            'password' => 'senha-forte-123',
            'password_confirmation' => 'senha-forte-123',
        ])->assertOk();

        $this->postJson('/api/login', ['email' => 'carla@example.test', 'password' => 'senha-forte-123'])->assertOk();
    }

    public function test_esqueci_minha_senha_continua_sem_o_marcador_e_com_o_texto_de_redefinicao(): void
    {
        Mail::fake();

        $this->postJson('/api/password/esqueci', ['email' => $this->admin->email])->assertOk();

        Mail::assertNotQueued(ConvitePessoaEmail::class);
        Mail::assertQueued(RedefinirSenhaEmail::class, function (RedefinirSenhaEmail $mail) {
            parse_str((string) parse_url($mail->urlRedefinir, PHP_URL_QUERY), $query);
            $this->assertArrayNotHasKey('convite', $query);
            $this->assertStringNotContainsString('convite=1', $mail->urlRedefinir);
            $this->assertStringContainsString('Recebemos um pedido para redefinir a senha', $mail->render());

            return true;
        });
    }

    public function test_convite_recusado_nao_manda_email(): void
    {
        Mail::fake();

        $this->convidar('gestor', $this->admin->email)->assertStatus(422);
        $this->convidar('motorista', 'y@example.test')->assertStatus(422);

        Mail::assertNothingQueued();
    }

    public function test_so_o_administrador_convida_e_ninguem_mais_recebe_email(): void
    {
        Mail::fake();
        [, $headersGestor] = $this->pessoa('gestor');
        [, $headersMotorista] = $this->pessoa('motorista');

        $this->convidar('gestor', 'a@example.test', $headersGestor)->assertStatus(403);
        $this->convidar('administrador', 'b@example.test', $headersMotorista)->assertStatus(403);
        $this->postJson('/api/conta/pessoas', ['nome' => 'C', 'email' => 'c@example.test', 'papel' => 'gestor'])
            ->assertStatus(401);

        Mail::assertNothingQueued();
        $this->assertFalse(User::whereIn('email', ['a@example.test', 'b@example.test', 'c@example.test'])->exists());
    }

    public function test_convite_fica_na_conta_de_quem_convidou(): void
    {
        Mail::fake();
        [, $headersOutra] = $this->autenticarConta('frota');
        $outraConta = Conta::find(app('conta.atual'));

        $this->convidar('gestor', 'deoutra@example.test', $headersOutra)->assertStatus(201);
        app()->instance('conta.atual', $this->conta->id);

        $convidada = User::where('email', 'deoutra@example.test')->firstOrFail();
        $this->assertSame($outraConta->id, $convidada->conta_id);

        // A minha frota não vê a pessoa convidada pela outra.
        $lista = $this->getJson('/api/conta/pessoas', $this->headersAdmin)->assertOk()->json('pessoas');
        $this->assertNotContains('deoutra@example.test', array_column($lista, 'email'));

        // O e-mail vai só para a convidada e não leva o nome de nenhuma das contas.
        Mail::assertQueued(ConvitePessoaEmail::class, function (ConvitePessoaEmail $mail) use ($outraConta) {
            $html = $mail->render();
            $this->assertSame([['name' => null, 'address' => 'deoutra@example.test']], $mail->to);
            $this->assertStringNotContainsString($outraConta->nome, $html);
            $this->assertStringNotContainsString($this->conta->nome, $html);

            return true;
        });

        // E não dá para reconvidar o mesmo e-mail a partir da minha conta.
        $this->convidar('gestor', 'deoutra@example.test')->assertStatus(422);
        $this->assertSame($outraConta->id, $convidada->fresh()->conta_id);
    }

    public function test_convite_nao_repete_email_nem_cria_motorista(): void
    {
        $this->postJson('/api/conta/pessoas', [
            'nome' => 'X', 'email' => $this->admin->email, 'papel' => 'gestor',
        ], $this->headersAdmin)->assertStatus(422);

        $this->postJson('/api/conta/pessoas', [
            'nome' => 'Y', 'email' => 'y@example.test', 'papel' => 'motorista',
        ], $this->headersAdmin)->assertStatus(422);
    }

    public function test_administrador_troca_o_papel_de_uma_pessoa(): void
    {
        [$pessoa] = $this->pessoa('motorista');

        $this->putJson("/api/conta/pessoas/{$pessoa->id}/papel", ['papel' => 'gestor'], $this->headersAdmin)
            ->assertOk()
            ->assertJsonPath('papel', 'gestor');

        $this->assertSame('gestor', $pessoa->fresh()->papelNaConta());
    }

    public function test_mudar_papel_fica_na_trilha_de_acessos(): void
    {
        [$pessoa] = $this->pessoa('motorista');

        $this->putJson("/api/conta/pessoas/{$pessoa->id}/papel", ['papel' => 'gestor'], $this->headersAdmin)->assertOk();

        $this->getJson('/api/conta/acessos-frota', $this->headersAdmin)
            ->assertOk()
            ->assertJsonFragment(['acao' => 'mudou_papel', 'ator' => $this->admin->name])
            ->assertJsonFragment(['de' => 'motorista', 'para' => 'gestor']);
    }

    public function test_so_o_administrador_le_a_trilha_de_acessos(): void
    {
        [, $headersGestor] = $this->pessoa('gestor');

        $this->getJson('/api/conta/acessos-frota', $headersGestor)->assertStatus(403);
        $this->getJson('/api/conta/acessos-frota', $this->headersAdmin)->assertOk();
    }

    public function test_lista_devolve_pessoas_com_os_catalogos_de_uma_vez(): void
    {
        [$motorista] = $this->pessoa('motorista');

        $this->getJson('/api/conta/pessoas', $this->headersAdmin)
            ->assertOk()
            ->assertJsonStructure(['pessoas' => [['id', 'name', 'papel', 'permissoes', 'menus']], 'catalogo', 'menus'])
            ->assertJsonPath('catalogo.grupos', 'Dar acesso a outras pessoas (criar grupos)')
            ->assertJsonFragment(['id' => $motorista->id, 'permissoes' => [], 'menus' => []]);
    }

    public function test_nao_troca_o_proprio_papel(): void
    {
        $this->putJson("/api/conta/pessoas/{$this->admin->id}/papel", ['papel' => 'gestor'], $this->headersAdmin)
            ->assertStatus(422);
    }

    public function test_nao_mexe_em_pessoa_de_outra_frota(): void
    {
        $outraConta = Conta::create(['tipo' => 'frota', 'nome' => 'Outra frota']);
        $estranho = User::factory()->create([
            'oficina_id' => null, 'perfil' => 'frota', 'conta_id' => $outraConta->id, 'papel_frota' => 'motorista',
        ]);

        $this->putJson("/api/conta/pessoas/{$estranho->id}/papel", ['papel' => 'gestor'], $this->headersAdmin)
            ->assertStatus(404);
    }
}
