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

    private function grupoComMenusOcultos(array $menus, array $membrosIds = []): GrupoUsuario
    {
        $grupo = GrupoUsuario::create([
            'nome' => 'Grupo ' . implode('-', $menus) . count($membrosIds),
            'permissoes' => [],
            'menus_ocultos' => $menus,
        ]);
        $grupo->membros()->attach($membrosIds);

        return $grupo;
    }

    private function idDoUsuarioDoHeader(array $headers): int
    {
        return $this->getJson('/api/me', $headers)->json('id');
    }

    public function test_gestor_sem_grupo_ve_todos_os_menus(): void
    {
        $headers = $this->headersDe('gestor');

        $this->getJson('/api/me', $headers)
            ->assertOk()
            ->assertJsonPath('menus', ['veiculos', 'agenda', 'cadastros']);
    }

    public function test_grupo_que_esconde_cadastros_bloqueia_a_tela_e_as_escritas_do_gestor(): void
    {
        $headers = $this->headersDe('gestor');
        $this->grupoComMenusOcultos(['cadastros'], [$this->idDoUsuarioDoHeader($headers)]);

        $this->getJson('/api/me', $headers)
            ->assertOk()
            ->assertJsonPath('menus', ['veiculos', 'agenda']);

        $this->postJson('/api/conta/centros-custo', ['nome' => 'Vendas'], $headers)->assertStatus(403);
        $this->getJson('/api/conta/centros-custo/historico', $headers)->assertStatus(403);
        $this->getJson('/api/conta/grupos-usuarios', $headers)->assertStatus(403);

        // A lista de centros continua livre: os formulários de uso precisam dela.
        $this->getJson('/api/conta/centros-custo', $headers)->assertOk();
    }

    public function test_administrador_nunca_tem_menu_escondido(): void
    {
        $this->grupoComMenusOcultos(['cadastros', 'agenda'], [$this->idDoUsuarioDoHeader($this->headersAdmin)]);

        $this->getJson('/api/me', $this->headersAdmin)
            ->assertOk()
            ->assertJsonPath('menus', ['veiculos', 'agenda', 'cadastros']);

        $this->postJson('/api/conta/centros-custo', ['nome' => 'Compras'], $this->headersAdmin)->assertStatus(201);
    }

    public function test_menus_escondidos_de_grupos_diferentes_se_somam(): void
    {
        $headers = $this->headersDe('gestor');
        $id = $this->idDoUsuarioDoHeader($headers);
        $this->grupoComMenusOcultos(['agenda'], [$id]);
        $this->grupoComMenusOcultos(['cadastros'], [$id]);

        $this->getJson('/api/me', $headers)
            ->assertOk()
            ->assertJsonPath('menus', ['veiculos']);
    }

    public function test_grupo_so_afeta_quem_pertence_a_ele(): void
    {
        $fora = $this->headersDe('gestor');
        $dentro = $this->headersDe('gestor');
        $this->grupoComMenusOcultos(['cadastros'], [$this->idDoUsuarioDoHeader($dentro)]);

        $this->postJson('/api/conta/centros-custo', ['nome' => 'Fora'], $fora)->assertStatus(201);
        $this->postJson('/api/conta/centros-custo', ['nome' => 'Dentro'], $dentro)->assertStatus(403);
    }

    public function test_grupo_valida_menu_desconhecido(): void
    {
        $this->postJson('/api/conta/grupos-usuarios', [
            'nome' => 'X', 'permissoes' => [], 'menus_ocultos' => ['financeiro'],
        ], $this->headersAdmin)->assertStatus(422);
    }
}
