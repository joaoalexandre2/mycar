<?php

namespace Tests\Feature;

use App\Mail\AlertaManutencaoEmail;
use App\Mail\ResumoSemanalEmail;
use App\Models\Cliente;
use App\Models\Conta;
use App\Models\Manutencao;
use App\Models\Oficina;
use App\Models\OrdemServico;
use App\Models\User;
use App\Models\Veiculo;
use App\Models\VeiculoConta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

/**
 * admin:converter-perfil: migra um usuário de oficina para pessoa ou frota
 * sem perder veículo, FIPE, IPVA nem os dados da oficina.
 */
class ConverterPerfilTest extends TestCase
{
    use RefreshDatabase;
    use AutenticaUsuario;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-03-05');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Oficina com um cliente, um veículo com FIPE e estado, uma OS e uma manutenção.
     *
     * @return array{0: User, 1: array<string, string>, 2: Veiculo}
     */
    private function oficinaComDados(): array
    {
        [$usuario, $headers] = $this->autenticar();

        $cliente = Cliente::factory()->create(['email' => 'cliente@exemplo.com']);
        $veiculo = Veiculo::factory()->create([
            'cliente_id' => $cliente->id,
            'placa' => 'ABC1D23',
            'marca' => 'Fiat',
            'modelo' => 'Uno',
            'ano' => 2018,
            'uf' => 'RS',
            'fipe_marca_id' => 23,
            'fipe_modelo_id' => 1,
            'fipe_ano' => '2018-1',
            'fipe_valor' => 40000,
            'fipe_consultado_em' => now()->subDays(2),
        ]);
        OrdemServico::factory()->create(['veiculo_id' => $veiculo->id]);
        Manutencao::factory()->create([
            'veiculo_id' => $veiculo->id,
            'tipo' => 'Revisão',
            'data_manutencao' => '2026-01-10',
            'proxima_data' => '2026-02-20', // atrasada
        ]);

        return [$usuario, $headers, $veiculo];
    }

    private function converter(User $usuario, array $opcoes = []): \Illuminate\Testing\PendingCommand
    {
        return $this->artisan('admin:converter-perfil', ['email' => $usuario->email] + $opcoes);
    }

    public function test_converte_para_pessoa_copiando_veiculo_fipe_e_impostos(): void
    {
        [$usuario, $headers, $veiculo] = $this->oficinaComDados();

        $this->converter($usuario)->assertExitCode(0);

        $usuario = $usuario->fresh();
        $this->assertSame('pessoa', $usuario->perfil);
        $this->assertNotNull($usuario->conta_id);
        $this->assertDatabaseHas('contas', ['id' => $usuario->conta_id, 'tipo' => 'pessoa', 'nome' => $usuario->name]);

        $copia = VeiculoConta::withoutGlobalScopes()->where('conta_id', $usuario->conta_id)->first();

        $this->assertSame('ABC1D23', $copia->placa);
        $this->assertSame('Fiat', $copia->marca);
        $this->assertSame('RS', $copia->uf);
        $this->assertSame(23, $copia->fipe_marca_id);
        $this->assertSame('2018-1', $copia->fipe_ano);
        $this->assertSame('40000.00', (string) $copia->fipe_valor);
        $this->assertSame($veiculo->fipe_consultado_em->toDateString(), $copia->fipe_consultado_em->toDateString());

        // IPVA, licenciamento e vencimentos são calculados: ficam iguais aos da oficina.
        $this->assertEquals($veiculo->ipva_estimado, $copia->ipva_estimado);
        $this->assertEquals($veiculo->licenciamento_valor, $copia->licenciamento_valor);
        $this->assertSame($veiculo->proximo_vencimento_licenciamento, $copia->proximo_vencimento_licenciamento);
    }

    public function test_depois_de_converter_a_api_responde_como_conta_e_barra_a_oficina(): void
    {
        [$usuario, $headers, $veiculo] = $this->oficinaComDados();

        $this->converter($usuario)->assertExitCode(0);

        $this->getJson('/api/me', $headers)
            ->assertJsonPath('perfil', 'pessoa')
            ->assertJsonPath('oficina', null)
            ->assertJsonPath('conta', $usuario->name);

        $lista = $this->getJson('/api/conta/veiculos?all=1', $headers)->assertStatus(200);
        $lista->assertJsonCount(1);
        $this->assertEquals($veiculo->ipva_estimado, $lista->json('0.ipva_estimado'));

        $this->getJson('/api/conta/resumo', $headers)
            ->assertJsonPath('total_veiculos', 1)
            ->assertJsonPath('valor_total_fipe', 40000);

        foreach (['clientes', 'veiculos', 'ordens-servico', 'manutencoes', 'oficina'] as $rota) {
            $this->getJson("/api/{$rota}", $headers)->assertStatus(403);
        }
    }

    public function test_os_dados_da_oficina_continuam_guardados_e_ela_fica_arquivada(): void
    {
        [$usuario, , $veiculo] = $this->oficinaComDados();

        $this->converter($usuario)->assertExitCode(0);

        $oficinaId = $usuario->oficina_id;

        $this->assertSame(1, Cliente::withoutGlobalScopes()->where('oficina_id', $oficinaId)->count());
        $this->assertSame(1, Veiculo::withoutGlobalScopes()->where('oficina_id', $oficinaId)->count());
        $this->assertSame(1, OrdemServico::withoutGlobalScopes()->where('oficina_id', $oficinaId)->count());
        $this->assertSame(1, Manutencao::withoutGlobalScopes()->where('oficina_id', $oficinaId)->count());
        $this->assertNotNull(Oficina::find($oficinaId)->arquivada_em);

        // O usuário continua ligado à oficina, para a conversão poder ser revertida.
        $this->assertSame($oficinaId, $usuario->fresh()->oficina_id);
    }

    public function test_oficina_arquivada_nao_recebe_avisos_nem_resumo(): void
    {
        Mail::fake();
        [$usuario] = $this->oficinaComDados();

        $this->converter($usuario)->assertExitCode(0);

        $this->artisan('manutencoes:alertar')->assertExitCode(0);
        $this->artisan('oficinas:resumo-semanal')->assertExitCode(0);

        Mail::assertNothingSent();
        $this->assertNull(Manutencao::withoutGlobalScopes()->first()->alertado_em);
    }

    public function test_antes_de_converter_os_avisos_e_o_resumo_saem_normalmente(): void
    {
        Mail::fake();
        $this->oficinaComDados();

        $this->artisan('manutencoes:alertar')->assertExitCode(0);
        $this->artisan('oficinas:resumo-semanal')->assertExitCode(0);

        Mail::assertSent(AlertaManutencaoEmail::class, 1);
        Mail::assertSent(ResumoSemanalEmail::class, 1);
    }

    public function test_dry_run_nao_altera_nada(): void
    {
        [$usuario] = $this->oficinaComDados();

        $this->converter($usuario, ['--dry-run' => true])
            ->expectsOutputToContain('nada foi alterado')
            ->assertExitCode(0);

        $this->assertSame('oficina', $usuario->fresh()->perfil);
        $this->assertSame(0, Conta::count());
        $this->assertSame(0, VeiculoConta::withoutGlobalScopes()->count());
        $this->assertNull(Oficina::find($usuario->oficina_id)->arquivada_em);
    }

    public function test_reverter_devolve_a_oficina_como_estava(): void
    {
        [$usuario, $headers] = $this->oficinaComDados();

        $this->converter($usuario)->assertExitCode(0);
        $this->converter($usuario, ['--reverter' => true])->assertExitCode(0);

        $this->assertSame('oficina', $usuario->fresh()->perfil);
        $this->assertNull(Oficina::find($usuario->oficina_id)->arquivada_em);

        $this->getJson('/api/clientes?all=1', $headers)->assertStatus(200)->assertJsonCount(1);
        $this->getJson('/api/conta/veiculos', $headers)->assertStatus(403);
        $this->getJson('/api/me', $headers)->assertJsonPath('perfil', 'oficina')->assertJsonPath('conta', null);

        // Os veículos da conta ficam guardados.
        $this->assertSame(1, VeiculoConta::withoutGlobalScopes()->count());
    }

    public function test_converter_de_novo_depois_de_reverter_nao_duplica_os_veiculos(): void
    {
        [$usuario, $headers] = $this->oficinaComDados();

        $this->converter($usuario)->assertExitCode(0);

        // Na conta, a pessoa cadastra mais um carro.
        $this->postJson('/api/conta/veiculos', [
            'placa' => 'ZZZ9Z99', 'marca' => 'VW', 'modelo' => 'Gol', 'ano' => 2015,
        ], $headers)->assertStatus(201);

        $this->converter($usuario, ['--reverter' => true])->assertExitCode(0);
        $this->converter($usuario)->assertExitCode(0);

        $placas = VeiculoConta::withoutGlobalScopes()->pluck('placa')->sort()->values()->all();

        $this->assertSame(['ABC1D23', 'ZZZ9Z99'], $placas);
        $this->assertSame(1, Conta::count());
    }

    public function test_converte_para_frota_exigindo_o_nome_da_empresa(): void
    {
        [$usuario] = $this->oficinaComDados();

        $this->converter($usuario, ['--para' => 'frota'])
            ->expectsOutputToContain('nome da empresa')
            ->assertExitCode(1);
        $this->assertSame('oficina', $usuario->fresh()->perfil);

        $this->converter($usuario, ['--para' => 'frota', '--nome-conta' => 'Transportes Silva'])
            ->assertExitCode(0);

        $usuario = $usuario->fresh();
        $this->assertSame('frota', $usuario->perfil);
        $this->assertDatabaseHas('contas', ['id' => $usuario->conta_id, 'tipo' => 'frota', 'nome' => 'Transportes Silva']);
    }

    public function test_nome_da_conta_pessoa_pode_ser_informado(): void
    {
        [$usuario] = $this->oficinaComDados();

        $this->converter($usuario, ['--nome-conta' => 'Garagem da família'])->assertExitCode(0);

        $this->assertDatabaseHas('contas', ['id' => $usuario->fresh()->conta_id, 'nome' => 'Garagem da família']);
    }

    public function test_recusa_usuario_inexistente_perfil_invalido_e_quem_nao_e_de_oficina(): void
    {
        $this->artisan('admin:converter-perfil', ['email' => 'ninguem@exemplo.com'])->assertExitCode(1);

        [$usuario] = $this->oficinaComDados();
        $this->converter($usuario, ['--para' => 'dono-do-mundo'])->assertExitCode(1);

        [$dono] = $this->autenticarConta('pessoa');
        $this->converter($dono)->assertExitCode(1);
        $this->converter($usuario, ['--reverter' => true])->assertExitCode(1); // ainda é oficina
    }

    public function test_so_arquiva_a_oficina_se_ninguem_mais_a_usa(): void
    {
        [$usuario] = $this->oficinaComDados();
        User::factory()->create(['oficina_id' => $usuario->oficina_id]); // outro usuário da mesma oficina

        $this->converter($usuario)->assertExitCode(0);

        $this->assertNull(Oficina::find($usuario->oficina_id)->arquivada_em);
    }

    public function test_o_operador_continua_com_acesso_ao_painel_depois_de_migrar(): void
    {
        [$operador, $headers] = $this->oficinaComDados();
        $operador->forceFill(['is_super_admin' => true])->save();

        $this->converter($operador)->assertExitCode(0);

        $this->getJson('/api/admin/resumo', $headers)->assertStatus(200);
        $this->getJson('/api/me', $headers)->assertJsonPath('admin', true)->assertJsonPath('perfil', 'pessoa');
    }

    public function test_painel_do_operador_nao_conta_oficina_arquivada_e_mostra_o_uso_do_novo_perfil(): void
    {
        [$operador, $headers] = $this->autenticar();
        $operador->forceFill(['is_super_admin' => true])->save();

        [$migrado] = $this->oficinaComDados();
        $this->converter($migrado)->assertExitCode(0);

        $resumo = $this->getJson('/api/admin/resumo', $headers)->assertStatus(200);
        $resumo->assertJsonPath('oficinas', 1)            // só a do operador
            ->assertJsonPath('totais.clientes', 0)         // os dados arquivados não entram
            ->assertJsonPath('totais.veiculos', 0)
            ->assertJsonPath('contas.pessoa', 1);

        $linha = collect($this->getJson('/api/admin/contas', $headers)->json('data'))->firstWhere('id', $migrado->id);

        $this->assertSame('pessoa', $linha['perfil']);
        $this->assertNull($linha['oficina']);
        $this->assertSame(1, $linha['totais']['veiculos']);
        $this->assertSame(0, $linha['totais']['clientes']);
    }
}
