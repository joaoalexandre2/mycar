<?php

namespace Tests\Feature;

use App\Models\Sugestao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class SugestaoTest extends TestCase
{
    use RefreshDatabase;
    use AutenticaUsuario;

    private function dados(array $extra = []): array
    {
        return array_merge([
            'categoria' => 'nova_funcao',
            'titulo' => 'Avisar a troca de pneus',
            'descricao' => 'Seria bom receber um aviso quando os pneus chegarem perto dos 40 mil km.',
        ], $extra);
    }

    /** @return array{0: User, 1: array<string, string>} */
    private function autenticarEquipe(): array
    {
        [$admin, $headers] = $this->autenticar();
        $admin->forceFill(['is_super_admin' => true])->save();

        return [$admin, $headers];
    }

    public function test_exige_autenticacao(): void
    {
        $this->getJson('/api/sugestoes')->assertStatus(401);
        $this->postJson('/api/sugestoes', $this->dados())->assertStatus(401);
    }

    public function test_qualquer_perfil_envia_e_ve_as_proprias(): void
    {
        [, $oficina] = $this->autenticar();
        [, $pessoa] = $this->autenticarConta('pessoa');

        $this->postJson('/api/sugestoes', $this->dados(), $oficina)
            ->assertStatus(201)->assertJsonPath('status', 'nova')->assertJsonPath('titulo', 'Avisar a troca de pneus');
        $this->postJson('/api/sugestoes', $this->dados(['titulo' => 'Exportar relatório em PDF']), $pessoa)->assertStatus(201);

        $this->getJson('/api/sugestoes', $oficina)->assertStatus(200)->assertJsonCount(1)->assertJsonPath('0.titulo', 'Avisar a troca de pneus');
        $this->getJson('/api/sugestoes', $pessoa)->assertJsonCount(1)->assertJsonPath('0.titulo', 'Exportar relatório em PDF');

        $this->assertSame(['oficina', 'pessoa'], Sugestao::orderBy('id')->pluck('perfil')->all());
    }

    public function test_valida_o_envio(): void
    {
        [, $headers] = $this->autenticar();

        $this->postJson('/api/sugestoes', [], $headers)
            ->assertStatus(422)->assertJsonValidationErrors(['categoria', 'titulo', 'descricao']);
        $this->postJson('/api/sugestoes', $this->dados(['categoria' => 'xingamento']), $headers)->assertStatus(422)->assertJsonValidationErrors('categoria');
        $this->postJson('/api/sugestoes', $this->dados(['titulo' => 'ab']), $headers)->assertStatus(422)->assertJsonValidationErrors('titulo');
        $this->postJson('/api/sugestoes', $this->dados(['descricao' => 'curta']), $headers)->assertStatus(422)->assertJsonValidationErrors('descricao');
        $this->postJson('/api/sugestoes', $this->dados(['descricao' => str_repeat('a', 2001)]), $headers)->assertStatus(422);
    }

    public function test_o_usuario_nao_ve_a_resposta_de_outro_nem_as_rotas_da_equipe(): void
    {
        [, $a] = $this->autenticar();
        [, $b] = $this->autenticar();
        $this->postJson('/api/sugestoes', $this->dados(), $a)->assertStatus(201);

        $this->getJson('/api/sugestoes', $b)->assertJsonCount(0);
        $this->getJson('/api/admin/sugestoes', $a)->assertStatus(403);
        $this->putJson('/api/admin/sugestoes/1', ['status' => 'feita'], $a)->assertStatus(403);
    }

    public function test_a_equipe_ve_todas_com_o_autor_e_filtra_por_status(): void
    {
        [, $usuario] = $this->autenticarConta('frota');
        $this->postJson('/api/sugestoes', $this->dados(), $usuario)->assertStatus(201);
        $this->postJson('/api/sugestoes', $this->dados(['titulo' => 'Outra ideia boa']), $usuario)->assertStatus(201);
        Sugestao::where('titulo', 'Outra ideia boa')->update(['status' => 'planejada']);

        [, $equipe] = $this->autenticarEquipe();

        $todas = $this->getJson('/api/admin/sugestoes', $equipe)->assertStatus(200)->assertJsonCount(2);
        $this->assertSame('frota', $todas->json('0.autor.perfil'));
        $this->assertNotEmpty($todas->json('0.autor.nome'));

        $this->getJson('/api/admin/sugestoes?status=planejada', $equipe)->assertJsonCount(1)->assertJsonPath('0.titulo', 'Outra ideia boa');
        $this->getJson('/api/admin/sugestoes?status=invalido', $equipe)->assertStatus(422);
    }

    public function test_a_equipe_responde_e_o_usuario_ve_o_retorno(): void
    {
        [, $usuario] = $this->autenticar();
        $id = $this->postJson('/api/sugestoes', $this->dados(), $usuario)->json('id');

        [, $equipe] = $this->autenticarEquipe();

        $this->putJson("/api/admin/sugestoes/{$id}", ['status' => 'planejada', 'resposta' => 'Boa ideia, entra no próximo mês.'], $equipe)
            ->assertStatus(200)->assertJsonPath('status', 'planejada');

        $this->getJson('/api/sugestoes', $usuario)
            ->assertJsonPath('0.status', 'planejada')->assertJsonPath('0.resposta', 'Boa ideia, entra no próximo mês.');

        $this->putJson("/api/admin/sugestoes/{$id}", ['status' => 'voando'], $equipe)->assertStatus(422);
        $this->putJson('/api/admin/sugestoes/999', ['status' => 'feita'], $equipe)->assertStatus(404);
    }

    public function test_a_lista_do_usuario_nao_expoe_dados_do_autor(): void
    {
        [, $usuario] = $this->autenticar();
        $this->postJson('/api/sugestoes', $this->dados(), $usuario)->assertStatus(201);

        $this->assertArrayNotHasKey('autor', $this->getJson('/api/sugestoes', $usuario)->json('0'));
    }
}
