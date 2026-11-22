<?php

namespace Tests\Feature;

use App\Models\Abastecimento;
use App\Models\Conta;
use App\Models\Servico;
use App\Models\VeiculoConta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

/**
 * A km conhecida do veículo é uma só: a que o aviso por km usa (kmAtual) é a
 * mesma que a API entrega para o cartão (km_conhecida). Antes, o cartão lia o
 * km_atual cru (vazio sem usos da frota) e dizia "informar" enquanto o aviso
 * de pneus, com a km dos abastecimentos, dizia "já passou".
 */
class KmConhecidaVeiculoTest extends TestCase
{
    use AutenticaUsuario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-20 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // Placa final 5, sem UF: sem estimativas de IPVA/licenciamento no resumo.
    private function veiculo(array $extra = []): VeiculoConta
    {
        return VeiculoConta::create(array_merge(
            ['placa' => 'AAA1B25', 'marca' => 'Fiat', 'modelo' => 'Doblô', 'ano' => 2018],
            $extra,
        ));
    }

    private function abastecimento(VeiculoConta $veiculo, int $km, string $data): Abastecimento
    {
        return Abastecimento::create([
            'veiculo_conta_id' => $veiculo->id,
            'data' => $data,
            'km' => $km,
            'litros' => 40,
            'valor_total' => 250,
        ]);
    }

    private function servico(VeiculoConta $veiculo, int $km, string $realizadoEm, array $extra = []): Servico
    {
        return Servico::create(array_merge([
            'veiculo_conta_id' => $veiculo->id,
            'tipo' => 'oleo',
            'realizado_em' => $realizadoEm,
            'km' => $km,
        ], $extra));
    }

    public function test_abastecimento_com_km_maior_e_km_atual_nulo(): void
    {
        [, $headers] = $this->autenticarConta('frota');
        $veiculo = $this->veiculo();
        $this->abastecimento($veiculo, 48000, '2026-11-10');
        $this->abastecimento($veiculo, 50500, '2026-11-17');
        $this->servico($veiculo, 10000, '2025-01-10');

        $this->assertSame(50500, $veiculo->kmAtual());

        $this->getJson("/api/conta/veiculos/{$veiculo->id}", $headers)
            ->assertOk()
            ->assertJsonPath('km_atual', null)
            ->assertJsonPath('km_conhecida', ['km' => 50500, 'origem' => 'abastecimento', 'em' => '2026-11-17']);
    }

    public function test_servico_com_km_maior_que_o_hodometro(): void
    {
        [, $headers] = $this->autenticarConta('frota');
        $veiculo = $this->veiculo();
        $veiculo->forceFill(['km_atual' => 30000, 'km_atualizado_em' => '2026-10-01 08:00:00'])->save();
        $this->servico($veiculo, 41000, '2026-11-02');

        $this->getJson("/api/conta/veiculos/{$veiculo->id}", $headers)
            ->assertOk()
            ->assertJsonPath('km_atual', 30000)
            ->assertJsonPath('km_conhecida', ['km' => 41000, 'origem' => 'servico', 'em' => '2026-11-02']);
    }

    public function test_hodometro_vale_quando_e_o_maior_e_no_empate(): void
    {
        [, $headers] = $this->autenticarConta('frota');
        $veiculo = $this->veiculo();
        $veiculo->forceFill(['km_atual' => 60000, 'km_atualizado_em' => '2026-11-18 15:30:00'])->save();
        $this->abastecimento($veiculo, 60000, '2026-11-19');

        $this->getJson("/api/conta/veiculos/{$veiculo->id}", $headers)
            ->assertOk()
            ->assertJsonPath('km_conhecida', ['km' => 60000, 'origem' => 'hodometro', 'em' => '2026-11-18']);
    }

    public function test_sem_km_nenhuma_o_campo_vem_nulo(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $veiculo = $this->veiculo();
        $this->servico($veiculo, 0, '2026-11-01');

        $this->assertNull($veiculo->kmAtual());

        $resposta = $this->getJson("/api/conta/veiculos/{$veiculo->id}", $headers)->assertOk();
        $this->assertArrayHasKey('km_conhecida', $resposta->json());
        $this->assertNull($resposta->json('km_conhecida'));
        $this->assertNull($resposta->json('km_atual'));
    }

    public function test_listagens_e_gravacoes_entregam_o_campo_novo_sem_tirar_km_atual(): void
    {
        [, $headers] = $this->autenticarConta('frota');
        $veiculo = $this->veiculo();
        $this->abastecimento($veiculo, 12345, '2026-11-17');

        $pagina = $this->getJson('/api/conta/veiculos', $headers)->assertOk();
        $this->assertArrayHasKey('km_atual', $pagina->json('data.0'));
        $this->assertSame(12345, $pagina->json('data.0.km_conhecida.km'));

        $todos = $this->getJson('/api/conta/veiculos?all=1', $headers)->assertOk();
        $this->assertSame(12345, $todos->json('0.km_conhecida.km'));

        $this->putJson("/api/conta/veiculos/{$veiculo->id}", [
            'placa' => 'AAA1B25', 'marca' => 'Fiat', 'modelo' => 'Doblô', 'ano' => 2018,
        ], $headers)->assertOk()->assertJsonPath('km_conhecida.km', 12345);

        $this->postJson('/api/conta/veiculos', [
            'placa' => 'CCC3C33', 'marca' => 'VW', 'modelo' => 'Gol', 'ano' => 2019,
        ], $headers)->assertStatus(201)->assertJsonPath('km_conhecida', null);
    }

    public function test_aviso_de_pneus_e_cartao_usam_a_mesma_km(): void
    {
        [, $headers] = $this->autenticarConta('frota');
        $veiculo = $this->veiculo();
        $this->servico($veiculo, 10000, '2023-01-10', ['tipo' => 'pneus', 'intervalo_km' => 40000]);
        $this->abastecimento($veiculo, 50500, '2026-11-17');

        $aviso = collect($this->getJson('/api/conta/resumo', $headers)->assertOk()->json('vencimentos'))
            ->firstWhere('rotulo', 'Pneus');

        $this->assertNotNull($aviso);
        $this->assertTrue($aviso['por_km']);
        $this->assertSame(50000, $aviso['proxima_km']);
        $this->assertSame(-500, $aviso['km_restante']);

        $km = $this->getJson("/api/conta/veiculos/{$veiculo->id}", $headers)->json('km_conhecida.km');
        $this->assertSame($aviso['proxima_km'] - $aviso['km_restante'], $km);
    }

    public function test_sem_km_conhecida_nao_ha_aviso_por_km(): void
    {
        [, $headers] = $this->autenticarConta('frota');
        $veiculo = $this->veiculo();
        // Serviço sem km: não há base para "já passou".
        Servico::create(['veiculo_conta_id' => $veiculo->id, 'tipo' => 'pneus', 'realizado_em' => '2026-01-10', 'intervalo_km' => 40000]);

        $avisos = collect($this->getJson('/api/conta/resumo', $headers)->assertOk()->json('vencimentos'));

        $this->assertNull($avisos->firstWhere('rotulo', 'Pneus'));
    }

    public function test_km_de_outra_conta_nao_entra_na_km_conhecida(): void
    {
        [, $headersA] = $this->autenticarConta('frota');
        $contaA = app('conta.atual');
        $veiculoA = $this->veiculo();
        $this->abastecimento($veiculoA, 1000, '2026-11-10');

        $contaB = Conta::create(['tipo' => 'frota', 'nome' => 'Empresa B']);
        app()->instance('conta.atual', $contaB->id);
        $veiculoB = VeiculoConta::create(['placa' => 'BBB2B22', 'marca' => 'VW', 'modelo' => 'Gol', 'ano' => 2020]);
        $this->abastecimento($veiculoB, 99000, '2026-11-12');

        // Linhas forjadas da conta B apontando para o veículo da conta A: o
        // escopo por conta tem de ignorá-las.
        DB::table('abastecimentos')->insert([
            'conta_id' => $contaB->id, 'veiculo_conta_id' => $veiculoA->id, 'data' => '2026-11-15',
            'km' => 88000, 'litros' => 1, 'valor_total' => 1, 'tanque_cheio' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('servicos_conta')->insert([
            'conta_id' => $contaB->id, 'veiculo_conta_id' => $veiculoA->id, 'tipo' => 'oleo',
            'realizado_em' => '2026-11-15', 'km' => 77000, 'created_at' => now(), 'updated_at' => now(),
        ]);
        app()->instance('conta.atual', $contaA);

        $this->getJson("/api/conta/veiculos/{$veiculoA->id}", $headersA)
            ->assertOk()
            ->assertJsonPath('km_conhecida.km', 1000);

        $this->getJson("/api/conta/veiculos/{$veiculoB->id}", $headersA)->assertStatus(404);

        $lista = $this->getJson('/api/conta/veiculos?all=1', $headersA)->assertOk()->json();
        $this->assertCount(1, $lista);
        $this->assertSame(1000, $lista[0]['km_conhecida']['km']);
    }
}
