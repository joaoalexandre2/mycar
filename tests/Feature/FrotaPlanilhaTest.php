<?php

namespace Tests\Feature;

use App\Models\CentroCusto;
use App\Models\Motorista;
use App\Models\UsoVeiculo;
use App\Models\VeiculoConta;
use App\Services\ImportadorUsosVeiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class FrotaPlanilhaTest extends TestCase
{
    use AutenticaUsuario;
    use RefreshDatabase;

    private const CABECALHO = 'placa;data_saida;hora_saida;data_chegada;hora_chegada;km_saida;km_chegada;motorista;centro_custo;finalidade';

    /** @var array<string, string> */
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();
        [, $this->headers] = $this->autenticarConta('frota');
    }

    private function veiculo(string $placa = 'AAA1B25'): VeiculoConta
    {
        return VeiculoConta::create(['placa' => $placa, 'marca' => 'Fiat', 'modelo' => 'Strada', 'ano' => 2022]);
    }

    /** @param  array<int, string>  $linhas */
    private function planilha(array $linhas, string $nome = 'uso.csv', string $cabecalho = self::CABECALHO): UploadedFile
    {
        $caminho = tempnam(sys_get_temp_dir(), 'planilha');
        file_put_contents($caminho, implode("\r\n", array_merge([$cabecalho], $linhas)) . "\r\n");

        return new UploadedFile($caminho, $nome, 'text/csv', null, true);
    }

    private function importar(UploadedFile $arquivo, array $extra = [], ?int $esperado = null)
    {
        $r = $this->post('/api/conta/usos/importar', ['arquivo' => $arquivo] + $extra, $this->headers + ['Accept' => 'application/json']);

        return $esperado ? $r->assertStatus($esperado) : $r;
    }

    // ------------------------------------------------------------------- modelo

    public function test_baixa_o_modelo_com_cabecalho_e_exemplo_comentado(): void
    {
        $r = $this->get('/api/conta/usos/planilha-modelo', $this->headers)->assertOk();

        $this->assertStringContainsString('text/csv', $r->headers->get('Content-Type'));
        $this->assertStringContainsString('planilha-de-utilizacao.csv', $r->headers->get('Content-Disposition'));

        $corpo = $r->getContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF" . self::CABECALHO, $corpo);
        $this->assertStringContainsString('# Exemplo', $corpo);
    }

    public function test_o_proprio_modelo_importa_sem_erro_e_sem_dados(): void
    {
        $caminho = tempnam(sys_get_temp_dir(), 'modelo');
        file_put_contents($caminho, ImportadorUsosVeiculo::modeloCsv());

        $this->importar(new UploadedFile($caminho, 'modelo.csv', 'text/csv', null, true), [], 200)
            ->assertJsonPath('usos', 0);
        $this->assertSame(0, UsoVeiculo::count());
    }

    // ---------------------------------------------------------------- importação

    public function test_importa_usos_concluidos_e_leva_o_km_ao_maior_valor(): void
    {
        $v = $this->veiculo();
        Motorista::create(['nome' => 'Maria Silva']);
        CentroCusto::create(['nome' => 'Entregas']);

        $this->importar($this->planilha([
            'AAA1B25;15/10/2026;08:00;15/10/2026;18:30;45200;45310;Maria Silva;Entregas;Visita a clientes',
            'AAA1B25;16/10/2026;09:00;16/10/2026;17:00;45310;45400;maria silva;ENTREGAS;',
        ]), [], 200)
            ->assertJsonPath('usos', 2)
            ->assertJsonPath('veiculos', 1)
            ->assertJsonPath('km_rodados', 200)
            ->assertJsonPath('gravado', true);

        $usos = UsoVeiculo::orderBy('inicio_previsto')->get();
        $this->assertCount(2, $usos);
        $this->assertSame('concluido', $usos[0]->status);
        $this->assertSame('planilha', $usos[0]->origem);
        $this->assertSame(45200, $usos[0]->km_saida);
        $this->assertSame(110, $usos[0]->kmRodados());
        $this->assertSame('Visita a clientes', $usos[0]->finalidade);
        $this->assertNotNull($usos[0]->motorista_id);
        $this->assertSame(45400, $v->fresh()->km_atual);
    }

    public function test_simular_valida_e_resume_sem_gravar_nada(): void
    {
        $v = $this->veiculo();

        $this->importar($this->planilha(['AAA1B25;15/10/2026;08:00;15/10/2026;18:30;45200;45310;;;']), ['simular' => 1], 200)
            ->assertJsonPath('usos', 1)->assertJsonPath('gravado', false);

        $this->assertSame(0, UsoVeiculo::count());
        $this->assertNull($v->fresh()->km_atual);
    }

    public function test_o_km_atual_do_veiculo_nunca_desce_com_uma_planilha_antiga(): void
    {
        $v = $this->veiculo();
        $v->forceFill(['km_atual' => 90000])->save();

        $this->importar($this->planilha(['AAA1B25;15/01/2026;08:00;15/01/2026;18:00;100;200;;;']), [], 200);

        $this->assertSame(90000, $v->fresh()->km_atual);
        $this->assertSame(1, UsoVeiculo::count());
    }

    public function test_aceita_virgula_datas_iso_km_com_ponto_e_horas_padrao(): void
    {
        $v = $this->veiculo();

        $this->importar($this->planilha(['AAA1B25,2026-10-15,,,,"45.200","45.310,4",,,'], 'uso.csv', 'placa,data_saida,hora_saida,data_chegada,hora_chegada,km_saida,km_chegada,motorista,centro_custo,finalidade'), [], 200)
            ->assertJsonPath('usos', 1);

        $uso = UsoVeiculo::first();
        $this->assertSame(45200, $uso->km_saida);
        $this->assertSame(45310, $uso->km_chegada);
        // Sem horas: o dia inteiro (00:00 a 23:59).
        $this->assertSame('2026-10-15 00:00', $uso->inicio_previsto->format('Y-m-d H:i'));
        $this->assertSame('2026-10-15 23:59', $uso->fim_previsto->format('Y-m-d H:i'));
        $this->assertSame(45310, $v->fresh()->km_atual);
    }

    public function test_le_planilha_do_excel_antigo_em_windows_1252(): void
    {
        $this->veiculo();
        Motorista::create(['nome' => 'João Conceição']);

        $caminho = tempnam(sys_get_temp_dir(), 'planilha');
        $texto = self::CABECALHO . "\r\nAAA1B25;15/10/2026;08:00;15/10/2026;18:00;100;200;João Conceição;;Reunião\r\n";
        file_put_contents($caminho, mb_convert_encoding($texto, 'Windows-1252', 'UTF-8'));

        $this->importar(new UploadedFile($caminho, 'excel.csv', 'text/csv', null, true), [], 200)->assertJsonPath('usos', 1);

        $uso = UsoVeiculo::with('motorista')->first();
        $this->assertSame('João Conceição', $uso->motorista->nome);
        $this->assertSame('Reunião', $uso->finalidade);
    }

    public function test_motorista_e_centro_desconhecidos_dao_erro_ou_sao_criados_quando_pedido(): void
    {
        $this->veiculo();
        $linha = 'AAA1B25;15/10/2026;08:00;15/10/2026;18:00;100;200;Pedro Novo;Centro Novo;';

        $r = $this->importar($this->planilha([$linha]), [], 422);
        $this->assertStringContainsString('motorista "Pedro Novo" não está cadastrado', $r->json('erros.0'));
        $this->assertStringContainsString('centro de custo "Centro Novo" não está cadastrado', $r->json('erros.0'));
        $this->assertSame(0, Motorista::count());

        $this->importar($this->planilha([$linha]), ['criar_faltantes' => 1], 200)
            ->assertJsonPath('motoristas_novos.0', 'Pedro Novo')
            ->assertJsonPath('centros_novos.0', 'Centro Novo');
        $this->assertSame(1, Motorista::count());
        $this->assertSame(1, CentroCusto::count());
        $this->assertNotNull(UsoVeiculo::first()->centro_custo_id);
    }

    public function test_linha_invalida_aborta_tudo_e_aponta_a_linha(): void
    {
        $this->veiculo();

        $r = $this->importar($this->planilha([
            'AAA1B25;15/10/2026;08:00;15/10/2026;18:00;100;200;;;',  // boa
            'ZZZ9Z99;15/10/2026;08:00;15/10/2026;18:00;100;200;;;',  // placa que não existe
            'AAA1B25;31/02/2026;08:00;;;100;200;;;',                  // data impossível
            'AAA1B25;16/10/2026;08:00;16/10/2026;18:00;500;400;;;',  // km de chegada menor
            'AAA1B25;17/10/2026;25:00;17/10/2026;18:00;300;400;;;',  // hora inválida
            'AAA1B25;18/10/2026;08:00;18/10/2026;07:00;300;400;;;',  // chegada antes da saída
            'AAA1B25;19/10/2026;08:00;19/10/2026;18:00;;;;;',        // km vazio
        ]), [], 422);

        $erros = implode("\n", $r->json('erros'));
        $this->assertStringContainsString('linha 3: placa "ZZZ9Z99" não está cadastrada', $erros);
        $this->assertStringContainsString('linha 4: data_saida "31/02/2026" inválida', $erros);
        $this->assertStringContainsString('linha 5: km de chegada (400) menor que o de saída (500)', $erros);
        $this->assertStringContainsString('linha 6: hora_saida "25:00" inválida', $erros);
        $this->assertStringContainsString('linha 7: a chegada precisa ser depois da saída', $erros);
        $this->assertStringContainsString('linha 8: km_saida vazio', $erros);
        $this->assertSame(6, $r->json('total_de_erros'));

        // A linha 2 era boa, mas nada é gravado.
        $this->assertSame(0, UsoVeiculo::count());
    }

    public function test_recusa_horarios_sobrepostos_e_hodometro_que_anda_para_tras(): void
    {
        $this->veiculo();

        $r = $this->importar($this->planilha([
            'AAA1B25;15/10/2026;08:00;15/10/2026;12:00;100;200;;;',
            'AAA1B25;15/10/2026;11:00;15/10/2026;15:00;200;300;;;',  // começa antes de a anterior acabar
            'AAA1B25;16/10/2026;08:00;16/10/2026;12:00;250;350;;;',  // km de saída menor que o de chegada anterior
        ]), [], 422);

        $erros = implode("\n", $r->json('erros'));
        $this->assertStringContainsString('linha 3: o horário se sobrepõe ao da linha 2', $erros);
        $this->assertStringContainsString('linha 4: o km de saída (250) é menor que o km de chegada da linha 3 (300)', $erros);
        $this->assertSame(0, UsoVeiculo::count());
    }

    public function test_recusa_uso_que_conflita_com_a_agenda_existente(): void
    {
        $v = $this->veiculo();
        UsoVeiculo::create([
            'veiculo_conta_id' => $v->id, 'inicio_previsto' => '2026-10-15 09:00', 'fim_previsto' => '2026-10-15 11:00',
            'status' => 'agendado', 'origem' => 'agenda',
        ]);

        $r = $this->importar($this->planilha(['AAA1B25;15/10/2026;08:00;15/10/2026;10:00;100;200;;;']), [], 422);

        $this->assertStringContainsString('já existe um uso desse veículo na agenda', $r->json('erros.0'));
    }

    public function test_conflito_da_planilha_diz_quem_ja_usa_o_veiculo_nesse_horario(): void
    {
        $v = $this->veiculo();
        $motorista = Motorista::create(['nome' => 'Salsicha']);
        UsoVeiculo::create([
            'veiculo_conta_id' => $v->id, 'motorista_id' => $motorista->id, 'finalidade' => 'Entrega',
            'inicio_previsto' => '2026-10-15 09:00', 'fim_previsto' => '2026-10-15 11:00',
            'status' => 'agendado', 'origem' => 'agenda',
        ]);

        $r = $this->importar($this->planilha(['AAA1B25;15/10/2026;08:00;15/10/2026;10:00;100;200;;;']), [], 422);

        $this->assertSame(
            'linha 2: já existe um uso desse veículo na agenda: Salsicha, de 15/10/2026 09:00 a 15/10/2026 11:00 (finalidade: Entrega)',
            $r->json('erros.0'),
        );
    }

    public function test_so_enxerga_os_veiculos_da_propria_conta(): void
    {
        [, $outra] = $this->autenticarConta('frota');
        $this->veiculo('BBB2C36'); // da outra conta
        [, $this->headers] = $this->autenticarConta('frota');

        $r = $this->importar($this->planilha(['BBB2C36;15/10/2026;08:00;15/10/2026;10:00;100;200;;;']), [], 422);

        $this->assertStringContainsString('placa "BBB2C36" não está cadastrada', $r->json('erros.0'));
        unset($outra);
    }

    // ---------------------------------------------------------- arquivo e acesso

    public function test_recusa_arquivo_que_nao_e_csv_e_cabecalho_sem_colunas_obrigatorias(): void
    {
        $this->importar($this->planilha([], 'planilha.pdf'), [], 422)->assertJsonValidationErrors('arquivo');

        $r = $this->importar($this->planilha(['AAA1B25;1;2'], 'uso.csv', 'placa;data_saida;km_saida'), [], 422);
        $this->assertStringContainsString('Coluna obrigatória ausente no cabeçalho: km_chegada', $r->json('erros.0'));

        $this->post('/api/conta/usos/importar', [], $this->headers + ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_so_frota_importa_e_baixa_o_modelo(): void
    {
        [, $pessoa] = $this->autenticarConta('pessoa');

        $this->get('/api/conta/usos/planilha-modelo', $pessoa + ['Accept' => 'application/json'])->assertStatus(403);
        $this->post('/api/conta/usos/importar', ['arquivo' => $this->planilha([])], $pessoa + ['Accept' => 'application/json'])->assertStatus(403);
    }
}
