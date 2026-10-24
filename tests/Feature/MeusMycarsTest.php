<?php

namespace Tests\Feature;

use App\Models\Acesso;
use App\Models\Cliente;
use App\Models\Conta;
use App\Models\Oficina;
use App\Models\TokenAcesso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class MeusMycarsTest extends TestCase
{
    use AutenticaUsuario;
    use RefreshDatabase;

    /** @var array<string, string> */
    private array $headers;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        // Quem começou com uma oficina (o MyCar principal).
        [$this->usuario, $this->headers] = $this->autenticar();
        $this->usuario->forceFill(['name' => 'Ana Souza', 'email_verified_at' => now()])->save();
    }

    private function veiculo(string $placa = 'ABC1D23'): array
    {
        return ['placa' => $placa, 'marca' => 'Fiat', 'modelo' => 'Uno', 'ano' => 2018];
    }

    /** Abre outra sessão (outro aparelho) do mesmo usuário, no MyCar principal. */
    private function outroAparelho(): array
    {
        $token = Str::random(60);
        $this->abrirSessao($this->usuario, $token);

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_exige_autenticacao(): void
    {
        $this->getJson('/api/meus-mycars')->assertStatus(401);
        $this->postJson('/api/meus-mycars', ['perfil' => 'frota', 'nome' => 'X'])->assertStatus(401);
        $this->postJson('/api/meus-mycars/principal/ativar')->assertStatus(401);
    }

    public function test_lista_o_mycar_principal_e_o_que_ainda_pode_criar(): void
    {
        $r = $this->getJson('/api/meus-mycars', $this->headers)->assertStatus(200);

        $r->assertJsonPath('mycars.0.id', 'principal')
            ->assertJsonPath('mycars.0.perfil', 'oficina')
            ->assertJsonPath('mycars.0.atual', true)
            ->assertJsonCount(1, 'mycars');

        $this->assertEqualsCanonicalizing(['pessoa', 'frota'], $r->json('pode_criar'));
    }

    public function test_cria_um_mycar_pessoa_e_ja_abre_nele(): void
    {
        $r = $this->postJson('/api/meus-mycars', ['perfil' => 'pessoa'], $this->headers)
            ->assertStatus(201)
            ->assertJsonPath('user.perfil', 'pessoa')
            ->assertJsonPath('user.conta', 'Ana Souza')
            ->assertJsonPath('user.oficina', null)
            ->assertJsonCount(2, 'mycars');

        $this->assertSame(['frota'], $r->json('pode_criar'));

        // Neste aparelho agora valem as rotas de pessoa e não as da oficina.
        $this->getJson('/api/me', $this->headers)->assertJsonPath('perfil', 'pessoa');
        $this->postJson('/api/conta/veiculos', $this->veiculo(), $this->headers)->assertStatus(201);
        $this->getJson('/api/clientes', $this->headers)->assertStatus(403);
    }

    public function test_cria_um_mycar_frota_com_o_nome_da_empresa_e_papel_de_administrador(): void
    {
        $this->postJson('/api/meus-mycars', ['perfil' => 'frota', 'nome' => 'Transportes Silva'], $this->headers)
            ->assertStatus(201)
            ->assertJsonPath('user.perfil', 'frota')
            ->assertJsonPath('user.conta', 'Transportes Silva')
            ->assertJsonPath('user.papel', 'administrador');

        $conta = Conta::where('nome', 'Transportes Silva')->firstOrFail();
        $this->assertSame('frota', $conta->tipo);
        $this->assertSame($conta->id, Acesso::where('user_id', $this->usuario->id)->where('perfil', 'frota')->value('conta_id'));
    }

    public function test_cria_uma_segunda_oficina_nao_e_possivel_mas_frota_exige_nome(): void
    {
        $this->postJson('/api/meus-mycars', ['perfil' => 'oficina', 'nome' => 'Outra'], $this->headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors('perfil');

        $this->postJson('/api/meus-mycars', ['perfil' => 'frota'], $this->headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors('nome');

        $this->postJson('/api/meus-mycars', ['perfil' => 'invalido'], $this->headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors('perfil');

        $this->assertSame(0, Acesso::count());
    }

    public function test_nao_cria_dois_mycar_do_mesmo_tipo(): void
    {
        $this->postJson('/api/meus-mycars', ['perfil' => 'pessoa'], $this->headers)->assertStatus(201);

        $this->postJson('/api/meus-mycars', ['perfil' => 'pessoa'], $this->headers)
            ->assertStatus(422)
            ->assertJsonPath('errors.perfil.0', 'Você já tem um MyCar desse tipo.');

        $this->assertSame(1, Acesso::where('user_id', $this->usuario->id)->count());
    }

    public function test_alterna_entre_os_mycar_e_volta_ao_principal(): void
    {
        $this->postJson('/api/meus-mycars', ['perfil' => 'frota', 'nome' => 'Frota Ana'], $this->headers)->assertStatus(201);
        $frota = Acesso::where('perfil', 'frota')->firstOrFail();

        $this->postJson('/api/meus-mycars', ['perfil' => 'pessoa'], $this->headers)->assertStatus(201);
        $pessoa = Acesso::where('perfil', 'pessoa')->firstOrFail();

        $this->postJson("/api/meus-mycars/{$frota->id}/ativar", [], $this->headers)
            ->assertStatus(200)
            ->assertJsonPath('user.perfil', 'frota');
        $this->getJson('/api/conta/centros-custo', $this->headers)->assertStatus(200);

        $this->postJson("/api/meus-mycars/{$pessoa->id}/ativar", [], $this->headers)
            ->assertStatus(200)
            ->assertJsonPath('user.perfil', 'pessoa');
        $this->getJson('/api/conta/centros-custo', $this->headers)->assertStatus(403);

        $this->postJson('/api/meus-mycars/principal/ativar', [], $this->headers)
            ->assertStatus(200)
            ->assertJsonPath('user.perfil', 'oficina')
            ->assertJsonPath('user.conta', null);
        $this->getJson('/api/clientes', $this->headers)->assertStatus(200);
        $this->getJson('/api/conta/veiculos', $this->headers)->assertStatus(403);
    }

    public function test_cada_mycar_enxerga_so_os_proprios_dados(): void
    {
        Cliente::factory()->create(['nome' => 'Cliente da oficina']);

        $this->postJson('/api/meus-mycars', ['perfil' => 'pessoa'], $this->headers)->assertStatus(201);
        $this->postJson('/api/conta/veiculos', $this->veiculo('AAA1B11'), $this->headers)->assertStatus(201);

        $acessoFrota = $this->postJson('/api/meus-mycars', ['perfil' => 'frota', 'nome' => 'Frota Ana'], $this->headers)
            ->assertStatus(201);
        $this->assertSame('frota', $acessoFrota->json('user.perfil'));

        // A frota nasce vazia: não herda os veículos da conta pessoa.
        $this->getJson('/api/conta/veiculos', $this->headers)->assertStatus(200)->assertJsonCount(0, 'data');

        $pessoa = Acesso::where('perfil', 'pessoa')->firstOrFail();
        $this->postJson("/api/meus-mycars/{$pessoa->id}/ativar", [], $this->headers)->assertStatus(200);
        $this->getJson('/api/conta/veiculos', $this->headers)->assertStatus(200)->assertJsonCount(1, 'data');

        // E a oficina continua com o cliente dela.
        $this->postJson('/api/meus-mycars/principal/ativar', [], $this->headers)->assertStatus(200);
        $this->getJson('/api/clientes', $this->headers)->assertStatus(200)->assertJsonFragment(['nome' => 'Cliente da oficina']);
    }

    public function test_a_troca_vale_so_para_o_aparelho_em_que_foi_feita(): void
    {
        $celular = $this->outroAparelho();

        $this->postJson('/api/meus-mycars', ['perfil' => 'frota', 'nome' => 'Frota Ana'], $this->headers)->assertStatus(201);

        // O computador foi para a frota; o celular continua na oficina.
        $this->getJson('/api/me', $this->headers)->assertJsonPath('perfil', 'frota');
        $this->getJson('/api/me', $celular)->assertJsonPath('perfil', 'oficina');
        $this->getJson('/api/clientes', $celular)->assertStatus(200);
    }

    public function test_um_novo_login_sempre_comeca_no_mycar_principal(): void
    {
        $this->usuario->forceFill(['password' => 'senha-segura-123'])->save();
        $this->postJson('/api/meus-mycars', ['perfil' => 'pessoa'], $this->headers)->assertStatus(201);

        $r = $this->postJson('/api/login', ['email' => $this->usuario->email, 'password' => 'senha-segura-123'])
            ->assertStatus(200)
            ->assertJsonPath('user.perfil', 'oficina');

        $this->getJson('/api/clientes', ['Authorization' => 'Bearer ' . $r->json('token')])->assertStatus(200);
    }

    public function test_nao_abre_o_mycar_de_outra_pessoa(): void
    {
        $oficinaDela = Oficina::factory()->create();
        $outra = User::factory()->create(['oficina_id' => $oficinaDela->id]);
        $contaDela = Conta::create(['tipo' => 'pessoa', 'nome' => 'Outra Pessoa']);
        $acessoDela = Acesso::create(['user_id' => $outra->id, 'perfil' => 'pessoa', 'conta_id' => $contaDela->id]);

        $this->postJson("/api/meus-mycars/{$acessoDela->id}/ativar", [], $this->headers)->assertStatus(404);

        $this->getJson('/api/me', $this->headers)->assertJsonPath('perfil', 'oficina');
        $this->assertNull(TokenAcesso::where('user_id', $this->usuario->id)->value('acesso_id'));
    }

    public function test_sessao_apontando_para_acesso_de_outra_pessoa_cai_no_principal(): void
    {
        $outra = User::factory()->create(['oficina_id' => Oficina::factory()->create()->id]);
        $contaDela = Conta::create(['tipo' => 'pessoa', 'nome' => 'Outra Pessoa']);
        $acessoDela = Acesso::create(['user_id' => $outra->id, 'perfil' => 'pessoa', 'conta_id' => $contaDela->id]);

        // Um token adulterado no banco não leva a conta de ninguém.
        TokenAcesso::where('user_id', $this->usuario->id)->update(['acesso_id' => $acessoDela->id]);

        $this->getJson('/api/me', $this->headers)->assertJsonPath('perfil', 'oficina');
        $this->getJson('/api/conta/veiculos', $this->headers)->assertStatus(403);
    }

    public function test_mycar_principal_inexistente_nao_abre(): void
    {
        // Usuário de conta pessoa: não tem oficina principal para abrir.
        [, $headers] = $this->autenticarConta('pessoa');

        $this->postJson('/api/meus-mycars', ['perfil' => 'oficina', 'nome' => 'Minha oficina'], $headers)->assertStatus(201);

        $this->postJson('/api/meus-mycars/principal/ativar', [], $headers)
            ->assertStatus(200)
            ->assertJsonPath('user.perfil', 'pessoa');

        $this->getJson('/api/meus-mycars', $headers)
            ->assertJsonPath('mycars.0.perfil', 'pessoa')
            ->assertJsonPath('mycars.1.perfil', 'oficina');
    }

    public function test_alterar_dados_dentro_de_outro_mycar_nao_muda_o_mycar_principal(): void
    {
        $this->postJson('/api/meus-mycars', ['perfil' => 'frota', 'nome' => 'Frota Ana'], $this->headers)->assertStatus(201);

        // Qualquer update do usuário feito já dentro da frota não pode gravar a frota nele.
        $this->putJson('/api/me/aparencia', ['tema' => 'escuro', 'cor' => 'green'], $this->headers)->assertStatus(200);

        $gravado = User::findOrFail($this->usuario->id);
        $this->assertSame('escuro', $gravado->tema);
        $this->assertSame('oficina', $gravado->perfil ?? 'oficina');
        $this->assertNotNull($gravado->oficina_id);
        $this->assertNull($gravado->conta_id);
    }

    public function test_apagar_o_acesso_devolve_a_sessao_ao_principal(): void
    {
        $this->postJson('/api/meus-mycars', ['perfil' => 'pessoa'], $this->headers)->assertStatus(201);

        Acesso::where('user_id', $this->usuario->id)->delete();

        $this->getJson('/api/me', $this->headers)->assertJsonPath('perfil', 'oficina');
        $this->assertNull(TokenAcesso::where('user_id', $this->usuario->id)->value('acesso_id'));
    }

    public function test_avisos_por_e_mail_chegam_tambem_a_quem_tem_o_mycar_como_extra(): void
    {
        $this->postJson('/api/meus-mycars', ['perfil' => 'frota', 'nome' => 'Frota Ana'], $this->headers)->assertStatus(201);
        $this->postJson('/api/meus-mycars', ['perfil' => 'pessoa'], $this->headers)->assertStatus(201);

        $frota = Conta::where('tipo', 'frota')->firstOrFail();
        $pessoa = Conta::where('tipo', 'pessoa')->firstOrFail();

        $this->assertSame([$this->usuario->email], $frota->emailsParaAvisos());
        $this->assertSame([$this->usuario->email], $pessoa->emailsParaAvisos());
        $this->assertSame([$this->usuario->email], Oficina::findOrFail($this->usuario->oficina_id)->emailsParaAvisos());
    }

    public function test_avisos_nao_vao_para_e_mail_nao_confirmado_nem_repetem(): void
    {
        $this->postJson('/api/meus-mycars', ['perfil' => 'frota', 'nome' => 'Frota Ana'], $this->headers)->assertStatus(201);
        $frota = Conta::where('tipo', 'frota')->firstOrFail();

        $this->usuario->forceFill(['email_verified_at' => null])->save();
        $this->assertSame([], $frota->emailsParaAvisos());

        // Principal e extra na mesma conta (caso de dados antigos) não duplica o e-mail.
        $this->usuario->forceFill(['email_verified_at' => now(), 'perfil' => 'frota', 'conta_id' => $frota->id])->save();
        $this->assertSame([$this->usuario->email], $frota->emailsParaAvisos());
    }
}
