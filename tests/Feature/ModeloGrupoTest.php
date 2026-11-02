<?php

namespace Tests\Feature;

use App\Models\Conta;
use App\Models\ModeloGrupo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class ModeloGrupoTest extends TestCase
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

    public function test_administrador_salva_lista_e_remove_um_modelo(): void
    {
        $id = $this->postJson('/api/conta/modelos-grupo', [
            'nome' => 'Recepção',
            'descricao' => 'Quem atende e reserva',
            'permissoes' => ['centros_custo'],
            'menus_liberados' => ['cadastros'],
        ], $this->headers)->assertStatus(201)->assertJsonPath('nome', 'Recepção')->json('id');

        $this->getJson('/api/conta/modelos-grupo', $this->headers)
            ->assertOk()
            ->assertJsonPath('0.permissoes', ['centros_custo'])
            ->assertJsonPath('0.menus_liberados', ['cadastros']);

        $this->deleteJson("/api/conta/modelos-grupo/{$id}", [], $this->headers)->assertOk();
        $this->assertSame(0, ModeloGrupo::count());
    }

    public function test_nome_do_modelo_e_unico_na_conta(): void
    {
        $this->postJson('/api/conta/modelos-grupo', ['nome' => 'A', 'permissoes' => [], 'menus_liberados' => []], $this->headers)->assertStatus(201);
        $this->postJson('/api/conta/modelos-grupo', ['nome' => 'A', 'permissoes' => [], 'menus_liberados' => []], $this->headers)->assertStatus(422);
    }

    public function test_so_salva_modelo_com_o_que_quem_salva_tem(): void
    {
        [$gerente, $headersGerente] = $this->autenticarConta('frota', Conta::find($this->contaId));
        $gerente->forceFill(['papel_frota' => 'motorista'])->save();
        app()->instance('conta.atual', $this->contaId);

        $this->postJson('/api/conta/grupos-usuarios', [
            'nome' => 'Gerentes', 'permissoes' => ['grupos'], 'menus_liberados' => ['cadastros'], 'membros' => [$gerente->id],
        ], $this->headers)->assertStatus(201);

        // Tem `grupos`, mas não tem `motoristas`: não pode salvar um modelo que o concede.
        $this->postJson('/api/conta/modelos-grupo', [
            'nome' => 'Cadastros', 'permissoes' => ['motoristas'], 'menus_liberados' => ['cadastros'],
        ], $headersGerente)->assertStatus(403);

        $this->postJson('/api/conta/modelos-grupo', [
            'nome' => 'Acessos', 'permissoes' => ['grupos'], 'menus_liberados' => ['cadastros'],
        ], $headersGerente)->assertStatus(201);
    }

    public function test_quem_nao_gerencia_grupos_nao_ve_os_modelos(): void
    {
        [$motorista, $headersMotorista] = $this->autenticarConta('frota', Conta::find($this->contaId));
        $motorista->forceFill(['papel_frota' => 'motorista'])->save();
        app()->instance('conta.atual', $this->contaId);

        $this->getJson('/api/conta/modelos-grupo', $headersMotorista)->assertStatus(403);
    }

    public function test_modelo_de_outra_conta_nao_aparece(): void
    {
        $outra = Conta::create(['tipo' => 'frota', 'nome' => 'Outra frota']);
        $alheio = new ModeloGrupo(['nome' => 'Alheio', 'permissoes' => [], 'menus_liberados' => []]);
        $alheio->conta_id = $outra->id;
        $alheio->save();

        $this->getJson('/api/conta/modelos-grupo', $this->headers)
            ->assertOk()
            ->assertJsonCount(0);
    }
}
