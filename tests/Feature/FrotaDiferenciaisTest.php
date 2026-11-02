<?php

namespace Tests\Feature;

use App\Models\ChecklistUso;
use App\Models\Conta;
use App\Models\Motorista;
use App\Models\Multa;
use App\Models\UsoVeiculo;
use App\Models\VeiculoConta;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class FrotaDiferenciaisTest extends TestCase
{
    use AutenticaUsuario;
    use RefreshDatabase;

    /** @var array<string, string> */
    private array $headersAdmin;

    private Conta $conta;

    private VeiculoConta $veiculo;

    private Motorista $motorista;

    /** @var array<string, string> */
    private array $headersMotorista;

    protected function setUp(): void
    {
        parent::setUp();

        [, $this->headersAdmin] = $this->autenticarConta('frota');
        $this->conta = Conta::find(app('conta.atual'));
        app()->instance('conta.atual', $this->conta->id);

        $this->veiculo = VeiculoConta::create(['placa' => 'AAA1A11', 'marca' => 'Fiat', 'modelo' => 'Strada', 'ano' => 2022]);
        $this->motorista = Motorista::create(['nome' => 'Maria', 'cnh_numero' => '123', 'cnh_validade' => now()->addYears(2)->toDateString()]);

        [$usuario, $this->headersMotorista] = $this->autenticarConta('frota', $this->conta);
        $usuario->forceFill(['papel_frota' => 'motorista'])->save();
        $this->motorista->forceFill(['user_id' => $usuario->id])->save();
        app()->instance('conta.atual', $this->conta->id);
    }

    private function uso(array $extra = []): UsoVeiculo
    {
        return UsoVeiculo::create(array_merge([
            'veiculo_conta_id' => $this->veiculo->id,
            'motorista_id' => $this->motorista->id,
            'inicio_previsto' => now()->subHour(),
            'fim_previsto' => now()->addHours(3),
            'status' => 'agendado',
        ], $extra));
    }

    private function checklistCompleto(bool $tudoOk = true): array
    {
        return [
            'momento' => 'saida',
            'itens' => collect(ChecklistUso::ITENS)
                ->keys()->map(fn (string $item) => ['item' => $item, 'ok' => $tudoOk])->all(),
        ];
    }

    public function test_motorista_faz_o_checklist_da_propria_reserva(): void
    {
        $uso = $this->uso();

        $this->postJson("/api/conta/usos/{$uso->id}/checklist", $this->checklistCompleto(), $this->headersMotorista)
            ->assertStatus(201)
            ->assertJsonPath('problemas', 0);

        $this->postJson("/api/conta/usos/{$uso->id}/checklist", $this->checklistCompleto(), $this->headersMotorista)
            ->assertStatus(422);
    }

    public function test_checklist_exige_todos_os_itens_e_registra_os_problemas(): void
    {
        $uso = $this->uso();
        $incompleto = ['momento' => 'saida', 'itens' => [['item' => 'pneus', 'ok' => false]]];

        $this->postJson("/api/conta/usos/{$uso->id}/checklist", $incompleto, $this->headersMotorista)->assertStatus(422);

        $completo = $this->checklistCompleto(false);
        $this->postJson("/api/conta/usos/{$uso->id}/checklist", $completo, $this->headersMotorista)
            ->assertStatus(201)
            ->assertJsonPath('problemas', count(ChecklistUso::ITENS));
    }

    public function test_motorista_nao_ve_o_checklist_de_reserva_de_outro(): void
    {
        $outro = Motorista::create(['nome' => 'Outro', 'cnh_numero' => '9', 'cnh_validade' => now()->addYear()->toDateString()]);
        $uso = $this->uso(['motorista_id' => $outro->id]);

        $this->getJson("/api/conta/usos/{$uso->id}/checklist", $this->headersMotorista)->assertStatus(404);
    }

    public function test_qr_do_veiculo_mostra_ao_motorista_so_as_reservas_dele_de_hoje(): void
    {
        $this->uso();
        $outro = Motorista::create(['nome' => 'Outro', 'cnh_numero' => '9', 'cnh_validade' => now()->addYear()->toDateString()]);
        $this->uso(['motorista_id' => $outro->id]);

        $token = $this->postJson("/api/conta/veiculos/{$this->veiculo->id}/qr", [], $this->headersAdmin)
            ->assertOk()->json('token');

        $this->getJson("/api/conta/qr/{$token}", $this->headersMotorista)
            ->assertOk()
            ->assertJsonPath('veiculo.placa', 'AAA1A11')
            ->assertJsonCount(1, 'reservas');
    }

    public function test_qr_desconhecido_e_recusado(): void
    {
        $this->getJson('/api/conta/qr/nao-existe', $this->headersMotorista)->assertStatus(404);
    }

    public function test_multa_sem_responsavel_informado_fica_com_quem_estava_com_o_veiculo(): void
    {
        $uso = $this->uso(['saida_em' => now()->subHour(), 'inicio_previsto' => now()->subHour()]);

        $this->postJson('/api/conta/multas', [
            'veiculo_conta_id' => $this->veiculo->id,
            'data_hora' => now()->subMinutes(10)->toDateTimeString(),
            'descricao' => 'Excesso de velocidade',
            'valor' => 130.16,
            'pontos' => 4,
        ], $this->headersAdmin)
            ->assertStatus(201)
            ->assertJsonPath('motorista.nome', 'Maria')
            ->assertJsonPath('situacao', 'aberta');

        $this->assertSame($uso->id, Multa::first()->uso_veiculo_id);
    }

    public function test_lista_de_multas_e_paginada(): void
    {
        foreach ([1, 2, 3] as $dias) {
            $this->postJson('/api/conta/multas', [
                'veiculo_conta_id' => $this->veiculo->id,
                'data_hora' => now()->subDays($dias)->toDateTimeString(),
                'descricao' => "Multa {$dias}",
                'valor' => 10,
                'pontos' => 0,
            ], $this->headersAdmin)->assertStatus(201);
        }

        $this->getJson('/api/conta/multas?per_page=2&page=2', $this->headersAdmin)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.descricao', 'Multa 3')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3);
    }

    public function test_motorista_nao_mexe_em_multas(): void
    {
        $this->getJson('/api/conta/multas', $this->headersMotorista)->assertStatus(403);
    }

    public function test_alertas_mostram_cnh_vencida(): void
    {
        Motorista::create(['nome' => 'Vencido', 'cnh_numero' => '5', 'cnh_validade' => now()->subDay()->toDateString()]);

        $this->getJson('/api/conta/alertas', $this->headersAdmin)
            ->assertOk()
            ->assertJsonFragment(['nome' => 'Vencido', 'situacao' => 'vencida']);
    }

    public function test_painel_calcula_as_horas_usadas_do_veiculo_no_periodo(): void
    {
        $dia = now()->toDateString();
        $this->uso([
            'inicio_previsto' => "{$dia} 08:00:00",
            'fim_previsto' => "{$dia} 10:00:00",
            'saida_em' => "{$dia} 08:00:00",
            'chegada_em' => "{$dia} 10:00:00",
            'status' => 'concluido',
            'km_saida' => 1000,
            'km_chegada' => 1085,
        ]);

        $this->getJson("/api/conta/painel-frota?de={$dia}&ate={$dia}", $this->headersAdmin)
            ->assertOk()
            ->assertJsonPath('veiculos.0.horas_em_uso', 2)
            ->assertJsonPath('veiculos.0.horas_reservadas', 2)
            ->assertJsonPath('veiculos.0.km_rodados', 85)
            ->assertJsonPath('veiculos.0.reservas', 1);
    }

    public function test_reserva_sem_saida_nao_conta_como_uso_real(): void
    {
        $dia = now()->toDateString();
        $this->uso([
            'inicio_previsto' => "{$dia} 08:00:00",
            'fim_previsto' => "{$dia} 10:00:00",
        ]);

        $this->getJson("/api/conta/painel-frota?de={$dia}&ate={$dia}", $this->headersAdmin)
            ->assertOk()
            ->assertJsonPath('veiculos.0.horas_em_uso', 0)
            ->assertJsonPath('veiculos.0.horas_reservadas', 2);
    }

    public function test_alertas_informam_o_total_de_problemas_mesmo_passando_do_limite_da_lista(): void
    {
        for ($i = 0; $i < 52; $i++) {
            ChecklistUso::create([
                'uso_veiculo_id' => $this->uso()->id,
                'momento' => 'saida',
                'itens' => [['item' => 'pneus', 'ok' => false]],
                'feito_por_user_id' => null,
            ]);
        }

        $this->getJson('/api/conta/alertas', $this->headersAdmin)
            ->assertOk()
            ->assertJsonPath('checklists_com_problema_total', 52)
            ->assertJsonCount(50, 'checklists_com_problema');
    }

    public function test_uso_real_que_passa_do_horario_reservado_entra_no_periodo(): void
    {
        $dia = '2026-11-10';
        UsoVeiculo::create([
            'veiculo_conta_id' => $this->veiculo->id,
            'motorista_id' => $this->motorista->id,
            'inicio_previsto' => "{$dia} 08:00:00",
            'fim_previsto' => "{$dia} 10:00:00",
            'saida_em' => "{$dia} 08:00:00",
            'chegada_em' => "{$dia} 13:00:00",
            'status' => 'concluido',
        ]);

        // Consulta só das 11h às 13h: a reserva terminou às 10h, mas o carro ainda estava rodando.
        $this->getJson("/api/conta/painel-frota?de={$dia}&ate={$dia}", $this->headersAdmin)
            ->assertOk()
            ->assertJsonPath('veiculos.0.reservas', 1);
    }

    public function test_periodo_de_um_dia_tem_24_horas(): void
    {
        $this->getJson('/api/conta/painel-frota?de=2026-11-10&ate=2026-11-10', $this->headersAdmin)
            ->assertOk()
            ->assertJsonPath('horas_periodo', 24);
    }

    public function test_periodo_que_vira_o_ano_conta_as_horas_certas(): void
    {
        $this->getJson('/api/conta/painel-frota?de=2026-12-31&ate=2027-01-01', $this->headersAdmin)
            ->assertOk()
            ->assertJsonPath('horas_periodo', 48);
    }

    public function test_periodo_de_um_mes_conta_todos_os_dias(): void
    {
        $this->getJson('/api/conta/painel-frota?de=2026-02-01&ate=2026-02-28', $this->headersAdmin)
            ->assertOk()
            ->assertJsonPath('horas_periodo', 672);
    }

    public function test_painel_mostra_so_os_veiculos_da_propria_conta(): void
    {
        $outra = Conta::create(['tipo' => 'frota', 'nome' => 'Outra frota']);
        app()->instance('conta.atual', $outra->id);
        VeiculoConta::create(['placa' => 'ZZZ9Z99', 'marca' => 'VW', 'modelo' => 'Gol', 'ano' => 2020]);
        app()->instance('conta.atual', $this->conta->id);

        $this->getJson('/api/conta/painel-frota', $this->headersAdmin)
            ->assertOk()
            ->assertJsonCount(1, 'veiculos')
            ->assertJsonPath('veiculos.0.placa', 'AAA1A11');
    }

    public function test_motorista_nao_consulta_painel_nem_multas(): void
    {
        $this->getJson('/api/conta/painel-frota', $this->headersMotorista)->assertStatus(403);
        $this->getJson('/api/conta/multas', $this->headersMotorista)->assertStatus(403);
        $this->getJson('/api/conta/alertas', $this->headersMotorista)->assertStatus(403);
    }

    public function test_o_banco_nao_aceita_dois_checklists_do_mesmo_momento_na_mesma_reserva(): void
    {
        $uso = $this->uso();
        $linha = [
            'conta_id' => $this->conta->id,
            'uso_veiculo_id' => $uso->id,
            'momento' => 'saida',
            'itens' => json_encode([['item' => 'pneus', 'ok' => true]]),
            'problemas' => 0,
        ];

        DB::table('checklists_uso')->insert($linha);

        $this->expectException(QueryException::class);
        DB::table('checklists_uso')->insert($linha);
    }

    public function test_corrida_no_checklist_vira_422_e_nao_erro_500(): void
    {
        $uso = $this->uso();

        // Simula a corrida: a checagem prévia não viu nada, mas o banco recusa a segunda gravação.
        ChecklistUso::creating(function () {
            $erro = new \PDOException('UNIQUE constraint failed: checklists_uso.uso_veiculo_id', 0);
            $erro->errorInfo = ['23000', 19, 'UNIQUE constraint failed'];

            throw new QueryException('sqlite', 'insert into checklists_uso', [], $erro);
        });

        try {
            $this->postJson("/api/conta/usos/{$uso->id}/checklist", $this->checklistCompleto(), $this->headersMotorista)
                ->assertStatus(422)
                ->assertJsonFragment(['message' => 'Este checklist já foi feito.']);
        } finally {
            ChecklistUso::getEventDispatcher()->forget('eloquent.creating: ' . ChecklistUso::class);
        }
    }

    public function test_outra_violacao_de_integridade_nao_vira_checklist_ja_feito(): void
    {
        $uso = $this->uso();

        // Uma chave estrangeira falhando também é SQLSTATE 23000, mas não é a duplicidade do checklist.
        ChecklistUso::creating(function () {
            $erro = new \PDOException('FOREIGN KEY constraint failed', 0);
            $erro->errorInfo = ['23000', 787, 'FOREIGN KEY constraint failed'];

            throw new QueryException('sqlite', 'insert into checklists_uso', [], $erro);
        });

        try {
            $this->withoutExceptionHandling();
            $this->expectException(QueryException::class);

            $this->postJson("/api/conta/usos/{$uso->id}/checklist", $this->checklistCompleto(), $this->headersMotorista);
        } finally {
            ChecklistUso::getEventDispatcher()->forget('eloquent.creating: ' . ChecklistUso::class);
        }
    }
}
