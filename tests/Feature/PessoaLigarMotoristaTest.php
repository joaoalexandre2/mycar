<?php

namespace Tests\Feature;

use App\Models\AcessoFrotaEvento;
use App\Models\CentroCusto;
use App\Models\Conta;
use App\Models\Motorista;
use App\Models\User;
use App\Models\UsoVeiculo;
use App\Models\VeiculoConta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

/**
 * Ligar o login de uma pessoa (papel motorista) a um cadastro de motorista:
 * a um cadastro existente sem login ou criando o cadastro a partir da pessoa.
 */
class PessoaLigarMotoristaTest extends TestCase
{
    use AutenticaUsuario;
    use RefreshDatabase;

    /** @var array<string, string> */
    private array $headersAdmin;

    private Conta $conta;

    private CentroCusto $centro;

    protected function setUp(): void
    {
        parent::setUp();
        [, $this->headersAdmin] = $this->autenticarConta('frota');
        $this->conta = Conta::find(app('conta.atual'));
        $this->centro = CentroCusto::create(['nome' => 'Entregas']);
    }

    /** @return array{0: User, 1: array<string, string>} */
    private function pessoa(string $papel, string $nome = 'Rui Rua'): array
    {
        [$usuario, $headers] = $this->autenticarConta('frota', $this->conta);
        $usuario->forceFill(['papel_frota' => $papel, 'name' => $nome])->save();
        app()->instance('conta.atual', $this->conta->id);

        return [$usuario, $headers];
    }

    private function motoristaSemLogin(string $nome = 'Rui Rua'): Motorista
    {
        return Motorista::create([
            'nome' => $nome, 'cnh_numero' => '123', 'cnh_validade' => '2035-01-01', 'centro_custo_id' => $this->centro->id,
        ]);
    }

    private function url(int $pessoaId): string
    {
        return "/api/conta/pessoas/{$pessoaId}/motorista";
    }

    public function test_liga_a_um_motorista_existente_sem_login(): void
    {
        [$pessoa] = $this->pessoa('motorista');
        $motorista = $this->motoristaSemLogin();

        $this->postJson($this->url($pessoa->id), ['motorista_id' => $motorista->id], $this->headersAdmin)
            ->assertOk()
            ->assertJsonPath('motorista', ['id' => $motorista->id, 'nome' => 'Rui Rua']);

        $this->assertSame($pessoa->id, $motorista->fresh()->user_id);
    }

    public function test_cria_o_cadastro_a_partir_da_pessoa_com_o_centro_obrigatorio(): void
    {
        [$pessoa] = $this->pessoa('motorista', 'Lia Lima');

        $this->postJson($this->url($pessoa->id), [
            'centro_custo_id' => $this->centro->id, 'cnh_validade' => '2035-01-01', 'cnh_numero' => '999',
        ], $this->headersAdmin)
            ->assertStatus(201)
            ->assertJsonPath('motorista.nome', 'Lia Lima');

        $motorista = Motorista::where('user_id', $pessoa->id)->first();
        $this->assertNotNull($motorista);
        $this->assertSame('Lia Lima', $motorista->nome);
        $this->assertSame($this->centro->id, $motorista->centro_custo_id);
        $this->assertSame($this->conta->id, $motorista->conta_id);
        $this->assertSame('2035-01-01', $motorista->cnh_validade->toDateString());
    }

    public function test_criar_sem_centro_ou_com_centro_invalido_e_recusado(): void
    {
        [$pessoa] = $this->pessoa('motorista');

        $this->postJson($this->url($pessoa->id), [], $this->headersAdmin)->assertStatus(422);

        $inativo = CentroCusto::create(['nome' => 'Velho', 'ativo' => false]);
        $this->postJson($this->url($pessoa->id), ['centro_custo_id' => $inativo->id], $this->headersAdmin)
            ->assertStatus(422)->assertJsonValidationErrors('centro_custo_id');

        $this->postJson($this->url($pessoa->id), ['centro_custo_id' => $this->centro->id, 'cnh_validade' => 'ontem'], $this->headersAdmin)
            ->assertStatus(422)->assertJsonValidationErrors('cnh_validade');

        $this->assertSame(0, Motorista::count());
    }

    public function test_nao_manda_as_duas_formas_juntas(): void
    {
        [$pessoa] = $this->pessoa('motorista');
        $motorista = $this->motoristaSemLogin('Outro');

        $this->postJson($this->url($pessoa->id), ['motorista_id' => $motorista->id, 'centro_custo_id' => $this->centro->id], $this->headersAdmin)
            ->assertStatus(422);

        $this->assertNull($motorista->fresh()->user_id);
    }

    public function test_criar_com_nome_ja_cadastrado_pede_para_ligar_ao_existente(): void
    {
        [$pessoa] = $this->pessoa('motorista');
        $this->motoristaSemLogin('Rui Rua');

        $this->postJson($this->url($pessoa->id), ['centro_custo_id' => $this->centro->id], $this->headersAdmin)
            ->assertStatus(422)
            ->assertJsonValidationErrors('nome');

        $this->assertSame(1, Motorista::count());
    }

    public function test_centro_de_outra_conta_e_recusado(): void
    {
        [$pessoa] = $this->pessoa('motorista');
        $outraConta = Conta::create(['tipo' => 'frota', 'nome' => 'Outra']);
        $centroAlheio = CentroCusto::withoutGlobalScope('conta')->create(['nome' => 'Alheio']);
        $centroAlheio->forceFill(['conta_id' => $outraConta->id])->save();

        $this->postJson($this->url($pessoa->id), ['centro_custo_id' => $centroAlheio->id], $this->headersAdmin)
            ->assertStatus(422)->assertJsonValidationErrors('centro_custo_id');
    }

    public function test_recusa_quem_nao_tem_papel_de_motorista(): void
    {
        [$gestor] = $this->pessoa('gestor');
        $motorista = $this->motoristaSemLogin();

        $this->postJson($this->url($gestor->id), ['motorista_id' => $motorista->id], $this->headersAdmin)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Só quem tem o papel de motorista pode ser ligado a um cadastro de motorista.');

        $this->postJson($this->url($gestor->id), ['centro_custo_id' => $this->centro->id], $this->headersAdmin)->assertStatus(422);

        $this->assertNull($motorista->fresh()->user_id);
        $this->assertSame(1, Motorista::count());
    }

    public function test_recusa_pessoa_ja_ligada_a_outro_motorista(): void
    {
        [$pessoa] = $this->pessoa('motorista');
        $atual = $this->motoristaSemLogin('Atual');
        $atual->forceFill(['user_id' => $pessoa->id])->save();
        $outro = $this->motoristaSemLogin('Outro');

        $this->postJson($this->url($pessoa->id), ['motorista_id' => $outro->id], $this->headersAdmin)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Esta pessoa já está ligada a outro cadastro de motorista.');

        $this->postJson($this->url($pessoa->id), ['centro_custo_id' => $this->centro->id], $this->headersAdmin)->assertStatus(422);

        $this->assertNull($outro->fresh()->user_id);
        $this->assertSame(2, Motorista::count());
    }

    public function test_ligar_de_novo_ao_mesmo_cadastro_nao_muda_nada(): void
    {
        [$pessoa] = $this->pessoa('motorista');
        $motorista = $this->motoristaSemLogin();

        $this->postJson($this->url($pessoa->id), ['motorista_id' => $motorista->id], $this->headersAdmin)->assertOk();
        $this->postJson($this->url($pessoa->id), ['motorista_id' => $motorista->id], $this->headersAdmin)
            ->assertOk()
            ->assertJsonPath('motorista.id', $motorista->id);

        $this->assertSame(1, AcessoFrotaEvento::where('acao', 'ligou_motorista')->count());
    }

    public function test_recusa_motorista_que_ja_tem_outro_login(): void
    {
        [$pessoa] = $this->pessoa('motorista');
        [$outraPessoa] = $this->pessoa('motorista', 'Outra Pessoa');
        $motorista = $this->motoristaSemLogin();
        $motorista->forceFill(['user_id' => $outraPessoa->id])->save();

        $this->postJson($this->url($pessoa->id), ['motorista_id' => $motorista->id], $this->headersAdmin)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Este motorista já tem outro login ligado.');

        $this->assertSame($outraPessoa->id, $motorista->fresh()->user_id);
    }

    public function test_nao_atravessa_contas(): void
    {
        [$pessoa] = $this->pessoa('motorista');

        $outraConta = Conta::create(['tipo' => 'frota', 'nome' => 'Outra frota']);
        $estranho = User::factory()->create([
            'oficina_id' => null, 'perfil' => 'frota', 'conta_id' => $outraConta->id, 'papel_frota' => 'motorista',
        ]);
        $motoristaAlheio = Motorista::withoutGlobalScope('conta')->create(['nome' => 'Alheio']);
        $motoristaAlheio->forceFill(['conta_id' => $outraConta->id])->save();
        $meu = $this->motoristaSemLogin();

        // Pessoa minha + motorista de outra conta: 404, sem ligar nada.
        $this->postJson($this->url($pessoa->id), ['motorista_id' => $motoristaAlheio->id], $this->headersAdmin)
            ->assertStatus(404);

        // Pessoa de outra conta + motorista meu: 404, nas duas formas.
        $this->postJson($this->url($estranho->id), ['motorista_id' => $meu->id], $this->headersAdmin)->assertStatus(404);
        $this->postJson($this->url($estranho->id), ['centro_custo_id' => $this->centro->id], $this->headersAdmin)->assertStatus(404);

        $this->assertNull(Motorista::withoutGlobalScope('conta')->find($motoristaAlheio->id)->user_id);
        $this->assertNull($meu->fresh()->user_id);
        $this->assertSame(0, Motorista::withoutGlobalScope('conta')->where('user_id', $estranho->id)->count());
    }

    public function test_lista_de_pessoas_informa_o_cadastro_ligado_sem_vazar_outra_conta(): void
    {
        [$ligada] = $this->pessoa('motorista', 'Ligada');
        [$solta] = $this->pessoa('motorista', 'Solta');
        $motorista = $this->motoristaSemLogin('Cadastro da Ligada');
        $motorista->forceFill(['user_id' => $ligada->id])->save();

        // Um cadastro de outra conta apontando (indevidamente) para a pessoa solta não aparece.
        $outraConta = Conta::create(['tipo' => 'frota', 'nome' => 'Outra frota']);
        $alheio = Motorista::withoutGlobalScope('conta')->create(['nome' => 'Alheio']);
        $alheio->forceFill(['conta_id' => $outraConta->id, 'user_id' => $solta->id])->save();

        $pessoas = collect($this->getJson('/api/conta/pessoas', $this->headersAdmin)->assertOk()->json('pessoas'))->keyBy('id');

        $this->assertSame(['id' => $motorista->id, 'nome' => 'Cadastro da Ligada'], $pessoas[$ligada->id]['motorista']);
        $this->assertNull($pessoas[$solta->id]['motorista']);
        $this->assertArrayHasKey('motorista', $pessoas->first());
    }

    public function test_fica_na_trilha_de_acessos(): void
    {
        [$pessoa] = $this->pessoa('motorista');

        $this->postJson($this->url($pessoa->id), ['centro_custo_id' => $this->centro->id], $this->headersAdmin)->assertStatus(201);

        $this->getJson('/api/conta/acessos-frota', $this->headersAdmin)
            ->assertOk()
            ->assertJsonFragment(['acao' => 'ligou_motorista'])
            ->assertJsonFragment(['nome' => 'Rui Rua', 'motorista' => 'Rui Rua', 'criou_cadastro' => true]);
    }

    public function test_motorista_gestor_e_visitante_nao_ligam(): void
    {
        [$pessoa, $headersMotorista] = $this->pessoa('motorista');
        [, $headersGestor] = $this->pessoa('gestor', 'Gil Gestor');
        $motorista = $this->motoristaSemLogin();

        $this->postJson($this->url($pessoa->id), ['motorista_id' => $motorista->id], $headersMotorista)->assertStatus(403);
        $this->postJson($this->url($pessoa->id), ['motorista_id' => $motorista->id], $headersGestor)->assertStatus(403);
        $this->postJson($this->url($pessoa->id), ['motorista_id' => $motorista->id])->assertStatus(401);

        $this->assertNull($motorista->fresh()->user_id);
    }

    public function test_depois_de_ligar_a_reserva_do_motorista_nao_falha_por_falta_de_cadastro(): void
    {
        [$pessoa, $headersMotorista] = $this->pessoa('motorista');
        $veiculo = VeiculoConta::create(['placa' => 'AAA1B25', 'marca' => 'Fiat', 'modelo' => 'Strada', 'ano' => 2022]);
        $reserva = [
            'veiculo_conta_id' => $veiculo->id,
            'inicio_previsto' => '2026-11-10 08:00',
            'fim_previsto' => '2026-11-10 12:00',
        ];

        $this->postJson('/api/conta/usos', $reserva, $headersMotorista)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Seu login ainda não está ligado a um motorista cadastrado. Peça ao gestor.');

        $this->postJson($this->url($pessoa->id), [
            'centro_custo_id' => $this->centro->id, 'cnh_validade' => '2035-01-01',
        ], $this->headersAdmin)->assertStatus(201);

        $this->postJson('/api/conta/usos', $reserva, $headersMotorista)->assertStatus(201);

        $this->assertSame(Motorista::where('user_id', $pessoa->id)->value('id'), UsoVeiculo::first()->motorista_id);
    }
}
