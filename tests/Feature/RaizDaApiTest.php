<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * A raiz do domínio da API responde um JSON mínimo e estável, sem versão,
 * ambiente nem dado interno; a sobra de desenvolvimento /teste não existe
 * mais, e o /up e as rotas da API seguem como estavam.
 */
class RaizDaApiTest extends TestCase
{
    public function test_a_raiz_responde_json_minimo_e_estavel(): void
    {
        $resposta = $this->getJson('/');

        $resposta->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson(['app' => 'MyCar API', 'status' => 'ok']);

        $corpo = $resposta->getContent();
        $this->assertStringNotContainsString('Laravel', $corpo);
        $this->assertStringNotContainsString('funcionando', $corpo);
        $this->assertStringNotContainsString((string) app()->version(), $corpo);
        $this->assertStringNotContainsString((string) app()->environment(), $corpo);
    }

    public function test_a_rota_de_teste_nao_existe_mais(): void
    {
        $this->get('/teste')->assertNotFound();
    }

    public function test_o_health_check_continua_no_up(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_as_rotas_da_api_continuam_respondendo(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }
}
