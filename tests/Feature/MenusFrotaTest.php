<?php

namespace Tests\Feature;

use App\Models\Conta;
use App\Models\GrupoUsuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class MenusFrotaTest extends TestCase
{
    use AutenticaUsuario;
    use RefreshDatabase;

    /** @var array<string, string> */
    private array $headersAdmin;

    private Conta $conta;

    protected function setUp(): void
    {
        parent::setUp();
        [, $this->headersAdmin] = $this->autenticarConta('frota');
        $this->conta = Conta::find(app('conta.atual'));
    }

    /** @return array<string, string> */
    private function headersDe(string $papel): array
    {
        [$usuario, $headers] = $this->autenticarConta('frota', $this->conta);
        $usuario->forceFill(['papel_frota' => $papel])->save();
        app()->instance('conta.atual', $this->conta->id);

        return $headers;
    }

    /**
     * @param  list<string>  $menus
     * @param  list<int>  $membrosIds
     */
    private function grupoQueLibera(array $menus, array $membrosIds = [], array $permissoes = []): GrupoUsuario
    {
        $grupo = GrupoUsuario::create([
            'nome' => 'Grupo ' . uniqid(),
            'permissoes' => $permissoes,
            'menus_liberados' => $menus,
        ]);
        $grupo->membros()->attach($membrosIds);

        return $grupo;
    }

    private function idDe(array $headers): int
    {
        return $this->getJson('/api/me', $headers)->json('id');
    }

    public function test_gestor_ve_todos_os_menus_sem_precisar_de_grupo(): void
    {
        $headers = $this->headersDe('gestor');

        $this->getJson('/api/me', $headers)
            ->assertOk()
            ->assertJsonPath('menus', ['veiculos', 'agenda', 'cadastros']);

        $this->postJson('/api/conta/centros-custo', ['nome' => 'Vendas'], $headers)->assertStatus(201);
    }

    public function test_administrador_ve_todos_os_menus_mesmo_sem_grupo(): void
    {
        $this->getJson('/api/me', $this->headersAdmin)
            ->assertOk()
            ->assertJsonPath('menus', ['veiculos', 'agenda', 'cadastros']);

        $this->postJson('/api/conta/centros-custo', ['nome' => 'Compras'], $this->headersAdmin)->assertStatus(201);
    }

    public function test_motorista_sem_grupo_nao_ve_os_menus_de_gestao(): void
    {
        $headers = $this->headersDe('motorista');

        $this->getJson('/api/me', $headers)
            ->assertOk()
            ->assertJsonPath('menus', []);

        $this->postJson('/api/conta/centros-custo', ['nome' => 'Vendas'], $headers)->assertStatus(403);
    }

    public function test_grupo_que_libera_cadastros_habilita_a_tela_e_as_escritas_do_motorista(): void
    {
        $headers = $this->headersDe('motorista');
        $this->grupoQueLibera(['cadastros'], [$this->idDe($headers)], ['centros_custo']);

        $this->getJson('/api/me', $headers)
            ->assertOk()
            ->assertJsonPath('menus', ['cadastros']);

        $this->postJson('/api/conta/centros-custo', ['nome' => 'Vendas'], $headers)->assertStatus(201);
        $this->getJson('/api/conta/centros-custo/historico', $headers)->assertOk();
    }

    public function test_menus_liberados_por_grupos_diferentes_se_somam(): void
    {
        $headers = $this->headersDe('motorista');
        $id = $this->idDe($headers);
        $this->grupoQueLibera(['agenda'], [$id]);
        $this->grupoQueLibera(['cadastros'], [$id]);

        $this->getJson('/api/me', $headers)
            ->assertOk()
            ->assertJsonPath('menus', ['agenda', 'cadastros']);
    }

    public function test_grupo_so_libera_para_quem_pertence_a_ele(): void
    {
        $fora = $this->headersDe('motorista');
        $dentro = $this->headersDe('motorista');
        $this->grupoQueLibera(['agenda'], [$this->idDe($dentro)]);

        $this->getJson('/api/me', $fora)->assertJsonPath('menus', []);
        $this->getJson('/api/me', $dentro)->assertJsonPath('menus', ['agenda']);
    }

    public function test_fora_da_frota_os_menus_nao_se_aplicam(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');

        $this->getJson('/api/me', $headers)
            ->assertOk()
            ->assertJsonPath('menus', null);
    }

    public function test_grupo_valida_menu_desconhecido(): void
    {
        $this->postJson('/api/conta/grupos-usuarios', [
            'nome' => 'X', 'permissoes' => [], 'menus_liberados' => ['financeiro'],
        ], $this->headersAdmin)->assertStatus(422);
    }
}
