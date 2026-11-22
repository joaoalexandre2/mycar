<?php

namespace Tests\Feature;

use App\Mail\ConviteMotoristaEmail;
use App\Mail\RedefinirSenhaEmail;
use App\Models\Conta;
use App\Models\Motorista;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

/**
 * Convite do motorista para entrar no sistema e o estado de acesso mostrado
 * na lista de motoristas.
 */
class MotoristaAcessoTest extends TestCase
{
    use AutenticaUsuario;
    use RefreshDatabase;

    /** @var array<string, string> */
    private array $headers;

    private User $gestor;

    private Conta $conta;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        [$this->gestor, $this->headers] = $this->autenticarConta('frota');
        $this->gestor->forceFill(['name' => 'Ana Gestora'])->save();
        $this->conta = Conta::find(app('conta.atual'));
    }

    private function motorista(string $nome = 'Maria'): Motorista
    {
        return Motorista::create(['nome' => $nome, 'cnh_numero' => '123456', 'cnh_validade' => '2035-01-01']);
    }

    private function convidar(Motorista $m, string $email = 'maria@exemplo.com', ?array $headers = null)
    {
        return $this->postJson("/api/conta/motoristas/{$m->id}/acesso", ['email' => $email], $headers ?? $this->headers);
    }

    /** @return array<string, mixed> */
    private function daLista(Motorista $m): array
    {
        $item = collect($this->getJson('/api/conta/motoristas', $this->headers)->assertOk()->json())
            ->firstWhere('id', $m->id);
        $this->assertNotNull($item, 'Motorista não apareceu na lista.');

        return $item;
    }

    // ------------------------------------------------------------- e-mail

    public function test_convite_usa_texto_proprio_sem_a_frase_de_redefinir_senha(): void
    {
        $maria = $this->motorista();

        $this->convidar($maria)->assertStatus(201)
            ->assertJsonPath('message', 'Convite enviado para maria@exemplo.com.')
            ->assertJsonPath('acesso', 'convite_enviado');

        Mail::assertNotQueued(RedefinirSenhaEmail::class);
        Mail::assertQueued(ConviteMotoristaEmail::class, function (ConviteMotoristaEmail $mail) {
            $html = $mail->render();

            $this->assertTrue($mail->hasTo('maria@exemplo.com'));
            $this->assertSame('Você foi convidado para o MyCar Frota', $mail->subject);
            $this->assertStringContainsString('Ana Gestora convidou você para usar o MyCar Frota como motorista', $html);
            $this->assertStringContainsString('Criar minha senha', $html);
            $this->assertStringContainsString('Este link expira em 60 minutos', $html);
            $this->assertStringContainsString('Equipe MyCar', $html);
            $this->assertStringContainsString('/redefinir-senha?token=', $html);
            $this->assertStringNotContainsString('Recebemos um pedido', $html);
            $this->assertStringNotContainsString('redefinir a senha', $html);
            $this->assertStringNotContainsString('pode ignorar', $html);
            // Nome da conta/cliente não vai no convite.
            $this->assertStringNotContainsString($this->conta->nome, $html);

            return true;
        });
    }

    public function test_link_do_convite_define_a_senha_e_a_pessoa_entra(): void
    {
        $maria = $this->motorista();
        $this->convidar($maria)->assertStatus(201);

        $token = null;
        Mail::assertQueued(ConviteMotoristaEmail::class, function (ConviteMotoristaEmail $mail) use (&$token) {
            parse_str((string) parse_url($mail->urlDefinirSenha, PHP_URL_QUERY), $query);
            $token = $query['token'] ?? null;

            return $query['email'] === 'maria@exemplo.com';
        });

        $this->postJson('/api/password/redefinir', [
            'token' => $token,
            'email' => 'maria@exemplo.com',
            'password' => 'senha-forte-123',
            'password_confirmation' => 'senha-forte-123',
        ])->assertOk();

        $this->assertTrue(password_verify('senha-forte-123', User::where('email', 'maria@exemplo.com')->value('password')));
    }

    public function test_redefinicao_de_senha_continua_com_o_texto_de_redefinicao(): void
    {
        $this->postJson('/api/password/esqueci', ['email' => $this->gestor->email])->assertOk();

        Mail::assertNotQueued(ConviteMotoristaEmail::class);
        Mail::assertQueued(RedefinirSenhaEmail::class, function (RedefinirSenhaEmail $mail) {
            $html = $mail->render();
            $this->assertStringContainsString('Recebemos um pedido para redefinir a senha', $html);

            return true;
        });
    }

    // ---------------------------------------------------- estado de acesso

    public function test_lista_informa_o_estado_de_acesso_de_cada_motorista(): void
    {
        $sem = $this->motorista('Sem Login');
        $convidada = $this->motorista('Convidada');
        $ativa = $this->motorista('Ativa');

        $this->convidar($convidada, 'convidada@exemplo.com')->assertStatus(201);
        $this->convidar($ativa, 'ativa@exemplo.com')->assertStatus(201);
        User::where('email', 'ativa@exemplo.com')->firstOrFail()->forceFill(['ultimo_acesso_em' => now()])->save();

        $this->assertSame('sem_acesso', $this->daLista($sem)['acesso']);
        $this->assertNull($this->daLista($sem)['acesso_email']);

        $this->assertSame('convite_enviado', $this->daLista($convidada)['acesso']);
        $this->assertSame('convidada@exemplo.com', $this->daLista($convidada)['acesso_email']);

        $this->assertSame('ativo', $this->daLista($ativa)['acesso']);
        $this->assertSame('ativa@exemplo.com', $this->daLista($ativa)['acesso_email']);

        // A relação com o login não vaza inteira na resposta.
        $this->assertArrayNotHasKey('usuario', $this->daLista($ativa));
    }

    public function test_criar_e_editar_ja_devolvem_o_estado_de_acesso(): void
    {
        $id = $this->postJson('/api/conta/motoristas', ['nome' => 'Novo'], $this->headers)
            ->assertStatus(201)
            ->assertJsonPath('acesso', 'sem_acesso')
            ->json('id');

        $this->putJson("/api/conta/motoristas/{$id}", ['nome' => 'Novo Nome'], $this->headers)
            ->assertOk()
            ->assertJsonPath('acesso', 'sem_acesso');
    }

    public function test_reenvio_do_convite_respeita_o_intervalo_e_nao_vale_para_quem_ja_entra(): void
    {
        $maria = $this->motorista();
        $this->convidar($maria)->assertStatus(201);

        // Logo em seguida: segura para não encher a caixa da pessoa.
        $this->convidar($maria)->assertStatus(429);
        Mail::assertQueuedCount(1);

        $this->travel(2)->minutes();
        $this->convidar($maria)->assertStatus(201)->assertJsonPath('acesso', 'convite_enviado');
        Mail::assertQueuedCount(2);
        $this->assertSame(1, User::where('email', 'maria@exemplo.com')->count());

        // Já entrou no sistema: não manda "convite" de novo.
        User::where('email', 'maria@exemplo.com')->firstOrFail()->forceFill(['ultimo_acesso_em' => now()])->save();
        $this->travel(2)->minutes();
        $this->convidar($maria)->assertStatus(422);
        Mail::assertQueuedCount(2);
    }

    public function test_email_invalido_e_recusado(): void
    {
        $this->convidar($this->motorista(), 'nao-e-email')->assertStatus(422)->assertJsonValidationErrors('email');
        Mail::assertNothingQueued();
    }

    // ------------------------------------------------- isolamento por conta

    public function test_lista_e_convite_nao_atravessam_contas(): void
    {
        $minha = $this->motorista('Minha');

        // Outra frota com motorista convidado.
        [, $headersOutra] = $this->autenticarConta('frota');
        $deOutra = $this->motorista('Da Outra');
        $this->convidar($deOutra, 'outra@exemplo.com', $headersOutra)->assertStatus(201);
        $loginDeOutra = User::where('email', 'outra@exemplo.com')->firstOrFail();
        app()->instance('conta.atual', $this->conta->id);

        $lista = $this->getJson('/api/conta/motoristas', $this->headers)->assertOk()->json();
        $this->assertSame(['Minha'], array_column($lista, 'nome'));
        $this->assertStringNotContainsString('outra@exemplo.com', json_encode($lista));

        // Não convida motorista de outra conta.
        $this->convidar($deOutra, 'x@exemplo.com')->assertStatus(404);

        // Um user_id de outra conta gravado por engano não conta como acesso nem mostra o e-mail.
        $minha->forceFill(['user_id' => $loginDeOutra->id])->save();
        $item = $this->daLista($minha);
        $this->assertSame('sem_acesso', $item['acesso']);
        $this->assertNull($item['acesso_email']);

        // E o convite não reaproveita (nem manda e-mail para) o login da outra conta.
        $this->convidar($minha, 'outra@exemplo.com')->assertStatus(422);
        $this->assertSame($deOutra->conta_id, $loginDeOutra->fresh()->conta_id);
    }

    // ---------------------------------------------------------- permissões

    public function test_motorista_e_visitante_nao_veem_nem_convidam(): void
    {
        $maria = $this->motorista();

        [$motoristaLogado, $headersMotorista] = $this->autenticarConta('frota', $this->conta);
        $motoristaLogado->forceFill(['papel_frota' => 'motorista'])->save();

        $this->getJson('/api/conta/motoristas', $headersMotorista)->assertStatus(403);
        $this->convidar($maria, 'maria@exemplo.com', $headersMotorista)->assertStatus(403);

        $this->getJson('/api/conta/motoristas')->assertStatus(401);
        $this->postJson("/api/conta/motoristas/{$maria->id}/acesso", ['email' => 'maria@exemplo.com'])->assertStatus(401);

        Mail::assertNothingQueued();
        $this->assertNull($maria->fresh()->user_id);
    }

    public function test_gestor_convida(): void
    {
        [$gestor, $headersGestor] = $this->autenticarConta('frota', $this->conta);
        $gestor->forceFill(['papel_frota' => 'gestor'])->save();

        $this->convidar($this->motorista(), 'maria@exemplo.com', $headersGestor)->assertStatus(201);
        Mail::assertQueued(ConviteMotoristaEmail::class, fn (ConviteMotoristaEmail $m) => $m->quemConvidou === $gestor->name);
    }
}
