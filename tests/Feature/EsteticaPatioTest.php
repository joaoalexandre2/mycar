<?php

namespace Tests\Feature;

use App\Models\AtendimentoEstetica;
use App\Models\FotoEstetica;
use App\Models\ServicoEstetica;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class EsteticaPatioTest extends TestCase
{
    use AutenticaUsuario;
    use RefreshDatabase;

    /** Uma imagem PNG de 1x1 pixel, para o upload passar na validação de imagem. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    /** @var array<string, string> */
    private array $headers;

    private ServicoEstetica $lavagem;

    private ServicoEstetica $vitrificacao;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        [, $this->headers] = $this->autenticarConta('estetica');

        $this->lavagem = ServicoEstetica::create(['nome' => 'Lavagem completa', 'preco' => 80, 'dias_previstos' => 0]);
        $this->vitrificacao = ServicoEstetica::create(['nome' => 'Vitrificação', 'preco' => 900, 'dias_previstos' => 3]);
    }

    private function atendimento(array $extra = []): array
    {
        return $this->postJson('/api/estetica/atendimentos', array_merge([
            'placa' => 'abc-1d23',
            'servicos' => [$this->lavagem->id],
        ], $extra), $this->headers)->assertStatus(201)->json();
    }

    public function test_entrada_normaliza_a_placa_e_soma_os_servicos(): void
    {
        $a = $this->atendimento(['servicos' => [$this->lavagem->id, $this->vitrificacao->id]]);

        $this->assertSame('ABC1D23', $a['placa']);
        $this->assertSame('entrada', $a['etapa']);
        $this->assertEquals(980, $a['total_servicos']);
        $this->assertCount(2, $a['servicos']);
    }

    public function test_servico_longo_define_a_previsao_de_entrega(): void
    {
        $a = $this->atendimento(['servicos' => [$this->vitrificacao->id]]);

        $this->assertNotNull($a['previsao_entrega']);
        $this->assertTrue($a['previsao_entrega'] > now()->addDays(2)->toIso8601String());
    }

    public function test_avanca_pelas_etapas_e_so_entrega_com_foto_de_checkout(): void
    {
        $id = $this->atendimento()['id'];

        $this->postJson("/api/estetica/atendimentos/{$id}/avancar", [], $this->headers)->assertOk()->assertJsonPath('etapa', 'atendimento');
        $this->postJson("/api/estetica/atendimentos/{$id}/avancar", [], $this->headers)->assertOk()->assertJsonPath('etapa', 'entrega');

        $this->postJson("/api/estetica/atendimentos/{$id}/avancar", [], $this->headers)
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Antes de entregar, tire a foto de checkout do carro.']);

        $this->postJson("/api/estetica/atendimentos/{$id}/fotos", [
            'momento' => 'checkout',
            'foto' => UploadedFile::fake()->createWithContent('saida.png', base64_decode(self::PNG)),
        ], $this->headers)->assertStatus(201);

        $this->postJson("/api/estetica/atendimentos/{$id}/avancar", [], $this->headers)
            ->assertOk()
            ->assertJsonPath('etapa', 'entregue');
    }

    public function test_foto_de_checkin_fica_guardada_e_abre_por_link_assinado(): void
    {
        $id = $this->atendimento()['id'];

        $foto = $this->postJson("/api/estetica/atendimentos/{$id}/fotos", [
            'momento' => 'checkin',
            'foto' => UploadedFile::fake()->createWithContent('entrada.png', base64_decode(self::PNG)),
        ], $this->headers)->assertStatus(201)->json();

        Storage::disk('local')->assertExists(FotoEstetica::first()->caminho);

        $this->get($foto['url'])->assertOk();
        $this->get(str_replace('/estetica/fotos/', '/estetica/fotos/9999', $foto['url']))->assertStatus(403);
    }

    public function test_pacote_entra_como_um_servico_com_o_preco_do_pacote(): void
    {
        $pacote = $this->postJson('/api/estetica/pacotes', [
            'nome' => 'Pacote completo',
            'preco' => 150,
            'servicos' => [$this->lavagem->id, $this->vitrificacao->id],
        ], $this->headers)->assertStatus(201)->json();

        $a = $this->atendimento(['servicos' => [], 'pacote_id' => $pacote['id']]);

        $this->assertEquals(150, $a['total_servicos']);
        $this->assertSame('Pacote: Pacote completo', $a['servicos'][0]['nome']);
    }

    public function test_historico_por_placa_mostra_o_total_e_o_retorno_previsto(): void
    {
        $id = $this->atendimento()['id'];
        AtendimentoEstetica::find($id)->forceFill(['etapa' => 'entregue', 'entregue_em' => now()->subDays(10)])->save();

        $this->getJson('/api/estetica/placas/ABC1D23', $this->headers)
            ->assertOk()
            ->assertJsonPath('total_gasto', 80)
            ->assertJsonPath('retorno_previsto', now()->subDays(10)->addDays(90)->toDateString());
    }

    public function test_financeiro_lista_o_que_esta_em_aberto_por_forma(): void
    {
        $a = $this->atendimento();
        $this->putJson("/api/estetica/atendimentos/{$a['id']}", ['forma_pagamento' => 'pix'], $this->headers)->assertOk();

        $this->getJson('/api/estetica/financeiro', $this->headers)
            ->assertOk()
            ->assertJsonPath('total_em_aberto', 80)
            ->assertJsonPath('por_forma.pix.quantidade', 1);

        $this->putJson("/api/estetica/atendimentos/{$a['id']}", ['pago' => true], $this->headers)->assertOk();

        $this->getJson('/api/estetica/financeiro', $this->headers)->assertJsonPath('total_em_aberto', 0);
    }

    public function test_outro_perfil_nao_acessa_o_patio(): void
    {
        [, $headersFrota] = $this->autenticarConta('frota');

        $this->getJson('/api/estetica/atendimentos', $headersFrota)->assertStatus(403);
    }
}
