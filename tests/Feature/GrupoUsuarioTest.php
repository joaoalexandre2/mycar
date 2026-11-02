<?php

namespace Tests\Feature;

use App\Models\Conta;
use App\Models\GrupoUsuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class GrupoUsuarioTest extends TestCase
{
    use AutenticaUsuario;
    use RefreshDatabase;

    /** @var array<string, string> */
    private array $headers;

    private int $contaId;

    protected function setUp(): void
    {
        parent::setUp();
        [, $this->headers] = $this->autenticarConta('frota');
        $this->contaId = app('conta.atual');
    }

    public function test_administrador_cria_lista_edita_e_remove_grupo(): void
    {
        $id = $this->postJson('/api/conta/grupos-usuarios', [
            'nome' => 'Vendas',
            'permissoes' => ['centros_custo', 'centros_custo'],
        ], $this->headers)->assertStatus(201)->json('id');

        $this->getJson('/api/conta/grupos-usuarios', $this->headers)
            ->assertOk()
            ->assertJsonPath('grupos.0.nome', 'Vendas')
            ->assertJsonPath('grupos.0.permissoes', ['centros_custo'])
            ->assertJsonStructure(['permissoes' => ['grupos']]);

        $this->putJson("/api/conta/grupos-usuarios/{$id}", [
            'nome' => 'Vendas SP', 'permissoes' => ['motoristas'],
        ], $this->headers)->assertOk()->assertJsonPath('nome', 'Vendas SP');

        $this->deleteJson("/api/conta/grupos-usuarios/{$id}", [], $this->headers)->assertOk();
        $this->assertSame(0, GrupoUsuario::count());
    }

    public function test_valida_nome_unico_e_permissao_desconhecida(): void
    {
        $this->postJson('/api/conta/grupos-usuarios', ['nome' => 'A', 'permissoes' => []], $this->headers)->assertStatus(201);
        $this->postJson('/api/conta/grupos-usuarios', ['nome' => 'A', 'permissoes' => []], $this->headers)->assertStatus(422);
        $this->postJson('/api/conta/grupos-usuarios', ['nome' => 'B', 'permissoes' => ['voar']], $this->headers)->assertStatus(422);
    }

    public function test_grupo_concede_permissao_ao_motorista_alem_do_papel(): void
    {
        [$motorista, $headersMotorista] = $this->autenticarConta('frota', Conta::find($this->contaId));
        $motorista->forceFill(['papel_frota' => 'motorista'])->save();
        app()->instance('conta.atual', $this->contaId);

        $this->getJson('/api/conta/centros-custo', $headersMotorista)->assertStatus(403);

        $this->postJson('/api/conta/grupos-usuarios', [
            'nome' => 'Apoio', 'permissoes' => ['centros_custo'], 'membros' => [$motorista->id],
        ], $this->headers)->assertStatus(201)->assertJsonPath('membros.0.id', $motorista->id);

        $this->getJson('/api/conta/centros-custo', $headersMotorista)->assertOk();
        // Só o que o grupo concede.
        $this->getJson('/api/conta/motoristas', $headersMotorista)->assertStatus(403);
        $this->getJson('/api/conta/grupos-usuarios', $headersMotorista)->assertStatus(403);
    }

    public function test_gestor_nao_gerencia_grupos_sem_permissao(): void
    {
        [$gestor, $headersGestor] = $this->autenticarConta('frota', Conta::find($this->contaId));
        $gestor->forceFill(['papel_frota' => 'gestor'])->save();
        app()->instance('conta.atual', $this->contaId);

        $this->getJson('/api/conta/centros-custo', $headersGestor)->assertOk();
        $this->getJson('/api/conta/grupos-usuarios', $headersGestor)->assertStatus(403);
    }

    public function test_quem_gerencia_grupos_nao_concede_o_que_nao_tem(): void
    {
        [$gerente, $headersGerente] = $this->autenticarConta('frota', Conta::find($this->contaId));
        $gerente->forceFill(['papel_frota' => 'motorista'])->save();
        app()->instance('conta.atual', $this->contaId);

        $this->postJson('/api/conta/grupos-usuarios', [
            'nome' => 'Gerentes', 'permissoes' => ['grupos'], 'menus_liberados' => ['cadastros'], 'membros' => [$gerente->id],
        ], $this->headers)->assertStatus(201);

        // Tem `grupos`, mas não tem `motoristas` nem o menu `veiculos`: não pode conceder esses.
        $this->postJson('/api/conta/grupos-usuarios', [
            'nome' => 'Fora', 'permissoes' => ['motoristas'],
        ], $headersGerente)->assertStatus(403);

        $this->postJson('/api/conta/grupos-usuarios', [
            'nome' => 'Menu', 'permissoes' => [], 'menus_liberados' => ['veiculos'],
        ], $headersGerente)->assertStatus(403);

        $this->postJson('/api/conta/grupos-usuarios', [
            'nome' => 'Dentro', 'permissoes' => ['grupos'], 'menus_liberados' => ['cadastros'],
        ], $headersGerente)->assertStatus(201);
    }

    public function test_quem_gerencia_grupos_nao_edita_grupo_acima_do_seu_acesso(): void
    {
        $idAdmin = $this->postJson('/api/conta/grupos-usuarios', [
            'nome' => 'Financeiro', 'permissoes' => ['centros_custo', 'motoristas'],
        ], $this->headers)->assertStatus(201)->json('id');

        [$gerente, $headersGerente] = $this->autenticarConta('frota', Conta::find($this->contaId));
        $gerente->forceFill(['papel_frota' => 'motorista'])->save();
        app()->instance('conta.atual', $this->contaId);

        $this->postJson('/api/conta/grupos-usuarios', [
            'nome' => 'Gerentes', 'permissoes' => ['grupos'], 'membros' => [$gerente->id],
        ], $this->headers)->assertStatus(201);

        // Não tem `motoristas` nem `centros_custo`: não mexe no grupo Financeiro, nem o apaga.
        $this->putJson("/api/conta/grupos-usuarios/{$idAdmin}", [
            'nome' => 'Financeiro', 'permissoes' => [],
        ], $headersGerente)->assertStatus(403);
        $this->deleteJson("/api/conta/grupos-usuarios/{$idAdmin}", [], $headersGerente)->assertStatus(403);
        $this->assertSame(2, GrupoUsuario::count());
    }

    public function test_grupo_nao_libera_editar_nem_apagar_uso(): void
    {
        [$motorista, $headersMotorista] = $this->autenticarConta('frota', Conta::find($this->contaId));
        $motorista->forceFill(['papel_frota' => 'motorista'])->save();
        app()->instance('conta.atual', $this->contaId);

        $this->postJson('/api/conta/grupos-usuarios', [
            'nome' => 'Auditoria', 'permissoes' => ['usos_corrigir'], 'membros' => [$motorista->id],
        ], $this->headers)->assertStatus(201);

        $this->putJson('/api/conta/usos/1', [], $headersMotorista)->assertStatus(403);
        $this->deleteJson('/api/conta/usos/1', [], $headersMotorista)->assertStatus(403);
        $this->putJson('/api/conta/veiculos/1/km', ['km' => 1], $headersMotorista)->assertStatus(403);
    }

    public function test_nao_aceita_membro_de_outra_frota(): void
    {
        [$outro] = $this->autenticarConta('frota');
        app()->instance('conta.atual', $this->contaId);

        $this->postJson('/api/conta/grupos-usuarios', [
            'nome' => 'X', 'permissoes' => [], 'membros' => [$outro->id],
        ], $this->headers)->assertStatus(422);
    }
}
