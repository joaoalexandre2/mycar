<?php

namespace Tests\Feature;

use App\Models\CentroCusto;
use App\Models\Conta;
use App\Models\Motorista;
use App\Models\UsoVeiculo;
use App\Models\VeiculoConta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class FrotaAgendaTest extends TestCase
{
    use AutenticaUsuario;
    use RefreshDatabase;

    /** PNG 1x1 válido. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    /** @var array<string, string> */
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        [, $this->headers] = $this->autenticarConta('frota');
    }

    private function veiculo(string $placa = 'AAA1B25', array $extra = []): VeiculoConta
    {
        return VeiculoConta::create(array_merge(['placa' => $placa, 'marca' => 'Fiat', 'modelo' => 'Strada', 'ano' => 2022], $extra));
    }

    private function motorista(string $nome = 'Maria Silva'): Motorista
    {
        return Motorista::create(['nome' => $nome]);
    }

    private function reservar(VeiculoConta $v, array $extra = [], int $esperado = 201)
    {
        return $this->postJson('/api/conta/usos', array_merge([
            'veiculo_conta_id' => $v->id,
            'inicio_previsto' => '2026-11-10 08:00',
            'fim_previsto' => '2026-11-10 12:00',
        ], $extra), $this->headers)->assertStatus($esperado);
    }

    private function foto(): UploadedFile
    {
        $caminho = tempnam(sys_get_temp_dir(), 'hodo');
        file_put_contents($caminho, base64_decode(self::PNG));

        return new UploadedFile($caminho, 'painel.png', null, null, true);
    }

    // ----------------------------------------------------------------- acesso

    public function test_so_o_perfil_frota_acessa_agenda_motoristas_e_centros(): void
    {
        [, $pessoa] = $this->autenticarConta('pessoa');

        foreach (['/api/conta/usos', '/api/conta/motoristas', '/api/conta/centros-custo'] as $rota) {
            $this->getJson($rota, $pessoa)->assertStatus(403);
        }

        $this->getJson('/api/conta/usos')->assertStatus(401);
    }

    // ---------------------------------------------------------- centros de custo

    public function test_cadastra_edita_e_lista_centros_de_custo(): void
    {
        $id = $this->postJson('/api/conta/centros-custo', ['nome' => 'Entregas', 'codigo' => 'CC-01'], $this->headers)
            ->assertStatus(201)->assertJsonPath('nome', 'Entregas')->json('id');

        $this->putJson("/api/conta/centros-custo/{$id}", ['nome' => 'Entregas SP', 'codigo' => 'CC-01'], $this->headers)
            ->assertOk()->assertJsonPath('nome', 'Entregas SP');

        $this->getJson('/api/conta/centros-custo', $this->headers)->assertOk()->assertJsonCount(1);
    }

    public function test_nome_de_centro_e_unico_na_conta_mas_outra_conta_pode_repetir(): void
    {
        $this->postJson('/api/conta/centros-custo', ['nome' => 'Vendas'], $this->headers)->assertStatus(201);
        $this->postJson('/api/conta/centros-custo', ['nome' => 'Vendas'], $this->headers)
            ->assertStatus(422)->assertJsonValidationErrors('nome');

        [, $outra] = $this->autenticarConta('frota');
        $this->postJson('/api/conta/centros-custo', ['nome' => 'Vendas'], $outra)->assertStatus(201);
    }

    public function test_centro_com_historico_nao_e_apagado_so_desativado(): void
    {
        $centro = CentroCusto::create(['nome' => 'Obras']);
        $veiculo = $this->veiculo();
        $this->reservar($veiculo, ['centro_custo_id' => $centro->id]);

        $this->deleteJson("/api/conta/centros-custo/{$centro->id}", [], $this->headers)->assertStatus(422);

        $this->putJson("/api/conta/centros-custo/{$centro->id}", ['nome' => 'Obras', 'ativo' => false], $this->headers)->assertOk();
        $this->getJson('/api/conta/centros-custo', $this->headers)->assertJsonCount(0);
        $this->getJson('/api/conta/centros-custo?todos=1', $this->headers)->assertJsonCount(1);

        $semUso = CentroCusto::create(['nome' => 'Sem uso']);
        $this->deleteJson("/api/conta/centros-custo/{$semUso->id}", [], $this->headers)->assertOk();
    }

    public function test_uma_conta_nao_ve_nem_edita_centros_de_outra(): void
    {
        $centro = CentroCusto::create(['nome' => 'Interno']);
        [, $outra] = $this->autenticarConta('frota');

        $this->getJson('/api/conta/centros-custo', $outra)->assertJsonCount(0);
        $this->putJson("/api/conta/centros-custo/{$centro->id}", ['nome' => 'X'], $outra)->assertStatus(404);
        $this->deleteJson("/api/conta/centros-custo/{$centro->id}", [], $outra)->assertStatus(404);
    }

    // --------------------------------------------------------------- motoristas

    public function test_cadastra_motorista_e_nao_aceita_nome_repetido(): void
    {
        $this->postJson('/api/conta/motoristas', ['nome' => 'João', 'cnh_numero' => '123', 'cnh_validade' => '2028-05-01'], $this->headers)
            ->assertStatus(201)->assertJsonPath('cnh_validade', '2028-05-01');

        $this->postJson('/api/conta/motoristas', ['nome' => 'João'], $this->headers)->assertStatus(422);
    }

    public function test_motorista_com_historico_e_desativado_em_vez_de_apagado(): void
    {
        $maria = $this->motorista();
        $this->reservar($this->veiculo(), ['motorista_id' => $maria->id]);

        $this->deleteJson("/api/conta/motoristas/{$maria->id}", [], $this->headers)->assertStatus(422);
    }

    // ------------------------------------------------------------------- reserva

    public function test_reserva_um_veiculo_para_um_motorista(): void
    {
        $maria = $this->motorista();

        $this->reservar($this->veiculo(), ['motorista_id' => $maria->id, 'finalidade' => 'Visita a clientes'])
            ->assertJsonPath('status', 'agendado')
            ->assertJsonPath('motorista', 'Maria Silva')
            ->assertJsonPath('origem', 'agenda')
            ->assertJsonPath('veiculo.placa', 'AAA1B25');
    }

    public function test_bloqueia_o_mesmo_veiculo_no_mesmo_horario(): void
    {
        $v = $this->veiculo();
        $this->reservar($v, ['motorista_id' => $this->motorista('Ana')->id]);

        $r = $this->reservar($v, ['inicio_previsto' => '2026-11-10 11:00', 'fim_previsto' => '2026-11-10 15:00'], 422);

        $this->assertStringContainsString('O veículo já está reservado nesse horário', $r->json('message'));
        $this->assertNotNull($r->json('conflito.uso_id'));
    }

    public function test_horario_encostado_nao_e_conflito(): void
    {
        $v = $this->veiculo();
        $this->reservar($v);

        // Começa exatamente quando a outra termina.
        $this->reservar($v, ['inicio_previsto' => '2026-11-10 12:00', 'fim_previsto' => '2026-11-10 18:00']);
    }

    public function test_bloqueia_o_mesmo_motorista_em_dois_veiculos_ao_mesmo_tempo(): void
    {
        $maria = $this->motorista();
        $this->reservar($this->veiculo('AAA1B25'), ['motorista_id' => $maria->id]);

        $r = $this->reservar($this->veiculo('BBB2C36'), ['motorista_id' => $maria->id, 'inicio_previsto' => '2026-11-10 09:00'], 422);

        $this->assertStringContainsString('O motorista já tem outro uso nesse horário', $r->json('message'));
    }

    public function test_uso_cancelado_libera_o_horario(): void
    {
        $v = $this->veiculo();
        $id = $this->reservar($v)->json('id');

        $this->postJson("/api/conta/usos/{$id}/cancelar", [], $this->headers)->assertOk()->assertJsonPath('status', 'cancelado');

        $this->reservar($v);
    }

    public function test_centro_de_custo_do_veiculo_vale_quando_a_reserva_nao_informa(): void
    {
        $centro = CentroCusto::create(['nome' => 'Entregas']);
        $v = $this->veiculo('AAA1B25', ['centro_custo_id' => $centro->id]);

        $this->reservar($v)->assertJsonPath('centro_custo', 'Entregas');

        $outro = CentroCusto::create(['nome' => 'Vendas']);
        $this->reservar($v, ['inicio_previsto' => '2026-11-11 08:00', 'fim_previsto' => '2026-11-11 12:00', 'centro_custo_id' => $outro->id])
            ->assertJsonPath('centro_custo', 'Vendas');
    }

    public function test_valida_a_janela_e_os_vinculos_da_reserva(): void
    {
        $v = $this->veiculo();

        $this->reservar($v, ['fim_previsto' => '2026-11-10 07:00'], 422)->assertJsonValidationErrors('fim_previsto');
        $this->reservar($v, ['fim_previsto' => '2026-12-20 12:00'], 422);

        // Veículo, motorista e centro de OUTRA conta não existem para esta.
        [, $outra] = $this->autenticarConta('frota');
        $veiculoDeFora = VeiculoConta::create(['placa' => 'ZZZ9Z99', 'marca' => 'Fiat', 'modelo' => 'Uno', 'ano' => 2020]);
        $motoristaDeFora = Motorista::create(['nome' => 'De Fora']);
        $centroDeFora = CentroCusto::create(['nome' => 'De Fora']);

        // Volta para a conta original antes de tentar usar os ids da outra.
        [, $this->headers] = $this->autenticarConta('frota');
        $v2 = $this->veiculo('CCC3D47');

        $this->reservar($v2, ['motorista_id' => $motoristaDeFora->id], 422)->assertJsonValidationErrors('motorista_id');
        $this->reservar($v2, ['centro_custo_id' => $centroDeFora->id], 422)->assertJsonValidationErrors('centro_custo_id');
        $this->postJson('/api/conta/usos', [
            'veiculo_conta_id' => $veiculoDeFora->id, 'inicio_previsto' => '2026-11-10 08:00', 'fim_previsto' => '2026-11-10 12:00',
        ], $this->headers)->assertStatus(422)->assertJsonValidationErrors('veiculo_conta_id');
        unset($outra);
    }

    public function test_agenda_lista_so_o_periodo_e_os_filtros_pedidos(): void
    {
        $a = $this->veiculo('AAA1B25');
        $b = $this->veiculo('BBB2C36');
        $this->reservar($a);
        $this->reservar($b, ['inicio_previsto' => '2026-11-20 08:00', 'fim_previsto' => '2026-11-20 12:00']);
        $this->reservar($a, ['inicio_previsto' => '2026-12-05 08:00', 'fim_previsto' => '2026-12-05 12:00']);

        $this->getJson('/api/conta/usos?de=2026-11-01&ate=2026-11-30', $this->headers)->assertOk()->assertJsonCount(2, 'usos');
        $this->getJson("/api/conta/usos?de=2026-11-01&ate=2026-12-31&veiculo_id={$a->id}", $this->headers)->assertJsonCount(2, 'usos');
        $this->getJson('/api/conta/usos?de=2026-12-01&ate=2026-12-31', $this->headers)->assertJsonCount(1, 'usos');
    }

    public function test_uma_conta_nao_ve_os_usos_de_outra(): void
    {
        $id = $this->reservar($this->veiculo())->json('id');
        [, $outra] = $this->autenticarConta('frota');

        $this->getJson('/api/conta/usos?de=2026-11-01&ate=2026-11-30', $outra)->assertJsonCount(0, 'usos');
        $this->getJson("/api/conta/usos/{$id}", $outra)->assertStatus(404);
        $this->postJson("/api/conta/usos/{$id}/saida", ['km_saida' => 100], $outra)->assertStatus(404);
    }

    public function test_edita_uma_reserva_agendada_respeitando_conflitos(): void
    {
        $v = $this->veiculo();
        $a = $this->reservar($v)->json('id');
        $this->reservar($v, ['inicio_previsto' => '2026-11-10 14:00', 'fim_previsto' => '2026-11-10 18:00']);

        // Mover a primeira para cima da segunda conflita; para o dia seguinte, não.
        $this->putJson("/api/conta/usos/{$a}", [
            'veiculo_conta_id' => $v->id, 'inicio_previsto' => '2026-11-10 13:00', 'fim_previsto' => '2026-11-10 15:00',
        ], $this->headers)->assertStatus(422);

        $this->putJson("/api/conta/usos/{$a}", [
            'veiculo_conta_id' => $v->id, 'inicio_previsto' => '2026-11-11 08:00', 'fim_previsto' => '2026-11-11 10:00',
        ], $this->headers)->assertOk();
    }

    // ----------------------------------------------------------- saída e chegada

    public function test_saida_e_chegada_registram_o_km_e_sobem_o_km_do_veiculo(): void
    {
        $v = $this->veiculo();
        $id = $this->reservar($v)->json('id');

        $this->postJson("/api/conta/usos/{$id}/saida", ['km_saida' => 45200], $this->headers)
            ->assertOk()->assertJsonPath('status', 'em_uso')->assertJsonPath('km_saida', 45200);
        $this->assertSame(45200, $v->fresh()->km_atual);

        $this->postJson("/api/conta/usos/{$id}/chegada", ['km_chegada' => 45310], $this->headers)
            ->assertOk()->assertJsonPath('status', 'concluido')->assertJsonPath('km_rodados', 110);
        $this->assertSame(45310, $v->fresh()->km_atual);
        $this->assertNotNull($v->fresh()->km_atualizado_em);
    }

    public function test_chegada_menor_que_a_saida_e_recusada(): void
    {
        $id = $this->reservar($this->veiculo())->json('id');
        $this->postJson("/api/conta/usos/{$id}/saida", ['km_saida' => 5000], $this->headers)->assertOk();

        $this->postJson("/api/conta/usos/{$id}/chegada", ['km_chegada' => 4990], $this->headers)
            ->assertStatus(422);
        $this->assertSame('em_uso', UsoVeiculo::find($id)->status);
    }

    public function test_saida_com_hodometro_menor_que_o_ultimo_km_e_recusada_ate_corrigir(): void
    {
        $v = $this->veiculo();
        // O km atual não é preenchível em massa de propósito: só sobe por uso ou por correção do gestor.
        $v->forceFill(['km_atual' => 10000])->save();
        $id = $this->reservar($v)->json('id');

        $r = $this->postJson("/api/conta/usos/{$id}/saida", ['km_saida' => 9000], $this->headers)->assertStatus(422);
        $this->assertStringContainsString('10000', $r->json('message'));

        // O gestor corrige o km do veículo (o único jeito de ele diminuir) e a saída passa.
        $this->putJson("/api/conta/veiculos/{$v->id}/km", ['km' => 8500], $this->headers)->assertOk()->assertJsonPath('km_atual', 8500);
        $this->postJson("/api/conta/usos/{$id}/saida", ['km_saida' => 9000], $this->headers)->assertOk();
    }

    public function test_nao_sai_com_um_veiculo_que_ainda_esta_em_uso(): void
    {
        $v = $this->veiculo();
        $primeiro = $this->reservar($v)->json('id');
        $segundo = $this->reservar($v, ['inicio_previsto' => '2026-11-10 12:00', 'fim_previsto' => '2026-11-10 16:00'])->json('id');

        $this->postJson("/api/conta/usos/{$primeiro}/saida", ['km_saida' => 100], $this->headers)->assertOk();
        $this->postJson("/api/conta/usos/{$segundo}/saida", ['km_saida' => 150], $this->headers)
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'ainda está em uso'));
    }

    public function test_so_registra_saida_de_agendado_e_chegada_de_em_uso(): void
    {
        $id = $this->reservar($this->veiculo())->json('id');

        $this->postJson("/api/conta/usos/{$id}/chegada", ['km_chegada' => 200], $this->headers)->assertStatus(422);
        $this->postJson("/api/conta/usos/{$id}/saida", ['km_saida' => 100], $this->headers)->assertOk();
        $this->postJson("/api/conta/usos/{$id}/saida", ['km_saida' => 100], $this->headers)->assertStatus(422);
        $this->postJson("/api/conta/usos/{$id}/cancelar", [], $this->headers)->assertStatus(422);
    }

    public function test_km_invalido_e_recusado(): void
    {
        $id = $this->reservar($this->veiculo())->json('id');

        $this->postJson("/api/conta/usos/{$id}/saida", ['km_saida' => -1], $this->headers)->assertStatus(422)->assertJsonValidationErrors('km_saida');
        $this->postJson("/api/conta/usos/{$id}/saida", [], $this->headers)->assertStatus(422)->assertJsonValidationErrors('km_saida');
        $this->postJson("/api/conta/usos/{$id}/saida", ['km_saida' => 99999999], $this->headers)->assertStatus(422);
    }

    // -------------------------------------------------------------------- foto

    public function test_foto_do_hodometro_na_saida_fica_guardada_e_so_abre_com_link_assinado(): void
    {
        $id = $this->reservar($this->veiculo())->json('id');

        $r = $this->post("/api/conta/usos/{$id}/saida", ['km_saida' => 777, 'foto' => $this->foto()], $this->headers + ['Accept' => 'application/json'])
            ->assertOk();

        $url = $r->json('url_foto_saida');
        $this->assertNotNull($url);
        $this->assertNull($r->json('url_foto_chegada'));
        $this->assertCount(1, Storage::disk('local')->allFiles('hodometros'));

        // Sem token, só com o link assinado.
        $this->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(preg_replace('/signature=[^&]+/', 'signature=falsa', $url))->assertStatus(403);
        $this->get("/api/hodometros/{$id}/saida")->assertStatus(403);
    }

    public function test_recusa_arquivo_que_nao_e_imagem(): void
    {
        $id = $this->reservar($this->veiculo())->json('id');
        $caminho = tempnam(sys_get_temp_dir(), 'x');
        file_put_contents($caminho, '<?php echo 1;');
        $falso = new UploadedFile($caminho, 'painel.jpg', null, null, true);

        $this->post("/api/conta/usos/{$id}/saida", ['km_saida' => 100, 'foto' => $falso], $this->headers + ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('foto');
        $this->assertSame('agendado', UsoVeiculo::find($id)->status);
        $this->assertCount(0, Storage::disk('local')->allFiles('hodometros'));
    }

    public function test_anexa_ou_troca_a_foto_depois_e_apagar_o_uso_apaga_o_arquivo(): void
    {
        $id = $this->reservar($this->veiculo())->json('id');

        // Sem km registrado ainda, não há o que fotografar.
        $this->post("/api/conta/usos/{$id}/foto/saida", ['foto' => $this->foto()], $this->headers + ['Accept' => 'application/json'])->assertStatus(422);

        $this->postJson("/api/conta/usos/{$id}/saida", ['km_saida' => 100], $this->headers)->assertOk();
        $this->post("/api/conta/usos/{$id}/foto/saida", ['foto' => $this->foto()], $this->headers + ['Accept' => 'application/json'])->assertOk();
        $this->post("/api/conta/usos/{$id}/foto/saida", ['foto' => $this->foto()], $this->headers + ['Accept' => 'application/json'])->assertOk();
        $this->assertCount(1, Storage::disk('local')->allFiles('hodometros'), 'a troca apaga a foto anterior');

        $this->post("/api/conta/usos/{$id}/foto/inexistente", ['foto' => $this->foto()], $this->headers + ['Accept' => 'application/json'])->assertStatus(404);

        $this->deleteJson("/api/conta/usos/{$id}", [], $this->headers)->assertOk();
        $this->assertCount(0, Storage::disk('local')->allFiles('hodometros'));
    }

    // ----------------------------------------------------------------- km do veículo

    public function test_o_veiculo_aceita_centro_de_custo_padrao_so_da_propria_conta(): void
    {
        $centro = CentroCusto::create(['nome' => 'Entregas']);
        $v = $this->veiculo();

        $this->putJson("/api/conta/veiculos/{$v->id}", [
            'placa' => 'AAA1B25', 'marca' => 'Fiat', 'modelo' => 'Strada', 'ano' => 2022, 'centro_custo_id' => $centro->id,
        ], $this->headers)->assertOk();
        $this->assertSame($centro->id, $v->fresh()->centro_custo_id);

        $deFora = Conta::create(['tipo' => 'frota', 'nome' => 'Outra']);
        $centroDeFora = new CentroCusto(['nome' => 'Alheio']);
        $centroDeFora->conta_id = $deFora->id; // conta_id não é preenchível em massa
        $centroDeFora->save();

        $this->putJson("/api/conta/veiculos/{$v->id}", [
            'placa' => 'AAA1B25', 'marca' => 'Fiat', 'modelo' => 'Strada', 'ano' => 2022, 'centro_custo_id' => $centroDeFora->id,
        ], $this->headers)->assertStatus(422)->assertJsonValidationErrors('centro_custo_id');
    }
}
