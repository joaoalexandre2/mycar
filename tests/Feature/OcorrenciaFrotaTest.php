<?php

namespace Tests\Feature;

use App\Models\CategoriaDespesa;
use App\Models\Conta;
use App\Models\Despesa;
use App\Models\Motorista;
use App\Models\Ocorrencia;
use App\Models\OcorrenciaFoto;
use App\Models\VeiculoConta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class OcorrenciaFrotaTest extends TestCase
{
    use AutenticaUsuario;
    use RefreshDatabase;

    /** @var array<string, string> */
    private array $headers;

    private Conta $conta;

    private VeiculoConta $strada;

    private Motorista $joao;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-10 12:00:00');
        Storage::fake('local');

        [, $this->headers] = $this->autenticarConta('frota');
        $this->conta = Conta::find(app('conta.atual'));

        $this->strada = VeiculoConta::create(['placa' => 'AAA1A11', 'marca' => 'Fiat', 'modelo' => 'Strada', 'ano' => 2022]);
        $this->joao = Motorista::create(['nome' => 'João', 'cnh_numero' => '123', 'cnh_validade' => now()->addYears(2)->toDateString()]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** Usuário de frota com papel de motorista, ligado ao motorista João. */
    private function motoristaJoao(): array
    {
        [$usuario, $headers] = $this->autenticarConta('frota', $this->conta);
        $usuario->forceFill(['papel_frota' => 'motorista'])->save();
        $this->joao->forceFill(['user_id' => $usuario->id])->save();
        app()->instance('conta.atual', $this->conta->id);

        return $headers;
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'veiculo_conta_id' => $this->strada->id,
            'tipo' => 'avaria',
            'data_hora' => '2026-11-09T08:00',
            'local' => 'Pátio',
            'descricao' => 'Retrovisor quebrado',
        ], $extra);
    }

    public function test_grava_o_horario_digitado_em_brasilia_e_mostra_com_o_fuso(): void
    {
        $r = $this->postJson('/api/conta/ocorrencias', $this->payload(['data_hora' => '2026-11-09T08:00']), $this->headers)
            ->assertStatus(201)
            ->assertJsonPath('status', 'aberta')
            ->assertJsonPath('data_hora', '2026-11-09T08:00:00-03:00');

        $this->assertSame('2026-11-09 11:00:00', DB::table('ocorrencias')->where('id', $r->json('id'))->value('data_hora'));
    }

    public function test_valor_gasto_gera_despesa_que_acompanha_as_mudancas_e_some_quando_zera(): void
    {
        $id = $this->postJson('/api/conta/ocorrencias', $this->payload(), $this->headers)->json('id');
        $this->assertDatabaseCount('despesas', 0);

        $this->putJson("/api/conta/ocorrencias/{$id}", $this->payload(['valor_gasto' => 350, 'status' => 'concluida']), $this->headers)
            ->assertOk()
            ->assertJsonPath('valor_gasto', 350);

        $despesa = Despesa::firstOrFail();
        $this->assertSame(350.0, (float) $despesa->valor);
        $this->assertSame(CategoriaDespesa::where('nome', 'Reparo e peças')->value('id'), $despesa->categoria_id);
        $this->assertSame('2026-11-09', $despesa->data->toDateString());

        $this->putJson("/api/conta/ocorrencias/{$id}", $this->payload(['valor_gasto' => 400, 'status' => 'concluida']), $this->headers)->assertOk();
        $this->assertDatabaseCount('despesas', 1);
        $this->assertSame(400.0, (float) Despesa::firstOrFail()->valor);

        $this->putJson("/api/conta/ocorrencias/{$id}", $this->payload(['valor_gasto' => null, 'status' => 'concluida']), $this->headers)->assertOk();
        $this->assertDatabaseCount('despesas', 0);
        $this->assertNull(Ocorrencia::find($id)->despesa_id);
    }

    public function test_motorista_registra_em_nome_proprio_e_nao_ve_valores_nem_as_ocorrencias_de_outros(): void
    {
        $headersMotorista = $this->motoristaJoao();

        $meu = $this->postJson('/api/conta/ocorrencias', $this->payload(['valor_gasto' => 999, 'motorista_id' => 999999]), $headersMotorista)
            ->assertStatus(201)
            ->assertJsonPath('motorista.nome', 'João')
            ->assertJsonMissingPath('valor_gasto');

        $outroMotorista = Motorista::create(['nome' => 'Carla', 'cnh_numero' => '456', 'cnh_validade' => now()->addYear()->toDateString()]);
        Ocorrencia::create(['conta_id' => $this->conta->id, 'veiculo_conta_id' => $this->strada->id, 'motorista_id' => $outroMotorista->id, 'tipo' => 'dano', 'data_hora' => now(), 'descricao' => 'Outra pessoa', 'status' => 'aberta']);

        $lista = $this->getJson('/api/conta/ocorrencias', $headersMotorista)->assertOk();

        $this->assertSame([$meu->json('id')], collect($lista->json('data'))->pluck('id')->all());
        $this->assertArrayNotHasKey('prejuizo_total', $lista->json('resumo'));
        $this->assertSame(0.0, (float) Ocorrencia::where('motorista_id', $this->joao->id)->value('valor_gasto'));
    }

    public function test_motorista_nao_altera_nem_apaga(): void
    {
        $headersMotorista = $this->motoristaJoao();
        $id = $this->postJson('/api/conta/ocorrencias', $this->payload(), $headersMotorista)->json('id');

        $this->putJson("/api/conta/ocorrencias/{$id}", $this->payload(['status' => 'concluida']), $headersMotorista)->assertStatus(403);
        $this->deleteJson("/api/conta/ocorrencias/{$id}", [], $headersMotorista)->assertStatus(403);
    }

    public function test_anexa_foto_antes_do_reparo_e_so_o_gestor_apaga(): void
    {
        $id = $this->postJson('/api/conta/ocorrencias', $this->payload(), $this->headers)->json('id');

        // Uma imagem PNG de 1x1 pixel, sem depender da extensão GD no ambiente de testes.
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');

        $foto = $this->postJson("/api/conta/ocorrencias/{$id}/fotos", [
            'momento' => 'antes',
            'arquivo' => UploadedFile::fake()->createWithContent('avaria.png', $png),
        ], $this->headers)->assertStatus(201)->assertJsonPath('momento', 'antes');

        $caminho = OcorrenciaFoto::findOrFail($foto->json('id'))->caminho;
        Storage::disk('local')->assertExists($caminho);

        $this->get($foto->json('url'))->assertOk();

        $this->deleteJson('/api/conta/ocorrencias/fotos/' . $foto->json('id'), [], $this->headers)->assertNoContent();
        Storage::disk('local')->assertMissing($caminho);
    }

    public function test_resumo_soma_prejuizo_e_gasto_para_quem_gerencia(): void
    {
        $this->postJson('/api/conta/ocorrencias', $this->payload(['prejuizo_estimado' => 1000, 'valor_gasto' => 300]), $this->headers);
        $this->postJson('/api/conta/ocorrencias', $this->payload(['prejuizo_estimado' => 500, 'valor_gasto' => 200]), $this->headers);

        $this->getJson('/api/conta/ocorrencias', $this->headers)
            ->assertOk()
            ->assertJsonPath('resumo.total', 2)
            ->assertJsonPath('resumo.abertas', 2)
            ->assertJsonPath('resumo.prejuizo_total', 1500)
            ->assertJsonPath('resumo.gasto_total', 500);
    }

    public function test_alerta_de_ocorrencia_aberta_ha_mais_de_uma_semana(): void
    {
        Ocorrencia::create(['conta_id' => $this->conta->id, 'veiculo_conta_id' => $this->strada->id, 'tipo' => 'acidente', 'data_hora' => now()->subDays(10), 'descricao' => 'Antiga', 'status' => 'aberta']);
        Ocorrencia::create(['conta_id' => $this->conta->id, 'veiculo_conta_id' => $this->strada->id, 'tipo' => 'avaria', 'data_hora' => now()->subDays(10), 'descricao' => 'Já resolvida', 'status' => 'concluida']);
        Ocorrencia::create(['conta_id' => $this->conta->id, 'veiculo_conta_id' => $this->strada->id, 'tipo' => 'avaria', 'data_hora' => now()->subDay(), 'descricao' => 'Recente', 'status' => 'aberta']);

        $this->getJson('/api/conta/alertas', $this->headers)
            ->assertOk()
            ->assertJsonPath('ocorrencias_pendentes.quantidade', 1)
            ->assertJsonPath('ocorrencias_pendentes.itens.0.tipo', 'Acidente');
    }

    public function test_ocorrencia_de_outra_conta_nao_aparece_nem_pode_ser_alterada(): void
    {
        $contaB = Conta::create(['tipo' => 'frota', 'nome' => 'Empresa B']);
        app()->instance('conta.atual', $contaB->id);
        $veiculoB = VeiculoConta::create(['placa' => 'BBB2B22', 'marca' => 'VW', 'modelo' => 'Gol', 'ano' => 2020]);
        $ocorrenciaB = Ocorrencia::create(['conta_id' => $contaB->id, 'veiculo_conta_id' => $veiculoB->id, 'tipo' => 'dano', 'data_hora' => now(), 'descricao' => 'Da outra empresa', 'status' => 'aberta']);
        app()->instance('conta.atual', $this->conta->id);

        $this->getJson('/api/conta/ocorrencias', $this->headers)->assertJsonPath('resumo.total', 0);
        $this->putJson("/api/conta/ocorrencias/{$ocorrenciaB->id}", $this->payload(['status' => 'concluida']), $this->headers)->assertStatus(404);
        $this->deleteJson("/api/conta/ocorrencias/{$ocorrenciaB->id}", [], $this->headers)->assertStatus(404);
    }

    public function test_recusa_tipo_e_status_desconhecidos(): void
    {
        $this->postJson('/api/conta/ocorrencias', $this->payload(['tipo' => 'inventado']), $this->headers)->assertStatus(422);

        $id = $this->postJson('/api/conta/ocorrencias', $this->payload(), $this->headers)->json('id');
        $this->putJson("/api/conta/ocorrencias/{$id}", $this->payload(['status' => 'virou-lenda']), $this->headers)->assertStatus(422);
    }
}
