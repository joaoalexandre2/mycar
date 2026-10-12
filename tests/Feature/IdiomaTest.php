<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class IdiomaTest extends TestCase
{
    use AutenticaUsuario;
    use RefreshDatabase;

    public function test_o_idioma_padrao_e_portugues_do_brasil(): void
    {
        $this->assertSame('pt_BR', app()->getLocale());
    }

    public function test_mensagens_de_validacao_saem_em_portugues_com_nome_amigavel(): void
    {
        [, $headers] = $this->autenticar();

        $resposta = $this->postJson('/api/veiculos', [], $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cliente_id', 'placa', 'marca', 'modelo', 'ano']);

        $this->assertSame('O campo cliente é obrigatório.', $resposta->json('errors.cliente_id.0'));
        $this->assertSame('O campo placa é obrigatório.', $resposta->json('errors.placa.0'));
    }

    public function test_nao_existe_mensagem_de_validacao_em_ingles(): void
    {
        [, $headers] = $this->autenticar();

        $resposta = $this->postJson('/api/clientes', [
            'nome' => str_repeat('a', 300),
            'cpf' => '123',
            'telefone' => '11999999999',
            'email' => 'nao-e-email',
        ], $headers)->assertStatus(422);

        $mensagens = collect($resposta->json('errors'))->flatten()->implode(' ');

        $this->assertStringNotContainsString('field', $mensagens);
        $this->assertStringNotContainsString('must', $mensagens);
        $this->assertStringContainsString('e-mail', $mensagens);
    }
}
