<?php

namespace Tests\Feature;

use App\Models\Conta;
use App\Models\Motorista;
use App\Models\UsoVeiculo;
use App\Models\UsoVeiculoEvento;
use App\Models\UsoVeiculoFoto;
use App\Models\VeiculoConta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

/**
 * Saída e chegada repetidas (clique duplo, "Tentar de novo" depois de erro de conexão):
 * o mesmo usuário com o mesmo km recebe o resultado já gravado, venha o km como número (JSON)
 * ou como texto (multipart, quando há foto). Outro usuário, outro km ou status errado seguem recusados.
 */
class UsoPedidoRepetidoTest extends TestCase
{
    use AutenticaUsuario;
    use RefreshDatabase;

    /** PNG 1x1 válido. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private const SO_AGENDADO = 'A saída só pode ser registrada em um uso agendado.';

    private const SO_EM_USO = 'A chegada só pode ser registrada em um uso em andamento.';

    /** @var array<string, string> */
    private array $headers;

    private int $contaId;

    private VeiculoConta $strada;

    private Motorista $motorista;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        // Hora congelada: a URL assinada da foto depende do relógio, e o payload repetido precisa ser idêntico.
        $this->freezeTime();

        [, $this->headers] = $this->autenticarConta('frota');
        $this->contaId = app('conta.atual');

        $this->strada = VeiculoConta::create(['placa' => 'AAA1A11', 'marca' => 'Fiat', 'modelo' => 'Strada', 'ano' => 2022]);
        $this->motorista = Motorista::create(['nome' => 'João', 'cnh_numero' => '123', 'cnh_validade' => now()->addYears(2)->toDateString()]);
    }

    private function agendado(): UsoVeiculo
    {
        return UsoVeiculo::create([
            'veiculo_conta_id' => $this->strada->id,
            'motorista_id' => $this->motorista->id,
            'inicio_previsto' => now()->subHour(),
            'fim_previsto' => now()->addHours(3),
            'status' => 'agendado',
        ]);
    }

    private function foto(): UploadedFile
    {
        $caminho = tempnam(sys_get_temp_dir(), 'hodo');
        file_put_contents($caminho, base64_decode(self::PNG));

        return new UploadedFile($caminho, 'painel.png', null, null, true);
    }

    /** Como o navegador manda com FormData: tudo texto, em multipart. */
    private function multipart(string $url, array $campos, ?array $headers = null): TestResponse
    {
        return $this->post($url, $campos, ($headers ?? $this->headers) + ['Accept' => 'application/json']);
    }

    private function sair(UsoVeiculo $uso, int $km = 45200): void
    {
        $this->postJson("/api/conta/usos/{$uso->id}/saida", ['km_saida' => $km], $this->headers)->assertOk();
    }

    private function eventos(UsoVeiculo $uso, string $acao): int
    {
        return UsoVeiculoEvento::where('uso_veiculo_id', $uso->id)->where('acao', $acao)->count();
    }

    // ------------------------------------------------------------------ saída

    public function test_saida_repetida_em_json_devolve_o_mesmo_resultado(): void
    {
        $uso = $this->agendado();

        $primeira = $this->postJson("/api/conta/usos/{$uso->id}/saida", ['km_saida' => 45200], $this->headers)->assertOk();
        $repetida = $this->postJson("/api/conta/usos/{$uso->id}/saida", ['km_saida' => 45200], $this->headers)->assertOk();

        $this->assertSame($primeira->json(), $repetida->json());
        $this->assertSame(1, $this->eventos($uso, 'saiu'));
    }

    public function test_saida_repetida_em_multipart_com_km_em_texto_sem_foto(): void
    {
        $uso = $this->agendado();
        $url = "/api/conta/usos/{$uso->id}/saida";

        $primeira = $this->multipart($url, ['km_saida' => '45200'])->assertOk();
        $repetida = $this->multipart($url, ['km_saida' => '45200'])->assertOk();

        $this->assertSame($primeira->json(), $repetida->json());
        $this->assertSame(45200, $repetida->json('km_saida'));
        $this->assertSame(1, $this->eventos($uso, 'saiu'));
    }

    public function test_saida_repetida_em_multipart_com_foto_nao_grava_de_novo(): void
    {
        $uso = $this->agendado();
        $url = "/api/conta/usos/{$uso->id}/saida";

        $primeira = $this->multipart($url, ['km_saida' => '45200', 'foto' => $this->foto()])->assertOk();
        $repetida = $this->multipart($url, ['km_saida' => '45200', 'foto' => $this->foto()])->assertOk();

        $this->assertSame($primeira->json(), $repetida->json());
        $this->assertSame('foto', $repetida->json('km_saida_origem'));
        $this->assertNotNull($repetida->json('url_foto_saida'));
        $this->assertSame(1, UsoVeiculoFoto::where('uso_veiculo_id', $uso->id)->where('momento', 'saida')->count());
        $this->assertSame(1, $this->eventos($uso, 'saiu'));
    }

    public function test_saida_gravada_em_json_e_repetida_em_multipart(): void
    {
        $uso = $this->agendado();

        $primeira = $this->postJson("/api/conta/usos/{$uso->id}/saida", ['km_saida' => 45200], $this->headers)->assertOk();
        $repetida = $this->multipart("/api/conta/usos/{$uso->id}/saida", ['km_saida' => '45200'])->assertOk();

        $this->assertSame($primeira->json(), $repetida->json());
    }

    public function test_saida_repetida_com_outro_km_continua_recusada(): void
    {
        $uso = $this->agendado();
        $this->sair($uso);

        $this->postJson("/api/conta/usos/{$uso->id}/saida", ['km_saida' => 45201], $this->headers)
            ->assertStatus(422)->assertJsonPath('message', self::SO_AGENDADO);
        $this->multipart("/api/conta/usos/{$uso->id}/saida", ['km_saida' => '45300'])
            ->assertStatus(422)->assertJsonPath('message', self::SO_AGENDADO);

        $this->assertSame(45200, $uso->fresh()->km_saida);
        $this->assertSame(1, $this->eventos($uso, 'saiu'));
    }

    public function test_saida_repetida_por_outro_usuario_da_conta_continua_recusada(): void
    {
        $uso = $this->agendado();
        $this->sair($uso);

        [, $outro] = $this->autenticarConta('frota', Conta::find($this->contaId));

        $this->postJson("/api/conta/usos/{$uso->id}/saida", ['km_saida' => 45200], $outro)
            ->assertStatus(422)->assertJsonPath('message', self::SO_AGENDADO);
        $this->multipart("/api/conta/usos/{$uso->id}/saida", ['km_saida' => '45200'], $outro)
            ->assertStatus(422)->assertJsonPath('message', self::SO_AGENDADO);
    }

    public function test_saida_com_o_mesmo_km_depois_da_chegada_continua_recusada(): void
    {
        $uso = $this->agendado();
        $this->sair($uso);
        $this->postJson("/api/conta/usos/{$uso->id}/chegada", ['km_chegada' => 45300], $this->headers)->assertOk();

        $this->multipart("/api/conta/usos/{$uso->id}/saida", ['km_saida' => '45200'])
            ->assertStatus(422)->assertJsonPath('message', self::SO_AGENDADO);
    }

    public function test_saida_em_uso_cancelado_continua_recusada(): void
    {
        $uso = $this->agendado();
        $uso->update(['status' => 'cancelado']);

        $this->multipart("/api/conta/usos/{$uso->id}/saida", ['km_saida' => '45200'])
            ->assertStatus(422)->assertJsonPath('message', self::SO_AGENDADO);
        $this->assertSame(0, $this->eventos($uso, 'saiu'));
    }

    // ---------------------------------------------------------------- chegada

    public function test_chegada_repetida_em_json_devolve_o_mesmo_resultado(): void
    {
        $uso = $this->agendado();
        $this->sair($uso);

        $primeira = $this->postJson("/api/conta/usos/{$uso->id}/chegada", ['km_chegada' => 45300], $this->headers)->assertOk();
        $repetida = $this->postJson("/api/conta/usos/{$uso->id}/chegada", ['km_chegada' => 45300], $this->headers)->assertOk();

        $this->assertSame($primeira->json(), $repetida->json());
        $this->assertSame([], $repetida->json('alerta_conflito'));
        $this->assertSame(1, $this->eventos($uso, 'chegou'));
    }

    public function test_chegada_repetida_em_multipart_com_km_em_texto_sem_foto(): void
    {
        $uso = $this->agendado();
        $this->sair($uso);
        $url = "/api/conta/usos/{$uso->id}/chegada";

        $primeira = $this->multipart($url, ['km_chegada' => '45300'])->assertOk();
        $repetida = $this->multipart($url, ['km_chegada' => '45300'])->assertOk();

        $this->assertSame($primeira->json(), $repetida->json());
        $this->assertSame(100, $repetida->json('km_rodados'));
        $this->assertSame(1, $this->eventos($uso, 'chegou'));
    }

    public function test_chegada_repetida_em_multipart_com_foto_nao_grava_de_novo(): void
    {
        $uso = $this->agendado();
        $this->sair($uso);
        $url = "/api/conta/usos/{$uso->id}/chegada";

        $primeira = $this->multipart($url, ['km_chegada' => '45300', 'foto' => $this->foto()])->assertOk();
        $repetida = $this->multipart($url, ['km_chegada' => '45300', 'foto' => $this->foto()])->assertOk();

        $this->assertSame($primeira->json(), $repetida->json());
        $this->assertSame('foto', $repetida->json('km_chegada_origem'));
        $this->assertSame(1, UsoVeiculoFoto::where('uso_veiculo_id', $uso->id)->where('momento', 'chegada')->count());
        $this->assertSame(1, $this->eventos($uso, 'chegou'));
    }

    public function test_chegada_repetida_devolve_o_alerta_de_conflito_sem_avisar_de_novo(): void
    {
        $uso = $this->agendado();
        $this->sair($uso);

        // Reserva da mesma Strada que começa antes da volta.
        $this->travelTo(now()->addHours(2));
        $afetada = UsoVeiculo::create([
            'veiculo_conta_id' => $this->strada->id,
            'inicio_previsto' => now()->subMinutes(30),
            'fim_previsto' => now()->addHours(2),
            'status' => 'agendado',
        ]);
        $url = "/api/conta/usos/{$uso->id}/chegada";

        $primeira = $this->multipart($url, ['km_chegada' => '45300'])->assertOk()
            ->assertJsonPath('alerta_conflito.0.uso_id', $afetada->id);
        $repetida = $this->multipart($url, ['km_chegada' => '45300'])->assertOk();

        $this->assertSame($primeira->json(), $repetida->json());
        $this->assertSame('veiculo', $repetida->json('alerta_conflito.0.motivo'));
        $this->assertSame(1, $this->eventos($afetada, 'conflito_por_devolucao'));
    }

    public function test_chegada_repetida_com_outro_km_continua_recusada(): void
    {
        $uso = $this->agendado();
        $this->sair($uso);
        $this->postJson("/api/conta/usos/{$uso->id}/chegada", ['km_chegada' => 45300], $this->headers)->assertOk();

        $this->postJson("/api/conta/usos/{$uso->id}/chegada", ['km_chegada' => 45301], $this->headers)
            ->assertStatus(422)->assertJsonPath('message', self::SO_EM_USO);
        $this->multipart("/api/conta/usos/{$uso->id}/chegada", ['km_chegada' => '45400'])
            ->assertStatus(422)->assertJsonPath('message', self::SO_EM_USO);

        $this->assertSame(45300, $uso->fresh()->km_chegada);
        $this->assertSame(1, $this->eventos($uso, 'chegou'));
    }

    public function test_chegada_repetida_por_outro_usuario_da_conta_continua_recusada(): void
    {
        $uso = $this->agendado();
        $this->sair($uso);
        $this->postJson("/api/conta/usos/{$uso->id}/chegada", ['km_chegada' => 45300], $this->headers)->assertOk();

        [, $outro] = $this->autenticarConta('frota', Conta::find($this->contaId));

        $this->multipart("/api/conta/usos/{$uso->id}/chegada", ['km_chegada' => '45300'], $outro)
            ->assertStatus(422)->assertJsonPath('message', self::SO_EM_USO);
    }

    public function test_chegada_em_uso_agendado_continua_recusada(): void
    {
        $uso = $this->agendado();

        $this->multipart("/api/conta/usos/{$uso->id}/chegada", ['km_chegada' => '45300'])
            ->assertStatus(422)->assertJsonPath('message', self::SO_EM_USO);
        $this->assertSame(0, $this->eventos($uso, 'chegou'));
    }

    // --------------------------------------------------------------- isolamento

    public function test_usuario_de_outra_conta_nao_repete_nem_enxerga_o_uso(): void
    {
        $uso = $this->agendado();
        $this->sair($uso);

        [, $deFora] = $this->autenticarConta('frota');

        $this->multipart("/api/conta/usos/{$uso->id}/saida", ['km_saida' => '45200'], $deFora)->assertStatus(404);
        $this->postJson("/api/conta/usos/{$uso->id}/saida", ['km_saida' => 45200], $deFora)->assertStatus(404);
        $this->multipart("/api/conta/usos/{$uso->id}/chegada", ['km_chegada' => '45300'], $deFora)->assertStatus(404);

        app()->instance('conta.atual', $this->contaId);
        $this->postJson("/api/conta/usos/{$uso->id}/chegada", ['km_chegada' => 45300], $this->headers)->assertOk();

        $this->multipart("/api/conta/usos/{$uso->id}/chegada", ['km_chegada' => '45300'], $deFora)
            ->assertStatus(404)->assertJsonMissingPath('alerta_conflito');

        // O middleware vinculou a outra conta no último pedido: volta para a dona do uso antes de conferir o banco.
        app()->instance('conta.atual', $this->contaId);
        $gravado = $uso->fresh();
        $this->assertSame('concluido', $gravado->status);
        $this->assertSame(1, $this->eventos($uso, 'saiu'));
        $this->assertSame(1, $this->eventos($uso, 'chegou'));
    }
}
