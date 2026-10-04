<?php

namespace Tests\Feature;

use App\Mail\ConfirmeSeuEmail;
use App\Models\Cliente;
use App\Models\Manutencao;
use App\Models\Oficina;
use App\Models\OrdemServico;
use App\Models\User;
use App\Models\Veiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class PainelOperadorTest extends TestCase
{
    use RefreshDatabase;
    use AutenticaUsuario;

    /**
     * @return array{0: User, 1: array<string, string>}
     */
    private function autenticarAdmin(): array
    {
        [$admin, $headers] = $this->autenticar();
        $admin->forceFill(['is_super_admin' => true])->save();

        return [$admin, $headers];
    }

    public function test_rotas_do_painel_exigem_autenticacao(): void
    {
        $this->getJson('/api/admin/resumo')->assertStatus(401);
        $this->getJson('/api/admin/contas')->assertStatus(401);
        $this->postJson('/api/admin/contas/1/reenviar-confirmacao')->assertStatus(401);
    }

    public function test_usuario_comum_de_oficina_nao_acessa_o_painel(): void
    {
        [, $headers] = $this->autenticar();

        $this->getJson('/api/admin/resumo', $headers)->assertStatus(403);
        $this->getJson('/api/admin/contas', $headers)->assertStatus(403);
        $this->postJson('/api/admin/contas/1/reenviar-confirmacao', [], $headers)->assertStatus(403);
    }

    public function test_resumo_conta_oficinas_usuarios_e_uso_da_plataforma_inteira(): void
    {
        [$admin, $headers] = $this->autenticarAdmin();

        // Uso em duas oficinas diferentes: o painel soma tudo, sem o isolamento.
        $cliente = Cliente::factory()->create();
        $veiculo = Veiculo::factory()->create(['cliente_id' => $cliente->id]);
        OrdemServico::factory()->create(['veiculo_id' => $veiculo->id]);

        $this->autenticar();
        Cliente::factory()->count(2)->create();

        User::factory()->unverified()->create([
            'oficina_id' => Oficina::factory()->create()->id,
        ]);

        $admin->forceFill(['ultimo_acesso_em' => now()])->save();

        $resposta = $this->getJson('/api/admin/resumo', $headers)->assertStatus(200);

        $resposta->assertJsonPath('oficinas', 3)
            ->assertJsonPath('usuarios', 3)
            ->assertJsonPath('email_pendente', 1)
            ->assertJsonPath('cadastros_7_dias', 3)
            ->assertJsonPath('ativos_7_dias', 1)
            ->assertJsonPath('totais.clientes', 3)
            ->assertJsonPath('totais.veiculos', 1)
            ->assertJsonPath('totais.ordens_servico', 1);
    }

    public function test_lista_de_contas_traz_status_do_email_e_totais_por_oficina(): void
    {
        [$admin, $headers] = $this->autenticarAdmin();

        $oficinaB = Oficina::factory()->create(['nome' => 'Oficina do Sergio']);
        $sergio = User::factory()->unverified()->create([
            'name' => 'Sergio',
            'email' => 'sergio@exemplo.com',
            'oficina_id' => $oficinaB->id,
        ]);

        app()->instance('oficina.atual', $oficinaB->id);
        $cliente = Cliente::factory()->create();
        $veiculo = Veiculo::factory()->create(['cliente_id' => $cliente->id]);
        Manutencao::factory()->create(['veiculo_id' => $veiculo->id]);

        $resposta = $this->getJson('/api/admin/contas', $headers)->assertStatus(200);

        $resposta->assertJsonPath('meta.total', 2);

        $linhaSergio = collect($resposta->json('data'))->firstWhere('id', $sergio->id);

        $this->assertSame('Sergio', $linhaSergio['nome']);
        $this->assertFalse($linhaSergio['email_confirmado']);
        $this->assertNull($linhaSergio['email_confirmado_em']);
        $this->assertSame('Oficina do Sergio', $linhaSergio['oficina']['nome']);
        $this->assertSame(
            ['clientes' => 1, 'veiculos' => 1, 'ordens_servico' => 0, 'manutencoes' => 1],
            $linhaSergio['totais']
        );

        $linhaAdmin = collect($resposta->json('data'))->firstWhere('id', $admin->id);
        $this->assertTrue($linhaAdmin['email_confirmado']);
        $this->assertTrue($linhaAdmin['admin']);

        // Datas em ISO 8601 com fuso, para o navegador não ler como horário local.
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $linhaAdmin['cadastro_em']);
    }

    public function test_lista_de_contas_nao_vaza_senha_token_nem_conteudo_das_oficinas(): void
    {
        [, $headers] = $this->autenticarAdmin();

        $cliente = Cliente::factory()->create([
            'nome' => 'Cliente Sigiloso da Oficina',
            'cpf' => '123.456.789-09',
            'telefone' => '(51) 99999-0000',
        ]);
        Veiculo::factory()->create(['cliente_id' => $cliente->id, 'placa' => 'ZZZ9Z99']);

        $corpo = $this->getJson('/api/admin/contas', $headers)->assertStatus(200)->getContent();

        foreach (['password', 'api_token', 'remember_token', 'Cliente Sigiloso', '123.456.789-09', '(51) 99999-0000', 'ZZZ9Z99'] as $proibido) {
            $this->assertStringNotContainsString($proibido, $corpo, "O painel não pode expor: {$proibido}");
        }
    }

    public function test_busca_e_paginacao_da_lista_de_contas(): void
    {
        [, $headers] = $this->autenticarAdmin();

        User::factory()->create([
            'name' => 'Vanessa Lima',
            'email' => 'vanessa@exemplo.com',
            'oficina_id' => Oficina::factory()->create(['nome' => 'Auto Center Vanessa'])->id,
        ]);
        User::factory()->count(3)->create([
            'oficina_id' => Oficina::factory()->create()->id,
        ]);

        $this->getJson('/api/admin/contas?busca=vanessa', $headers)
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.email', 'vanessa@exemplo.com');

        $this->getJson('/api/admin/contas?busca=Auto Center', $headers)
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/api/admin/contas?per_page=2', $headers)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonCount(2, 'data');
    }

    public function test_reenvia_o_link_de_confirmacao_para_quem_ainda_nao_confirmou(): void
    {
        Mail::fake();
        [, $headers] = $this->autenticarAdmin();

        $pendente = User::factory()->unverified()->create([
            'email' => 'vanessa@exemplo.com',
            'oficina_id' => Oficina::factory()->create()->id,
        ]);

        $this->postJson("/api/admin/contas/{$pendente->id}/reenviar-confirmacao", [], $headers)
            ->assertStatus(200);

        Mail::assertSent(ConfirmeSeuEmail::class, fn (ConfirmeSeuEmail $mail) => $mail->hasTo('vanessa@exemplo.com'));
    }

    public function test_nao_reenvia_para_email_ja_confirmado_nem_para_usuario_inexistente(): void
    {
        Mail::fake();
        [$admin, $headers] = $this->autenticarAdmin();

        $this->postJson("/api/admin/contas/{$admin->id}/reenviar-confirmacao", [], $headers)
            ->assertStatus(422);
        $this->postJson('/api/admin/contas/999999/reenviar-confirmacao', [], $headers)
            ->assertStatus(404);

        Mail::assertNothingSent();
    }

    public function test_login_registra_ultimo_acesso_e_informa_se_e_admin(): void
    {
        $usuario = User::factory()->create([
            'email' => 'dono@exemplo.com',
            'password' => 'senha-segura-123',
            'oficina_id' => Oficina::factory()->create()->id,
        ]);

        $this->assertNull($usuario->ultimo_acesso_em);

        $this->postJson('/api/login', ['email' => 'dono@exemplo.com', 'password' => 'senha-segura-123'])
            ->assertStatus(200)
            ->assertJsonPath('user.admin', false);

        $this->assertNotNull($usuario->fresh()->ultimo_acesso_em);

        $usuario->forceFill(['is_super_admin' => true])->save();

        $this->postJson('/api/login', ['email' => 'dono@exemplo.com', 'password' => 'senha-segura-123'])
            ->assertJsonPath('user.admin', true);
    }

    public function test_me_e_atualizacao_de_perfil_mantem_a_flag_de_admin(): void
    {
        [, $headers] = $this->autenticarAdmin();

        $this->getJson('/api/me', $headers)->assertJsonPath('admin', true);
        $this->putJson('/api/me', ['name' => 'Novo Nome'], $headers)->assertJsonPath('admin', true);
    }

    public function test_ninguem_vira_admin_por_cadastro_nem_pela_api(): void
    {
        $this->postJson('/api/register', [
            'nome_oficina' => 'Oficina Espertinha',
            'name' => 'Espertinho',
            'email' => 'espertinho@exemplo.com',
            'password' => 'senha-segura-123',
            'password_confirmation' => 'senha-segura-123',
            'is_super_admin' => true,
        ])->assertStatus(201);

        $this->assertFalse((bool) User::where('email', 'espertinho@exemplo.com')->value('is_super_admin'));

        [$usuario, $headers] = $this->autenticar();
        $this->putJson('/api/me', ['name' => 'Outro', 'is_super_admin' => true], $headers)
            ->assertStatus(200)
            ->assertJsonPath('admin', false);

        $this->assertFalse((bool) $usuario->fresh()->is_super_admin);
        $this->getJson('/api/admin/resumo', $headers)->assertStatus(403);
    }

    public function test_comando_promove_e_remove_o_acesso_de_administrador(): void
    {
        $usuario = User::factory()->create([
            'email' => 'dono@exemplo.com',
            'oficina_id' => Oficina::factory()->create()->id,
        ]);

        $this->artisan('admin:promover', ['email' => 'dono@exemplo.com'])->assertExitCode(0);
        $this->assertTrue((bool) $usuario->fresh()->is_super_admin);

        $this->artisan('admin:promover', ['email' => 'dono@exemplo.com', '--remover' => true])->assertExitCode(0);
        $this->assertFalse((bool) $usuario->fresh()->is_super_admin);

        $this->artisan('admin:promover', ['email' => 'ninguem@exemplo.com'])->assertExitCode(1);
    }
}
