<?php

namespace Tests\Feature;

use App\Models\Veiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class VeiculoTributosTest extends TestCase
{
    use AutenticaUsuario;
    use RefreshDatabase;

    public function test_calcula_ipva_estimado_e_licenciamento_pelo_estado(): void
    {
        [, $headers] = $this->autenticar();
        $veiculo = Veiculo::factory()->create([
            'uf' => 'PR',
            'fipe_valor' => 50000,
        ]);

        $this->getJson("/api/veiculos/{$veiculo->id}", $headers)
            ->assertStatus(200)
            ->assertJsonPath('ipva_estimado', 950)
            ->assertJsonPath('licenciamento_valor', 90.94);
    }

    public function test_sem_valor_fipe_nao_calcula_ipva_mas_mostra_licenciamento(): void
    {
        [, $headers] = $this->autenticar();
        $veiculo = Veiculo::factory()->create(['uf' => 'PR', 'fipe_valor' => null]);

        $this->getJson("/api/veiculos/{$veiculo->id}", $headers)
            ->assertJsonPath('ipva_estimado', null)
            ->assertJsonPath('licenciamento_valor', 90.94);
    }

    public function test_sem_estado_nada_e_calculado(): void
    {
        [, $headers] = $this->autenticar();
        $veiculo = Veiculo::factory()->create(['uf' => null, 'fipe_valor' => 50000]);

        $this->getJson("/api/veiculos/{$veiculo->id}", $headers)
            ->assertJsonPath('ipva_estimado', null)
            ->assertJsonPath('licenciamento_valor', null);
    }

    public function test_estado_sem_aliquota_confirmada_usa_a_media_e_avisa_que_e_aproximado(): void
    {
        [, $headers] = $this->autenticar();
        $veiculo = Veiculo::factory()->create(['uf' => 'GO', 'fipe_valor' => 50000]);

        // 3% (config/tributos.php: aliquota_media_ipva) sobre R$ 50.000
        $this->getJson("/api/veiculos/{$veiculo->id}", $headers)
            ->assertJsonPath('ipva_estimado', 1500)
            ->assertJsonPath('ipva_aliquota_media', true)
            ->assertJsonPath('licenciamento_valor', 251.25);
    }

    public function test_estado_com_aliquota_confirmada_nao_usa_a_media(): void
    {
        [, $headers] = $this->autenticar();
        $veiculo = Veiculo::factory()->create(['uf' => 'PR', 'fipe_valor' => 50000]);

        $this->getJson("/api/veiculos/{$veiculo->id}", $headers)
            ->assertJsonPath('ipva_aliquota_media', false);
    }

    public function test_veiculo_com_mais_de_20_anos_e_isento_de_ipva_em_qualquer_estado(): void
    {
        Carbon::setTestNow('2026-10-19');
        [, $headers] = $this->autenticar();
        // Palio 2001: 25 anos de fabricação. Mesmo com estado e valor FIPE, não há IPVA.
        $palio = Veiculo::factory()->create(['uf' => 'PR', 'fipe_valor' => 8000, 'ano' => 2001, 'ano_fabricacao' => 2001]);

        $this->getJson("/api/veiculos/{$palio->id}", $headers)
            ->assertStatus(200)
            ->assertJsonPath('idade_anos', 25)
            ->assertJsonPath('isento_ipva', true)
            ->assertJsonPath('ipva_estimado', null)
            // O aviso de "pode ser isento" é só para quem ainda não é: aqui a isenção é certa.
            ->assertJsonPath('possivel_isencao_ipva', false)
            ->assertJsonPath('licenciamento_valor', 90.94);

        $this->assertNull($palio->proximo_vencimento_ipva);

        Carbon::setTestNow();
    }

    public function test_isencao_vale_so_acima_de_20_anos_e_o_resto_vira_aviso(): void
    {
        Carbon::setTestNow('2026-10-19');
        [, $headers] = $this->autenticar();

        $casos = [
            // ano de fabricação => [isento, possível isenção, tem IPVA estimado]
            2005 => [true, false, false],  // 21 anos: isento
            2006 => [false, true, true],   // exatamente 20: as fontes divergem, então só avisa
            2011 => [false, true, true],   // 15 anos: alguns estados isentam
            2012 => [false, false, true],  // 14 anos: paga
            2024 => [false, false, true],
        ];

        foreach ($casos as $ano => [$isento, $possivel, $temIpva]) {
            $v = Veiculo::factory()->create(['uf' => 'PR', 'fipe_valor' => 20000, 'ano' => $ano, 'ano_fabricacao' => $ano]);

            $r = $this->getJson("/api/veiculos/{$v->id}", $headers)->assertStatus(200);

            $this->assertSame($isento, $r->json('isento_ipva'), "isento_ipva em {$ano}");
            $this->assertSame($possivel, $r->json('possivel_isencao_ipva'), "possivel_isencao_ipva em {$ano}");
            $this->assertSame($temIpva, $r->json('ipva_estimado') !== null, "ipva_estimado em {$ano}");
            // O vencimento do veículo da oficina não vai na API: confere no modelo, que alimenta os alertas.
            $this->assertSame($temIpva, $v->proximo_vencimento_ipva !== null, "vencimento em {$ano}");
        }

        Carbon::setTestNow();
    }

    public function test_sem_ano_de_fabricacao_usa_o_ano_do_modelo_para_a_isencao(): void
    {
        Carbon::setTestNow('2026-10-19');
        [, $headers] = $this->autenticar();
        $v = Veiculo::factory()->create(['uf' => 'SP', 'fipe_valor' => 8000, 'ano' => 2001, 'ano_fabricacao' => null]);

        $this->getJson("/api/veiculos/{$v->id}", $headers)->assertJsonPath('isento_ipva', true);

        Carbon::setTestNow();
    }

    public function test_rejeita_uf_invalida(): void
    {
        [, $headers] = $this->autenticar();
        $veiculo = Veiculo::factory()->create();

        $this->putJson("/api/veiculos/{$veiculo->id}", [
            'cliente_id' => $veiculo->cliente_id,
            'placa' => $veiculo->placa,
            'marca' => $veiculo->marca,
            'modelo' => $veiculo->modelo,
            'ano' => $veiculo->ano,
            'uf' => 'XX',
        ], $headers)->assertStatus(422)->assertJsonValidationErrors('uf');
    }
}
