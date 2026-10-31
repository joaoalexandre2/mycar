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

    public function test_nao_aceita_membro_de_outra_frota(): void
    {
        [$outro] = $this->autenticarConta('frota');
        app()->instance('conta.atual', $this->contaId);

        $this->postJson('/api/conta/grupos-usuarios', [
            'nome' => 'X', 'permissoes' => [], 'membros' => [$outro->id],
        ], $this->headers)->assertStatus(422);
    }
}
