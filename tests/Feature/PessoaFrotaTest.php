<?php

namespace Tests\Feature;

use App\Models\Conta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class PessoaFrotaTest extends TestCase
{
    use AutenticaUsuario;
    use RefreshDatabase;

    /** @var array<string, string> */
    private array $headersAdmin;

    private Conta $conta;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->admin, $this->headersAdmin] = $this->autenticarConta('frota');
        $this->conta = Conta::find(app('conta.atual'));
    }

    private User $admin;

    /** @return array{0: User, 1: array<string, string>} */
    private function pessoa(string $papel): array
    {
        [$usuario, $headers] = $this->autenticarConta('frota', $this->conta);
        $usuario->forceFill(['papel_frota' => $papel])->save();
        app()->instance('conta.atual', $this->conta->id);

        return [$usuario, $headers];
    }

    public function test_administrador_lista_as_pessoas_da_frota_com_o_papel(): void
    {
        $this->pessoa('gestor');

        $this->getJson('/api/conta/pessoas', $this->headersAdmin)
            ->assertOk()
            ->assertJsonFragment(['papel' => 'gestor'])
            ->assertJsonFragment(['papel' => 'administrador']);
    }

    public function test_gestor_nao_mexe_em_pessoas(): void
    {
        [, $headersGestor] = $this->pessoa('gestor');

        $this->getJson('/api/conta/pessoas', $headersGestor)->assertStatus(403);
        $this->postJson('/api/conta/pessoas', [
            'nome' => 'Novo', 'email' => 'novo@example.test', 'papel' => 'gestor',
        ], $headersGestor)->assertStatus(403);
    }

    public function test_administrador_convida_um_novo_gestor(): void
    {
        $this->postJson('/api/conta/pessoas', [
            'nome' => 'Carla Gestora', 'email' => 'carla@example.test', 'papel' => 'gestor',
        ], $this->headersAdmin)
            ->assertStatus(201)
            ->assertJsonPath('papel', 'gestor');

        $nova = User::where('email', 'carla@example.test')->first();
        $this->assertSame('frota', $nova->perfil);
        $this->assertSame($this->conta->id, $nova->conta_id);
        $this->assertSame('gestor', $nova->papelNaConta());
    }

    public function test_convite_nao_repete_email_nem_cria_motorista(): void
    {
        $this->postJson('/api/conta/pessoas', [
            'nome' => 'X', 'email' => $this->admin->email, 'papel' => 'gestor',
        ], $this->headersAdmin)->assertStatus(422);

        $this->postJson('/api/conta/pessoas', [
            'nome' => 'Y', 'email' => 'y@example.test', 'papel' => 'motorista',
        ], $this->headersAdmin)->assertStatus(422);
    }

    public function test_administrador_troca_o_papel_de_uma_pessoa(): void
    {
        [$pessoa] = $this->pessoa('motorista');

        $this->putJson("/api/conta/pessoas/{$pessoa->id}/papel", ['papel' => 'gestor'], $this->headersAdmin)
            ->assertOk()
            ->assertJsonPath('papel', 'gestor');

        $this->assertSame('gestor', $pessoa->fresh()->papelNaConta());
    }

    public function test_nao_troca_o_proprio_papel(): void
    {
        $this->putJson("/api/conta/pessoas/{$this->admin->id}/papel", ['papel' => 'gestor'], $this->headersAdmin)
            ->assertStatus(422);
    }

    public function test_nao_mexe_em_pessoa_de_outra_frota(): void
    {
        $outraConta = Conta::create(['tipo' => 'frota', 'nome' => 'Outra frota']);
        $estranho = User::factory()->create([
            'oficina_id' => null, 'perfil' => 'frota', 'conta_id' => $outraConta->id, 'papel_frota' => 'motorista',
        ]);

        $this->putJson("/api/conta/pessoas/{$estranho->id}/papel", ['papel' => 'gestor'], $this->headersAdmin)
            ->assertStatus(404);
    }
}
