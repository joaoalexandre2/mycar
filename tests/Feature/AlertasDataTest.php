<?php

namespace Tests\Feature;

use App\Models\Motorista;
use App\Models\VeiculoConta;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

/**
 * Os alertas da frota contam os dias de Brasília. O servidor roda em UTC, e depois das 21h
 * em Brasília ele já está no dia seguinte: a conta não pode usar o "hoje" do servidor.
 */
class AlertasDataTest extends TestCase
{
    use AutenticaUsuario;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_depois_das_21h_em_brasilia_os_alertas_usam_o_dia_de_brasilia(): void
    {
        [, $headers] = $this->autenticarConta('frota');

        // 22h de 2 de novembro em Brasília são 1h de 3 de novembro em UTC.
        Carbon::setTestNow(Carbon::parse('2026-11-02 22:00:00', 'America/Sao_Paulo'));

        Motorista::create(['nome' => 'Vence hoje', 'cnh_numero' => '1', 'cnh_validade' => '2026-11-02', 'ativo' => true]);
        Motorista::create(['nome' => 'Venceu ontem', 'cnh_numero' => '2', 'cnh_validade' => '2026-11-01', 'ativo' => true]);
        Motorista::create(['nome' => 'Dentro da janela', 'cnh_numero' => '3', 'cnh_validade' => '2026-12-02', 'ativo' => true]);
        Motorista::create(['nome' => 'Fora da janela', 'cnh_numero' => '4', 'cnh_validade' => '2026-12-03', 'ativo' => true]);

        $cnh = collect($this->getJson('/api/conta/alertas', $headers)->assertOk()->json('cnh'))->keyBy('nome');

        $this->assertSame('vence_em_breve', $cnh['Vence hoje']['situacao']);
        $this->assertSame('vencida', $cnh['Venceu ontem']['situacao']);
        $this->assertTrue($cnh->has('Dentro da janela'));
        $this->assertFalse($cnh->has('Fora da janela'));
    }

    public function test_revisao_respeita_a_janela_de_brasilia(): void
    {
        [, $headers] = $this->autenticarConta('frota');

        Carbon::setTestNow(Carbon::parse('2026-11-02 22:00:00', 'America/Sao_Paulo'));

        VeiculoConta::create(['placa' => 'AAA1A11', 'marca' => 'Fiat', 'modelo' => 'Strada', 'ano' => 2022, 'revisao_prevista_em' => '2026-12-02']);
        VeiculoConta::create(['placa' => 'BBB2B22', 'marca' => 'VW', 'modelo' => 'Gol', 'ano' => 2020, 'revisao_prevista_em' => '2026-12-03']);

        $placas = collect($this->getJson('/api/conta/alertas', $headers)->assertOk()->json('revisoes'))->pluck('placa');

        $this->assertTrue($placas->contains('AAA1A11'));
        $this->assertFalse($placas->contains('BBB2B22'));
    }
}
