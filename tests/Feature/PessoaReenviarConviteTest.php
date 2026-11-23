<?php

namespace Tests\Feature;

use App\Mail\ConviteMotoristaEmail;
use App\Mail\ConvitePessoaEmail;
use App\Mail\RedefinirSenhaEmail;
use App\Models\AcessoFrotaEvento;
use App\Models\Conta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

/**
 * Reenvio do convite de quem ainda não entrou (Pessoas da frota) e o estado de
 * acesso de cada pessoa na lista. Só o administrador, só na própria conta.
 */
class PessoaReenviarConviteTest extends TestCase
{
    use AutenticaUsuario;
    use RefreshDatabase;

    private User $admin;

    /** @var array<string, string> */
    private array $headersAdmin;

    private Conta $conta;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        [$this->admin, $this->headersAdmin] = $this->autenticarConta('frota');
        $this->admin->forceFill(['name' => 'Ana Admin', 'ultimo_acesso_em' => now()])->save();
        $this->conta = Conta::find(app('conta.atual'));
    }

    /** Convida pela rota de sempre e devolve a pessoa criada. */
    private function convidada(string $papel = 'gestor', string $email = 'carla@example.test'): User
    {
        $this->postJson('/api/conta/pessoas', [
            'nome' => 'Carla', 'email' => $email, 'papel' => $papel,
        ], $this->headersAdmin)->assertStatus(201);

        return User::where('email', $email)->firstOrFail();
    }

    /** @param  array<string, string>|null  $headers */
    private function reenviar(User $pessoa, ?array $headers = null)
    {
        return $this->postJson("/api/conta/pessoas/{$pessoa->id}/convite", [], $headers ?? $this->headersAdmin);
    }

    /** @return array{0: User, 1: array<string, string>} */
    private function logado(string $papel): array
    {
        [$usuario, $headers] = $this->autenticarConta('frota', $this->conta);
        $usuario->forceFill(['papel_frota' => $papel, 'ultimo_acesso_em' => now()])->save();
        app()->instance('conta.atual', $this->conta->id);

        return [$usuario, $headers];
    }

    /** @return array<string, string> */
    private function queryDoLink(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query;
    }

    /** @return list<array<string, mixed>> */
    private function pessoas(): array
    {
        return $this->getJson('/api/conta/pessoas', $this->headersAdmin)->assertOk()->json('pessoas');
    }

    private function eventosDeReenvio(): int
    {
        return AcessoFrotaEvento::withoutGlobalScopes()->where('acao', 'reenviou_convite')->count();
    }

    // ------------------------------------------------------------ sucesso

    public function test_reenvia_o_convite_com_link_novo_e_texto_de_convite(): void
    {
        $carla = $this->convidada();

        $tokenAntigo = null;
        Mail::assertQueued(ConvitePessoaEmail::class, function (ConvitePessoaEmail $mail) use (&$tokenAntigo) {
            $tokenAntigo = $this->queryDoLink($mail->urlDefinirSenha)['token'];

            return true;
        });

        $this->travel(2)->minutes();

        $this->reenviar($carla)
            ->assertOk()
            ->assertExactJson([
                'message' => 'Convite reenviado para carla@example.test.',
                'id' => $carla->id,
                'acesso' => 'convite_enviado',
            ]);

        Mail::assertNotQueued(RedefinirSenhaEmail::class);
        Mail::assertQueuedCount(2);

        $reenvio = Mail::queued(ConvitePessoaEmail::class)->last();
        $html = $reenvio->render();

        $this->assertTrue($reenvio->hasTo('carla@example.test'));
        $this->assertSame('Você foi convidado para o MyCar Frota', $reenvio->subject);
        $this->assertStringContainsString('Ana Admin convidou você para usar o MyCar Frota como gestor', $html);
        $this->assertStringContainsString('Criar minha senha', $html);
        $this->assertStringNotContainsString('Recebemos um pedido', $html);
        $this->assertStringNotContainsString($this->conta->nome, $html);

        $query = $this->queryDoLink($reenvio->urlDefinirSenha);
        $tokenNovo = $query['token'];
        $this->assertSame('1', $query['convite'] ?? null);
        $this->assertNotSame($tokenAntigo, $tokenNovo);

        // O link anterior deixa de valer; o novo define a senha e a pessoa entra.
        $definir = fn (string $token) => $this->postJson('/api/password/redefinir', [
            'token' => $token,
            'email' => 'carla@example.test',
            'password' => 'senha-forte-123',
            'password_confirmation' => 'senha-forte-123',
        ]);

        $definir($tokenAntigo)->assertStatus(422);
        $definir($tokenNovo)->assertOk();
        $this->postJson('/api/login', ['email' => 'carla@example.test', 'password' => 'senha-forte-123'])->assertOk();

        $this->assertNotNull($carla->fresh()->ultimo_acesso_em);
    }

    public function test_administrador_convidado_recebe_o_texto_de_administrador(): void
    {
        $bia = $this->convidada('administrador', 'bia@example.test');
        $this->travel(2)->minutes();

        $this->reenviar($bia)->assertOk();

        $html = Mail::queued(ConvitePessoaEmail::class)->last()->render();
        $this->assertStringContainsString('como administrador', $html);
    }

    public function test_pessoa_com_papel_de_motorista_recebe_o_convite_de_motorista(): void
    {
        $carla = $this->convidada();
        $carla->forceFill(['papel_frota' => 'motorista'])->save();
        $this->travel(2)->minutes();

        $this->reenviar($carla)->assertOk();

        Mail::assertQueued(ConviteMotoristaEmail::class, fn (ConviteMotoristaEmail $mail) => $mail->hasTo('carla@example.test'));
        Mail::assertQueuedCount(2);
    }

    // ------------------------------------------------------- recusas (sem e-mail)

    public function test_dentro_de_um_minuto_responde_429_e_nao_manda_outro_email(): void
    {
        $carla = $this->convidada();

        $this->reenviar($carla)
            ->assertStatus(429)
            ->assertJsonPath('message', 'Acabamos de enviar um convite para esta pessoa. Aguarde um minuto para reenviar.');

        Mail::assertQueuedCount(1);
        $this->assertSame(0, $this->eventosDeReenvio());

        $this->travel(2)->minutes();
        $this->reenviar($carla)->assertOk();
        $this->reenviar($carla)->assertStatus(429);
        Mail::assertQueuedCount(2);
    }

    public function test_quem_ja_entrou_nao_recebe_convite_422(): void
    {
        $carla = $this->convidada();
        $carla->forceFill(['ultimo_acesso_em' => now()])->save();
        $this->travel(2)->minutes();

        $this->reenviar($carla)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Esta pessoa já entra no sistema. Se esqueceu a senha, ela pode usar "Esqueci minha senha".');

        // Nem a si mesmo (está logado, então já entrou).
        $this->admin->forceFill(['ultimo_acesso_em' => null])->save();
        $this->reenviar($this->admin)->assertStatus(422);

        Mail::assertQueuedCount(1);
        $this->assertSame(0, $this->eventosDeReenvio());
    }

    // ------------------------------------------------------------ isolamento

    public function test_pessoa_de_outra_conta_ou_fora_da_frota_e_404(): void
    {
        // Outra frota com uma pessoa convidada que ainda não entrou.
        [, $headersOutra] = $this->autenticarConta('frota');
        $this->postJson('/api/conta/pessoas', [
            'nome' => 'Estranha', 'email' => 'estranha@example.test', 'papel' => 'gestor',
        ], $headersOutra)->assertStatus(201);
        $estranha = User::where('email', 'estranha@example.test')->firstOrFail();
        app()->instance('conta.atual', $this->conta->id);

        // Login da mesma conta, mas de outro perfil: não é pessoa da frota.
        $foraDaFrota = User::factory()->create([
            'oficina_id' => null, 'perfil' => 'pessoa', 'conta_id' => $this->conta->id,
        ]);

        $this->travel(2)->minutes();

        foreach ([$estranha, $foraDaFrota] as $alvo) {
            $this->reenviar($alvo)
                ->assertStatus(404)
                ->assertJsonPath('message', 'Pessoa não encontrada na frota.');
        }
        $this->postJson('/api/conta/pessoas/999999/convite', [], $this->headersAdmin)->assertStatus(404);

        Mail::assertQueuedCount(1); // só o convite original da outra frota
        $this->assertSame(0, $this->eventosDeReenvio());

        // A lista da minha frota não mostra a pessoa da outra nem o estado dela.
        $this->assertNotContains('estranha@example.test', array_column($this->pessoas(), 'email'));
        $this->assertNotContains($foraDaFrota->id, array_column($this->pessoas(), 'id'));
    }

    // ----------------------------------------------------------- permissões

    public function test_gestor_e_motorista_403_e_sem_login_401(): void
    {
        $carla = $this->convidada();
        $this->travel(2)->minutes();

        [, $headersGestor] = $this->logado('gestor');
        [, $headersMotorista] = $this->logado('motorista');

        $this->reenviar($carla, $headersGestor)->assertStatus(403);
        $this->reenviar($carla, $headersMotorista)->assertStatus(403);
        $this->postJson("/api/conta/pessoas/{$carla->id}/convite")->assertStatus(401);

        Mail::assertQueuedCount(1);
        $this->assertSame(0, $this->eventosDeReenvio());
    }

    // ----------------------------------------------------------------- trilha

    public function test_reenvio_fica_na_trilha_de_acessos_so_da_propria_conta(): void
    {
        $carla = $this->convidada();
        $this->travel(2)->minutes();
        $this->reenviar($carla)->assertOk();

        $this->getJson('/api/conta/acessos-frota', $this->headersAdmin)
            ->assertOk()
            ->assertJsonFragment([
                'acao' => 'reenviou_convite',
                'ator' => 'Ana Admin',
                'alvo_tipo' => 'pessoa',
                'alvo_id' => $carla->id,
                'detalhes' => ['nome' => 'Carla', 'papel' => 'gestor'],
            ]);

        // A outra frota não vê o evento.
        [, $headersOutra] = $this->autenticarConta('frota');
        $trilhaDaOutra = $this->getJson('/api/conta/acessos-frota', $headersOutra)->assertOk()->json();
        $this->assertNotContains('reenviou_convite', array_column($trilhaDaOutra, 'acao'));
    }

    // ---------------------------------------------------------- estado no GET

    public function test_lista_informa_o_estado_de_acesso_de_cada_pessoa(): void
    {
        $carla = $this->convidada();
        $dani = $this->convidada('administrador', 'dani@example.test');
        $dani->forceFill(['ultimo_acesso_em' => now()])->save();

        $porId = collect($this->pessoas())->keyBy('id');

        $this->assertSame('convite_enviado', $porId[$carla->id]['acesso']);
        $this->assertSame('ativo', $porId[$dani->id]['acesso']);
        $this->assertSame('ativo', $porId[$this->admin->id]['acesso']);

        // Aditivo: os campos de antes continuam lá e nada de data de acesso vaza.
        $this->assertSame(
            ['id', 'name', 'email', 'papel', 'permissoes', 'menus', 'motorista', 'acesso'],
            array_keys($porId[$carla->id]),
        );

        // A resposta do convite também já traz o estado.
        $this->postJson('/api/conta/pessoas', [
            'nome' => 'Eva', 'email' => 'eva@example.test', 'papel' => 'gestor',
        ], $this->headersAdmin)->assertStatus(201)->assertJsonPath('acesso', 'convite_enviado');
    }
}
