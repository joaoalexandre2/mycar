<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\User;
use App\Models\VeiculoConta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

/**
 * Visão de suporte do operador: lê o que um usuário cadastrou e registra cada acesso.
 */
class AdminConteudoTest extends TestCase
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

    private function secao(array $resposta, string $chave): array
    {
        return collect($resposta['secoes'])->firstWhere('chave', $chave);
    }

    public function test_rotas_exigem_autenticacao_e_perfil_de_administrador(): void
    {
        $this->getJson('/api/admin/contas/1/conteudo')->assertStatus(401);
        $this->getJson('/api/admin/acessos')->assertStatus(401);

        [$dono, $headers] = $this->autenticar();

        $this->getJson("/api/admin/contas/{$dono->id}/conteudo", $headers)->assertStatus(403);
        $this->getJson('/api/admin/acessos', $headers)->assertStatus(403);

        $this->assertSame(0, DB::table('acessos_admin')->count());
    }

    public function test_mostra_o_conteudo_da_oficina_inclusive_cpf_e_telefone(): void
    {
        [$dono] = $this->autenticar();
        Cliente::factory()->create(['nome' => 'Maria Souza', 'cpf' => '123.456.789-09', 'telefone' => '(51) 99999-0000']);

        [, $headers] = $this->autenticarAdmin();
        // Cliente de outra oficina: não pode aparecer na visão do dono acima.
        Cliente::factory()->create(['nome' => 'Cliente de Outra Oficina']);

        $resposta = $this->getJson("/api/admin/contas/{$dono->id}/conteudo", $headers)
            ->assertStatus(200)
            ->assertJsonPath('usuario.email', $dono->email)
            ->assertJsonPath('usuario.perfil', 'oficina');

        $clientes = $this->secao($resposta->json(), 'clientes');

        $this->assertSame(1, $clientes['total']);
        $this->assertSame('Maria Souza', $clientes['linhas'][0]['nome']);
        $this->assertSame('123.456.789-09', $clientes['linhas'][0]['cpf']);
        $this->assertSame('(51) 99999-0000', $clientes['linhas'][0]['telefone']);
        $this->assertArrayNotHasKey('oficina_id', $clientes['linhas'][0]);
        $this->assertStringNotContainsString('Cliente de Outra Oficina', $resposta->getContent());
    }

    public function test_mostra_o_conteudo_de_uma_conta_pessoa(): void
    {
        [$dono, $headersDono] = $this->autenticarConta('pessoa');
        VeiculoConta::create([
            'placa' => 'FJB4E12', 'marca' => 'Fiat', 'modelo' => 'Uno', 'ano' => 2018, 'apelido' => 'Meu Uno',
        ]);

        [, $headers] = $this->autenticarAdmin();

        $resposta = $this->getJson("/api/admin/contas/{$dono->id}/conteudo", $headers)
            ->assertStatus(200)
            ->assertJsonPath('usuario.perfil', 'pessoa');

        $veiculos = $this->secao($resposta->json(), 'veiculos');

        $this->assertSame(1, $veiculos['total']);
        $this->assertSame('FJB4E12', $veiculos['linhas'][0]['placa']);
        $this->assertSame('Meu Uno', $veiculos['linhas'][0]['apelido']);
        $this->assertArrayNotHasKey('conta_id', $veiculos['linhas'][0]);
        $this->assertNotEmpty($headersDono);
    }

    public function test_cada_abertura_fica_registrada_e_aparece_na_lista_de_acessos(): void
    {
        [$dono] = $this->autenticar();
        [$admin, $headers] = $this->autenticarAdmin();

        $this->getJson("/api/admin/contas/{$dono->id}/conteudo", $headers)->assertStatus(200);
        $this->getJson("/api/admin/contas/{$dono->id}/conteudo", $headers)->assertStatus(200);

        $this->assertSame(2, DB::table('acessos_admin')->count());
        $this->assertDatabaseHas('acessos_admin', [
            'admin_id' => $admin->id,
            'usuario_id' => $dono->id,
            'perfil' => 'oficina',
        ]);

        $this->getJson('/api/admin/acessos', $headers)
            ->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.admin.email', $admin->email)
            ->assertJsonPath('data.0.usuario.email', $dono->email)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_usuario_inexistente_da_404_e_nao_registra_acesso(): void
    {
        [, $headers] = $this->autenticarAdmin();

        $this->getJson('/api/admin/contas/99999/conteudo', $headers)->assertStatus(404);

        $this->assertSame(0, DB::table('acessos_admin')->count());
    }

    public function test_lista_de_contas_continua_sem_o_conteudo(): void
    {
        [$dono] = $this->autenticar();
        Cliente::factory()->create(['nome' => 'Maria Souza', 'cpf' => '123.456.789-09']);

        [, $headers] = $this->autenticarAdmin();

        $conteudo = $this->getJson('/api/admin/contas', $headers)->assertStatus(200)->getContent();

        $this->assertStringNotContainsString('123.456.789-09', $conteudo);
        $this->assertStringNotContainsString('Maria Souza', $conteudo);
        $this->assertNotNull($dono->id);
    }
}
