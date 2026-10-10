<?php

namespace Tests\Feature;

use App\Mail\ConfirmeSeuEmail;
use App\Models\Conta;
use App\Models\User;
use App\Models\VeiculoConta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

/**
 * Os três perfis do MyCar: oficina, pessoa (Cuidados com seu carro) e frota.
 */
class PerfisContaTest extends TestCase
{
    use RefreshDatabase;
    use AutenticaUsuario;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function veiculo(array $extra = []): array
    {
        return array_merge([
            'placa' => 'ABC1D23',
            'marca' => 'Fiat',
            'modelo' => 'Uno',
            'ano' => 2018,
            'uf' => 'RS',
        ], $extra);
    }

    private function cadastro(array $extra = []): array
    {
        return array_merge([
            'name' => 'Fulano de Tal',
            'email' => 'fulano@exemplo.com',
            'password' => 'senha-forte-123',
            'password_confirmation' => 'senha-forte-123',
        ], $extra);
    }

    // ------------------------------------------------------------- cadastro

    public function test_cadastro_de_pessoa_cria_conta_pessoa_sem_oficina(): void
    {
        Mail::fake();

        $this->postJson('/api/register', $this->cadastro(['perfil' => 'pessoa']))->assertStatus(201);

        $usuario = User::where('email', 'fulano@exemplo.com')->first();

        $this->assertSame('pessoa', $usuario->perfil);
        $this->assertNull($usuario->oficina_id);
        $this->assertNotNull($usuario->conta_id);
        $this->assertDatabaseHas('contas', ['id' => $usuario->conta_id, 'tipo' => 'pessoa', 'nome' => 'Fulano de Tal']);
        $this->assertDatabaseCount('oficinas', 0);

        Mail::assertSent(ConfirmeSeuEmail::class, fn ($mail) => $mail->hasTo('fulano@exemplo.com'));
    }

    public function test_cadastro_de_frota_exige_o_nome_da_frota(): void
    {
        $this->postJson('/api/register', $this->cadastro(['perfil' => 'frota']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('nome_frota');

        Mail::fake();

        $this->postJson('/api/register', $this->cadastro(['perfil' => 'frota', 'nome_frota' => 'Transportes Silva']))
            ->assertStatus(201);

        $usuario = User::where('email', 'fulano@exemplo.com')->first();

        $this->assertSame('frota', $usuario->perfil);
        $this->assertDatabaseHas('contas', ['id' => $usuario->conta_id, 'tipo' => 'frota', 'nome' => 'Transportes Silva']);
    }

    public function test_cadastro_sem_perfil_continua_criando_oficina_como_sempre(): void
    {
        Mail::fake();

        $this->postJson('/api/register', $this->cadastro(['nome_oficina' => 'Oficina do João']))->assertStatus(201);

        $usuario = User::where('email', 'fulano@exemplo.com')->first();

        $this->assertSame('oficina', $usuario->perfil);
        $this->assertNotNull($usuario->oficina_id);
        $this->assertNull($usuario->conta_id);
        $this->assertDatabaseHas('oficinas', ['nome' => 'Oficina do João']);

        // Oficina, com ou sem o campo "perfil", continua exigindo o nome.
        $this->postJson('/api/register', $this->cadastro(['email' => 'outro@exemplo.com']))
            ->assertStatus(422)->assertJsonValidationErrors('nome_oficina');
        $this->postJson('/api/register', $this->cadastro(['email' => 'outro@exemplo.com', 'perfil' => 'oficina']))
            ->assertStatus(422)->assertJsonValidationErrors('nome_oficina');
    }

    public function test_perfil_invalido_e_recusado(): void
    {
        $this->postJson('/api/register', $this->cadastro(['perfil' => 'dono-do-mundo', 'nome_oficina' => 'X']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('perfil');
    }

    public function test_login_e_me_informam_o_perfil_e_a_conta(): void
    {
        [$usuario] = $this->autenticarConta('frota');
        $usuario->forceFill(['password' => 'senha-forte-123'])->save();

        $login = $this->postJson('/api/login', ['email' => $usuario->email, 'password' => 'senha-forte-123'])
            ->assertStatus(200)
            ->assertJsonPath('user.perfil', 'frota')
            ->assertJsonPath('user.conta', $usuario->conta->nome)
            ->assertJsonPath('user.oficina', null);

        // O login emite um token novo; é com ele que o /me responde.
        $this->getJson('/api/me', ['Authorization' => 'Bearer ' . $login->json('token')])
            ->assertJsonPath('perfil', 'frota');
    }

    public function test_usuario_de_oficina_aparece_como_perfil_oficina(): void
    {
        [, $headers] = $this->autenticar();

        $this->getJson('/api/me', $headers)
            ->assertJsonPath('perfil', 'oficina')
            ->assertJsonPath('conta', null);
    }

    // ------------------------------------------------------ separação de perfis

    public function test_pessoa_e_frota_nao_acessam_as_rotas_de_oficina(): void
    {
        foreach (['pessoa', 'frota'] as $perfil) {
            [, $headers] = $this->autenticarConta($perfil);

            foreach (['clientes', 'veiculos', 'ordens-servico', 'manutencoes', 'oficina'] as $rota) {
                $this->getJson("/api/{$rota}", $headers)->assertStatus(403);
            }

            $this->postJson('/api/clientes', [], $headers)->assertStatus(403);
        }
    }

    public function test_oficina_nao_acessa_as_rotas_da_conta(): void
    {
        [, $headers] = $this->autenticar();

        $this->getJson('/api/conta/resumo', $headers)->assertStatus(403);
        $this->getJson('/api/conta/veiculos', $headers)->assertStatus(403);
        $this->postJson('/api/conta/veiculos', $this->veiculo(), $headers)->assertStatus(403);
    }

    public function test_rotas_da_conta_exigem_autenticacao(): void
    {
        $this->getJson('/api/conta/resumo')->assertStatus(401);
        $this->getJson('/api/conta/veiculos')->assertStatus(401);
    }

    public function test_a_consulta_fipe_continua_aberta_a_todos_os_perfis(): void
    {
        Http::fake(['*/carros/marcas' => Http::response([['codigo' => '23', 'nome' => 'Fiat']])]);

        [, $headersOficina] = $this->autenticar();
        $this->getJson('/api/fipe/marcas', $headersOficina)->assertStatus(200);

        [, $headersConta] = $this->autenticarConta('pessoa');
        $this->getJson('/api/fipe/marcas', $headersConta)->assertStatus(200);
    }

    // ------------------------------------------------------ veículos da conta

    public function test_cria_lista_edita_e_remove_veiculo_da_conta(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');

        $id = $this->postJson('/api/conta/veiculos', $this->veiculo(['apelido' => 'Meu Uno']), $headers)
            ->assertStatus(201)
            ->assertJsonPath('placa', 'ABC1D23')
            ->assertJsonPath('apelido', 'Meu Uno')
            ->json('id');

        $this->getJson('/api/conta/veiculos', $headers)
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('resumo.total', 1);

        $this->putJson("/api/conta/veiculos/{$id}", $this->veiculo(['modelo' => 'Mille']), $headers)
            ->assertStatus(200)
            ->assertJsonPath('modelo', 'Mille');

        $this->getJson("/api/conta/veiculos/{$id}", $headers)->assertJsonPath('modelo', 'Mille');

        $this->deleteJson("/api/conta/veiculos/{$id}", [], $headers)->assertStatus(200);
        $this->getJson('/api/conta/veiculos', $headers)->assertJsonPath('meta.total', 0);
    }

    public function test_valida_os_dados_do_veiculo(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');

        $this->postJson('/api/conta/veiculos', [], $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['placa', 'marca', 'modelo', 'ano']);

        $this->postJson('/api/conta/veiculos', $this->veiculo(['uf' => 'XX']), $headers)
            ->assertStatus(422)->assertJsonValidationErrors('uf');
    }

    public function test_busca_por_placa_apelido_marca_ou_modelo(): void
    {
        [, $headers] = $this->autenticarConta('frota');

        $this->postJson('/api/conta/veiculos', $this->veiculo(['placa' => 'AAA1B11', 'apelido' => 'Caminhonete da obra']), $headers);
        $this->postJson('/api/conta/veiculos', $this->veiculo(['placa' => 'CCC2D22', 'marca' => 'Volkswagen', 'modelo' => 'Gol']), $headers);

        $this->getJson('/api/conta/veiculos?busca=obra', $headers)->assertJsonPath('meta.total', 1);
        $this->getJson('/api/conta/veiculos?busca=Gol', $headers)->assertJsonPath('meta.total', 1);
        $this->getJson('/api/conta/veiculos?busca=CCC2', $headers)->assertJsonPath('meta.total', 1);
        $this->getJson('/api/conta/veiculos?all=1', $headers)->assertJsonCount(2);
    }

    public function test_placa_e_unica_dentro_da_conta_mas_pode_repetir_em_outra(): void
    {
        [, $headersA] = $this->autenticarConta('pessoa');
        $this->postJson('/api/conta/veiculos', $this->veiculo(), $headersA)->assertStatus(201);
        $this->postJson('/api/conta/veiculos', $this->veiculo(), $headersA)
            ->assertStatus(422)->assertJsonValidationErrors('placa');

        // O carro mudou de dono: a mesma placa em outra conta é válida.
        [, $headersB] = $this->autenticarConta('pessoa');
        $this->postJson('/api/conta/veiculos', $this->veiculo(), $headersB)->assertStatus(201);
    }

    public function test_uma_conta_nao_ve_nem_altera_veiculos_de_outra(): void
    {
        [, $headersA] = $this->autenticarConta('pessoa');
        $idA = $this->postJson('/api/conta/veiculos', $this->veiculo(['apelido' => 'Segredo da conta A']), $headersA)->json('id');

        [, $headersB] = $this->autenticarConta('frota');

        $this->getJson('/api/conta/veiculos?all=1', $headersB)
            ->assertJsonMissing(['apelido' => 'Segredo da conta A'])
            ->assertJsonCount(0);
        $this->getJson("/api/conta/veiculos/{$idA}", $headersB)->assertStatus(404);
        $this->putJson("/api/conta/veiculos/{$idA}", $this->veiculo(['modelo' => 'Hackeado']), $headersB)->assertStatus(404);
        $this->deleteJson("/api/conta/veiculos/{$idA}", [], $headersB)->assertStatus(404);
        $this->postJson("/api/conta/veiculos/{$idA}/fipe", [], $headersB)->assertStatus(404);

        $this->assertDatabaseHas('veiculos_conta', ['id' => $idA, 'modelo' => 'Uno']);
        $this->assertSame(1, VeiculoConta::withoutGlobalScopes()->count());
    }

    public function test_sem_conta_vinculada_nao_enxerga_nada(): void
    {
        [, $headersA] = $this->autenticarConta('pessoa');
        $this->postJson('/api/conta/veiculos', $this->veiculo(), $headersA)->assertStatus(201);

        // Usuário de perfil pessoa sem conta (situação anômala): falha fechado.
        [$semConta, $headers] = $this->autenticarConta('pessoa');
        $semConta->forceFill(['conta_id' => null])->save();

        $this->getJson('/api/conta/veiculos?all=1', $headers)->assertStatus(200)->assertJsonCount(0);
    }

    // ----------------------------------------------------------------- FIPE

    public function test_cadastro_com_codigos_fipe_busca_o_valor(): void
    {
        Http::fake(['*/carros/marcas/23/modelos/1/anos/2020-1' => Http::response(['Valor' => 'R$ 60.250,50'])]);
        [, $headers] = $this->autenticarConta('pessoa');

        $this->postJson('/api/conta/veiculos', $this->veiculo([
            'fipe_marca_id' => 23, 'fipe_modelo_id' => 1, 'fipe_ano' => '2020-1',
        ]), $headers)
            ->assertStatus(201)
            ->assertJsonPath('fipe_valor', '60250.50');
    }

    public function test_fipe_fora_do_ar_nao_impede_o_cadastro(): void
    {
        Http::fake(['*' => Http::response('erro', 500)]);
        [, $headers] = $this->autenticarConta('pessoa');

        $id = $this->postJson('/api/conta/veiculos', $this->veiculo([
            'fipe_marca_id' => 23, 'fipe_modelo_id' => 1, 'fipe_ano' => '2020-1',
        ]), $headers)
            ->assertStatus(201)
            ->assertJsonPath('fipe_valor', null)
            ->json('id');

        $this->postJson("/api/conta/veiculos/{$id}/fipe", [], $headers)->assertStatus(502);
    }

    public function test_consultar_fipe_exige_os_codigos_e_atualiza_o_valor(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $semCodigos = $this->postJson('/api/conta/veiculos', $this->veiculo(['placa' => 'AAA1B11']), $headers)->json('id');

        $this->postJson("/api/conta/veiculos/{$semCodigos}/fipe", [], $headers)->assertStatus(422);

        // A FIPE guarda as respostas em cache: o primeiro valor vem no cadastro,
        // o segundo depois que o cache expira.
        Http::fake([
            '*/carros/marcas/23/modelos/1/anos/2020-1' => Http::sequence()
                ->push(['Valor' => 'R$ 55.000,00'])
                ->push(['Valor' => 'R$ 58.000,00']),
        ]);
        $comCodigos = $this->postJson('/api/conta/veiculos', $this->veiculo([
            'placa' => 'BBB2C22', 'fipe_marca_id' => 23, 'fipe_modelo_id' => 1, 'fipe_ano' => '2020-1',
        ]), $headers)
            ->assertJsonPath('fipe_valor', '55000.00')
            ->json('id');

        \Illuminate\Support\Facades\Cache::flush();

        $this->postJson("/api/conta/veiculos/{$comCodigos}/fipe", [], $headers)
            ->assertStatus(200)
            ->assertJsonPath('fipe_valor', '58000.00');
    }

    // ------------------------------------------------- tributos e visão geral

    public function test_calcula_ipva_e_licenciamento_estimados_como_nos_veiculos_de_oficina(): void
    {
        Carbon::setTestNow('2026-03-05');
        [, $headers] = $this->autenticarConta('pessoa');

        VeiculoConta::create($this->veiculo(['placa' => 'AAA1B23', 'uf' => 'RS', 'fipe_valor' => 50000]));

        $dados = $this->getJson('/api/conta/veiculos?all=1', $headers)->json('0');

        $this->assertEqualsWithDelta(
            round(50000 * config('tributos.estados.RS.ipva') / 100, 2),
            $dados['ipva_estimado'],
            0.01
        );
        $this->assertSame('2027-01-31', $dados['proximo_vencimento_ipva']);        // sempre janeiro
        $this->assertSame('2026-05-31', $dados['proximo_vencimento_licenciamento']);
    }

    public function test_resumo_lista_o_que_vence_nos_proximos_60_dias_em_ordem(): void
    {
        // O IPVA vence em 31/01 (hoje, 0 dia) e o licenciamento do final 1 em 31/03 (59 dias).
        Carbon::setTestNow('2026-01-31');
        [$usuario, $headers] = $this->autenticarConta('frota');

        VeiculoConta::create($this->veiculo(['placa' => 'AAA1B23', 'apelido' => 'Van', 'fipe_valor' => 40000])); // IPVA 31/01; lic. 31/05 (fora)
        VeiculoConta::create($this->veiculo(['placa' => 'CCC1D21', 'marca' => 'VW', 'modelo' => 'Gol']));        // IPVA 31/01; lic. 31/03
        VeiculoConta::create($this->veiculo(['placa' => 'EEE1F25', 'uf' => null]));                               // IPVA 31/01 (mesmo sem estado)

        $resposta = $this->getJson('/api/conta/resumo', $headers)->assertStatus(200);

        $resposta->assertJsonPath('total_veiculos', 3)
            ->assertJsonPath('valor_total_fipe', 40000)
            ->assertJsonPath('conta.nome', $usuario->conta->nome)
            ->assertJsonPath('conta.tipo', 'frota')
            ->assertJsonCount(4, 'vencimentos');

        $vencimentos = $resposta->json('vencimentos');

        $this->assertSame(['ipva', 'ipva', 'ipva', 'licenciamento'], array_column($vencimentos, 'tipo'));
        $this->assertSame(0, $vencimentos[0]['dias']);
        $this->assertSame('2026-01-31', $vencimentos[0]['data']);
        $this->assertSame('VW Gol', $vencimentos[3]['veiculo']);
        $this->assertSame(59, $vencimentos[3]['dias']);
    }

    public function test_resumo_so_conta_os_veiculos_da_propria_conta(): void
    {
        [, $headersA] = $this->autenticarConta('pessoa');
        VeiculoConta::create($this->veiculo(['placa' => 'AAA1B11']));
        VeiculoConta::create($this->veiculo(['placa' => 'AAA1B22']));

        [, $headersB] = $this->autenticarConta('pessoa');
        VeiculoConta::create($this->veiculo(['placa' => 'AAA1B33']));

        $this->getJson('/api/conta/resumo', $headersA)->assertJsonPath('total_veiculos', 2);
        $this->getJson('/api/conta/resumo', $headersB)->assertJsonPath('total_veiculos', 1);
    }

    // ------------------------------------------------------- painel do operador

    public function test_painel_do_operador_mostra_o_perfil_e_a_contagem_por_tipo(): void
    {
        [$admin, $headers] = $this->autenticar();
        $admin->forceFill(['is_super_admin' => true])->save();

        [$dono] = $this->autenticarConta('pessoa');
        $this->autenticarConta('frota');
        VeiculoConta::create($this->veiculo());

        $resumo = $this->getJson('/api/admin/resumo', $headers)->assertStatus(200);
        $resumo->assertJsonPath('contas.pessoa', 1)->assertJsonPath('contas.frota', 1);

        $linhas = collect($this->getJson('/api/admin/contas', $headers)->assertStatus(200)->json('data'));

        $linhaAdmin = $linhas->firstWhere('id', $admin->id);
        $this->assertSame('oficina', $linhaAdmin['perfil']);

        $linhaDono = $linhas->firstWhere('id', $dono->id);
        $this->assertSame('pessoa', $linhaDono['perfil']);
        $this->assertSame($dono->conta->nome, $linhaDono['conta']);
        $this->assertNull($linhaDono['oficina']);
    }

    public function test_usuarios_de_conta_nao_acessam_o_painel_do_operador(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');

        $this->getJson('/api/admin/resumo', $headers)->assertStatus(403);
    }

    public function test_conta_apagada_leva_os_veiculos_junto(): void
    {
        [$usuario] = $this->autenticarConta('pessoa');
        VeiculoConta::create($this->veiculo());

        Conta::find($usuario->conta_id)->delete();

        $this->assertSame(0, VeiculoConta::withoutGlobalScopes()->count());
    }
}
