<?php

namespace Tests\Feature;

use App\Models\Motorista;
use App\Models\Multa;
use App\Models\UsoVeiculo;
use App\Models\VeiculoConta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

/**
 * Antes do fuso de Brasília, as datas digitadas foram gravadas como UTC (sem converter).
 * O comando soma 3 horas nas linhas gravadas antes do corte, e só nelas.
 */
class CorrigirFusoAntigoTest extends TestCase
{
    use AutenticaUsuario;
    use RefreshDatabase;

    private const CORTE = '2026-11-02 20:55:00';

    private VeiculoConta $veiculo;

    private Motorista $motorista;

    protected function setUp(): void
    {
        parent::setUp();

        $this->autenticarConta('frota');

        $this->veiculo = VeiculoConta::create(['placa' => 'AAA1A11', 'marca' => 'Fiat', 'modelo' => 'Strada', 'ano' => 2022]);
        $this->motorista = Motorista::create(['nome' => 'Maria', 'cnh_numero' => '123', 'cnh_validade' => now()->addYears(2)->toDateString()]);
    }

    /** Uso como o sistema gravava antes do fuso: horário digitado guardado como UTC. */
    private function usoAntigo(array $extra = [], string $criado = '2026-10-01 10:00:00', ?string $editado = null): UsoVeiculo
    {
        $uso = UsoVeiculo::create(array_merge([
            'veiculo_conta_id' => $this->veiculo->id,
            'motorista_id' => $this->motorista->id,
            'origem' => 'agenda',
            'status' => 'agendado',
            'inicio_previsto' => '2026-11-10 08:00:00',
            'fim_previsto' => '2026-11-10 18:00:00',
        ], $extra));

        DB::table('usos_veiculo')->where('id', $uso->id)->update(['created_at' => $criado, 'updated_at' => $editado ?? $criado]);

        return $uso;
    }

    private function cru(int $usoId, string $coluna): ?string
    {
        return DB::table('usos_veiculo')->where('id', $usoId)->value($coluna);
    }

    public function test_sem_executar_so_mostra_o_plano_e_nao_grava(): void
    {
        $uso = $this->usoAntigo();

        $this->artisan('frota:corrigir-fuso-antigo', ['--antes' => self::CORTE])
            ->expectsOutputToContain('Nada foi gravado')
            ->assertExitCode(0);

        $this->assertSame('2026-11-10 08:00:00', $this->cru($uso->id, 'inicio_previsto'));
    }

    public function test_soma_3_horas_nas_datas_antigas_e_so_nelas(): void
    {
        $agenda = $this->usoAntigo(['saida_em' => '2026-11-10 08:05:00', 'chegada_em' => '2026-11-10 11:50:00']);
        $planilha = $this->usoAntigo(['origem' => 'planilha', 'saida_em' => '2026-11-10 08:05:00', 'chegada_em' => '2026-11-10 11:50:00']);
        $depoisDoCorte = $this->usoAntigo([], criado: '2099-01-01 00:00:00');

        $this->artisan('frota:corrigir-fuso-antigo', ['--antes' => self::CORTE, '--executar' => true])->assertExitCode(0);

        // Agenda: a reserva foi digitada, então muda; saída e chegada vieram de now(), então não mudam.
        $this->assertSame('2026-11-10 11:00:00', $this->cru($agenda->id, 'inicio_previsto'));
        $this->assertSame('2026-11-10 21:00:00', $this->cru($agenda->id, 'fim_previsto'));
        $this->assertSame('2026-11-10 08:05:00', $this->cru($agenda->id, 'saida_em'));
        $this->assertSame('2026-11-10 11:50:00', $this->cru($agenda->id, 'chegada_em'));

        // Planilha: saída e chegada também foram digitadas, então mudam.
        $this->assertSame('2026-11-10 11:05:00', $this->cru($planilha->id, 'saida_em'));
        $this->assertSame('2026-11-10 14:50:00', $this->cru($planilha->id, 'chegada_em'));

        // Gravado depois do corte: já estava certo.
        $this->assertSame('2026-11-10 08:00:00', $this->cru($depoisDoCorte->id, 'inicio_previsto'));
    }

    public function test_soma_3_horas_na_data_da_multa_gravada_antes_do_corte(): void
    {
        $multa = Multa::create([
            'veiculo_conta_id' => $this->veiculo->id,
            'data_hora' => '2026-10-20 14:00:00',
            'descricao' => 'Excesso de velocidade',
            'situacao' => Multa::ABERTA,
        ]);
        DB::table('multas')->where('id', $multa->id)->update(['created_at' => '2026-10-20 15:00:00']);

        $this->artisan('frota:corrigir-fuso-antigo', ['--antes' => self::CORTE, '--executar' => true])->assertExitCode(0);

        $this->assertSame('2026-10-20 17:00:00', DB::table('multas')->where('id', $multa->id)->value('data_hora'));
    }

    public function test_nao_aplica_duas_vezes(): void
    {
        $uso = $this->usoAntigo();

        $this->artisan('frota:corrigir-fuso-antigo', ['--antes' => self::CORTE, '--executar' => true])->assertExitCode(0);
        $this->artisan('frota:corrigir-fuso-antigo', ['--antes' => self::CORTE, '--executar' => true])
            ->expectsOutputToContain('já foi aplicada')
            ->assertExitCode(1);

        $this->assertSame('2026-11-10 11:00:00', $this->cru($uso->id, 'inicio_previsto'));
    }

    public function test_avisa_os_usos_criados_antes_e_editados_depois_do_corte(): void
    {
        $this->usoAntigo([], criado: '2026-10-01 10:00:00', editado: '2026-11-03 09:00:00');

        $this->artisan('frota:corrigir-fuso-antigo', ['--antes' => self::CORTE])
            ->expectsOutputToContain('editados depois do corte (1)')
            ->assertExitCode(0);
    }
}
