<?php

namespace Tests\Feature;

use App\Services\Hodometro\LeitorDeHodometro;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class LeituraHodometroTest extends TestCase
{
    use AutenticaUsuario;
    use RefreshDatabase;

    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        [, $this->headers] = $this->autenticarConta('frota');
    }

    /** Troca a Claude por um leitor falso: os testes não chamam a API de verdade. */
    private function leitorFalso(bool $disponivel, ?int $km = null, bool $falha = false): void
    {
        $this->app->instance(LeitorDeHodometro::class, new class($disponivel, $km, $falha) implements LeitorDeHodometro
        {
            public function __construct(private bool $disponivel, private ?int $km, private bool $falha) {}

            public function disponivel(): bool
            {
                return $this->disponivel;
            }

            public function ler(UploadedFile $foto): ?int
            {
                if ($this->falha) {
                    throw new RuntimeException('API fora do ar');
                }

                return $this->km;
            }
        });
    }

    /** PNG de 1x1 real: os testes não dependem da extensão GD. */
    private function painel(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('painel.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='));
    }

    private function enviar(?UploadedFile $foto = null)
    {
        return $this->post('/api/conta/hodometro/leitura', [
            'foto' => $foto ?? $this->painel(),
        ], $this->headers + ['Accept' => 'application/json']);
    }

    public function test_devolve_o_km_lido_da_foto(): void
    {
        $this->leitorFalso(disponivel: true, km: 45200);

        $this->enviar()->assertOk()->assertExactJson(['km' => 45200]);
    }

    public function test_devolve_null_quando_a_foto_nao_deixa_ler(): void
    {
        $this->leitorFalso(disponivel: true, km: null);

        $this->enviar()->assertOk()->assertExactJson(['km' => null]);
    }

    public function test_sem_chave_configurada_pede_o_km_digitado(): void
    {
        $this->leitorFalso(disponivel: false);

        $this->enviar()->assertStatus(503);
    }

    public function test_falha_da_ia_vira_mensagem_e_nao_erro_500(): void
    {
        $this->leitorFalso(disponivel: true, falha: true);

        $this->enviar()->assertStatus(502)->assertJsonPath('message', 'Não foi possível ler a foto agora. Digite o km.');
    }

    public function test_exige_uma_imagem(): void
    {
        $this->leitorFalso(disponivel: true, km: 1);

        $this->enviar(UploadedFile::fake()->create('nota.pdf', 10, 'application/pdf'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('foto');
    }

    public function test_so_o_perfil_frota_usa_a_leitura(): void
    {
        $this->leitorFalso(disponivel: true, km: 1);
        [, $headersPessoa] = $this->autenticarConta('pessoa');

        $this->post('/api/conta/hodometro/leitura', [
            'foto' => $this->painel(),
        ], $headersPessoa + ['Accept' => 'application/json'])->assertStatus(403);
    }

    public function test_sem_login_a_api_recusa(): void
    {
        $this->post('/api/conta/hodometro/leitura', [], ['Accept' => 'application/json'])->assertStatus(401);
    }
}
