<?php

namespace Tests\Feature;

use App\Models\Motorista;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

/**
 * O frontend mostra ao usuário o "message" e os "errors" que a API devolve:
 * tudo precisa sair em português, com o nome do campo traduzido e sem
 * detalhe interno (classe, rota, SQL, trace).
 */
class MensagensEmPortuguesTest extends TestCase
{
    use AutenticaUsuario;
    use RefreshDatabase;

    /** Nada de texto do framework em inglês na resposta. */
    private function assertSemIngles(string $texto): void
    {
        foreach (['The ', 'field', 'must', 'more error', 'Too Many', 'Unauthenticated', 'Not Found', 'unauthorized', 'Server Error', 'could not be found', 'not supported', 'No query results'] as $ingles) {
            $this->assertStringNotContainsString($ingles, $texto);
        }
    }

    // ------------------------------------------------------------ validação

    public function test_login_invalido_devolve_mensagem_e_erros_em_portugues(): void
    {
        $resposta = $this->postJson('/api/login', ['email' => 'nao-e-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);

        $this->assertSame('O campo e-mail deve ser um e-mail válido.', $resposta->json('errors.email.0'));
        $this->assertSame('O campo senha é obrigatório.', $resposta->json('errors.password.0'));
        // O resumo do "message" também é traduzido (antes saía "(and 1 more error)").
        $this->assertSame('O campo e-mail deve ser um e-mail válido. (e mais 1 erro)', $resposta->json('message'));
    }

    public function test_registro_invalido_devolve_mensagem_e_erros_em_portugues(): void
    {
        $resposta = $this->postJson('/api/register', [
            'perfil' => 'frota',
            'password' => 'curta',
            'password_confirmation' => 'outra',
        ])->assertStatus(422);

        $this->assertSame('O campo nome da frota é obrigatório quando perfil for frota.', $resposta->json('errors.nome_frota.0'));
        $this->assertSame('O campo nome é obrigatório.', $resposta->json('errors.name.0'));
        $this->assertSame('O campo e-mail é obrigatório.', $resposta->json('errors.email.0'));
        $this->assertContains('A confirmação do campo senha não confere.', $resposta->json('errors.password'));
        $this->assertStringContainsString('(e mais ', $resposta->json('message'));
        $this->assertStringContainsString(' erros)', $resposta->json('message'));
        $this->assertSemIngles($resposta->getContent());
    }

    public function test_veiculo_da_frota_invalido_devolve_campos_traduzidos(): void
    {
        [, $headers] = $this->autenticarConta('frota');

        $resposta = $this->postJson('/api/conta/veiculos', ['tipo_veiculo' => 'disco-voador', 'ano' => 'abc'], $headers)
            ->assertStatus(422);

        $this->assertSame('O campo placa é obrigatório.', $resposta->json('errors.placa.0'));
        $this->assertSame('O valor selecionado para tipo de veículo é inválido.', $resposta->json('errors.tipo_veiculo.0'));
        $this->assertSemIngles($resposta->getContent());
    }

    public function test_motorista_invalido_devolve_campos_traduzidos(): void
    {
        [, $headers] = $this->autenticarConta('frota');

        $resposta = $this->postJson('/api/conta/motoristas', [
            'cnh_validade' => 'amanhã talvez',
            'centro_custo_id' => 'vendas',
        ], $headers)->assertStatus(422);

        $this->assertSame('O campo nome é obrigatório.', $resposta->json('errors.nome.0'));
        $this->assertSame('O campo validade da CNH deve ser uma data válida.', $resposta->json('errors.cnh_validade.0'));
        $this->assertSame('O campo centro de custo deve ser um número inteiro.', $resposta->json('errors.centro_custo_id.0'));
        $this->assertSemIngles($resposta->getContent());
    }

    public function test_reserva_e_saida_de_uso_invalidas_devolvem_campos_traduzidos(): void
    {
        [, $headers] = $this->autenticarConta('frota');

        $reserva = $this->postJson('/api/conta/usos', ['motorista_id' => 'x'], $headers)->assertStatus(422);

        $this->assertSame('O campo veículo é obrigatório.', $reserva->json('errors.veiculo_conta_id.0'));
        $this->assertSame('O campo motorista deve ser um número inteiro.', $reserva->json('errors.motorista_id.0'));
        $this->assertSame('O campo início previsto é obrigatório.', $reserva->json('errors.inicio_previsto.0'));
        $this->assertSame('O campo fim previsto é obrigatório.', $reserva->json('errors.fim_previsto.0'));
        $this->assertSemIngles($reserva->getContent());

        $saida = $this->postJson('/api/conta/usos/1/saida', [], $headers)->assertStatus(422);

        $this->assertSame('O campo km de saída é obrigatório.', $saida->json('errors.km_saida.0'));
        $this->assertSame('O campo km de saída é obrigatório.', $saida->json('message'));
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function camposDaApi(): array
    {
        return [
            'km_saida' => ['km_saida', 'km de saída'],
            'km_chegada' => ['km_chegada', 'km de chegada'],
            'centro_custo_id' => ['centro_custo_id', 'centro de custo'],
            'motorista_id' => ['motorista_id', 'motorista'],
            'cnh_validade' => ['cnh_validade', 'validade da CNH'],
            'cnh_numero' => ['cnh_numero', 'número da CNH'],
            'veiculo_conta_id' => ['veiculo_conta_id', 'veículo'],
            'inicio_previsto' => ['inicio_previsto', 'início previsto'],
            'email' => ['email', 'e-mail'],
            'password' => ['password', 'senha'],
            'placa' => ['placa', 'placa'],
        ];
    }

    #[DataProvider('camposDaApi')]
    public function test_nome_do_campo_sai_traduzido_na_mensagem(string $campo, string $nome): void
    {
        $mensagem = Validator::make([], [$campo => 'required'])->errors()->first($campo);

        $this->assertSame("O campo {$nome} é obrigatório.", $mensagem);
    }

    // ------------------------------------------------------- erros HTTP

    public function test_401_sem_sessao_em_portugues(): void
    {
        $this->getJson('/api/me')
            ->assertStatus(401)
            ->assertExactJson(['message' => 'Não autenticado.']);
    }

    public function test_401_do_framework_tambem_sai_em_portugues(): void
    {
        Route::get('/api/_teste/nao-autenticado', fn () => throw new AuthenticationException);

        $this->getJson('/api/_teste/nao-autenticado')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Não autenticado.');
    }

    public function test_403_de_autorizacao_padrao_sai_em_portugues(): void
    {
        Route::get('/api/_teste/proibido', fn () => throw new AuthorizationException);

        $this->getJson('/api/_teste/proibido')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Você não tem permissão para esta ação.');
    }

    public function test_403_dos_middlewares_do_app_continua_com_o_texto_deles(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');

        $this->getJson('/api/conta/motoristas', $headers)
            ->assertStatus(403)
            ->assertJsonPath('message', 'Este recurso não está disponível para o seu tipo de conta.');
    }

    public function test_404_de_rota_inexistente_em_portugues_sem_mostrar_a_rota(): void
    {
        $resposta = $this->getJson('/api/rota-que-nao-existe')
            ->assertStatus(404)
            ->assertJsonPath('message', 'Não encontrado.');

        $this->assertStringNotContainsString('rota-que-nao-existe', $resposta->getContent());
    }

    public function test_404_de_registro_inexistente_nao_mostra_a_classe_do_model(): void
    {
        Route::get('/api/_teste/sem-registro', fn () => throw (new ModelNotFoundException)->setModel(Motorista::class, [5]));

        $resposta = $this->getJson('/api/_teste/sem-registro')
            ->assertStatus(404)
            ->assertJsonPath('message', 'Não encontrado.');

        $this->assertStringNotContainsString('Motorista', $resposta->getContent());
        $this->assertStringNotContainsString('App\\', $resposta->getContent());
    }

    public function test_404_com_mensagem_do_app_e_mantida(): void
    {
        Route::get('/api/_teste/mycar-sumiu', fn () => abort(404, 'MyCar não encontrado.'));

        $this->getJson('/api/_teste/mycar-sumiu')
            ->assertStatus(404)
            ->assertJsonPath('message', 'MyCar não encontrado.');
    }

    public function test_405_em_portugues_e_mantem_o_cabecalho_allow(): void
    {
        $resposta = $this->getJson('/api/login')
            ->assertStatus(405)
            ->assertJsonPath('message', 'Esta ação não é permitida neste endereço.');

        $this->assertStringContainsString('POST', (string) $resposta->headers->get('Allow'));
    }

    public function test_429_do_throttle_em_portugues_e_mantem_o_retry_after(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/login', ['email' => 'ninguem@example.com', 'password' => 'errada'])->assertStatus(422);
        }

        $resposta = $this->postJson('/api/login', ['email' => 'ninguem@example.com', 'password' => 'errada'])
            ->assertStatus(429)
            ->assertJsonPath('message', 'Muitas tentativas. Aguarde um pouco e tente de novo.');

        $this->assertNotNull($resposta->headers->get('Retry-After'));
    }

    public function test_500_em_producao_nao_vaza_detalhe_interno(): void
    {
        config(['app.debug' => false]);
        Route::get('/api/_teste/explode', fn () => throw new RuntimeException('SQLSTATE[HY000]: tabela_secreta senha=123'));

        $resposta = $this->getJson('/api/_teste/explode')
            ->assertStatus(500)
            ->assertExactJson(['message' => 'Erro interno no servidor. Tente de novo em instantes.']);

        foreach (['SQLSTATE', 'tabela_secreta', 'trace', 'RuntimeException', '.php'] as $detalhe) {
            $this->assertStringNotContainsString($detalhe, $resposta->getContent());
        }
    }
}
