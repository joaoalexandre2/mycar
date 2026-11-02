<?php

namespace Tests\Feature;

use App\Models\Conta;
use App\Models\VeiculoConta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class PlanoContaTest extends TestCase
{
    use AutenticaUsuario;
    use RefreshDatabase;

    private function contaComPlano(string $plano, ?int $limite): Conta
    {
        return Conta::create(['tipo' => 'frota', 'nome' => 'Frota teste', 'plano' => $plano, 'limite_veiculos' => $limite]);
    }

    private function veiculo(Conta $conta, string $placa): void
    {
        app()->instance('conta.atual', $conta->id);
        VeiculoConta::create(['placa' => $placa, 'marca' => 'Fiat', 'modelo' => 'Strada', 'ano' => 2022]);
    }

    public function test_conta_sem_plano_nao_tem_limite(): void
    {
        $conta = Conta::create(['tipo' => 'frota', 'nome' => 'Sem plano']);

        $this->assertFalse($conta->atingiuLimiteDeVeiculos());
    }

    public function test_conta_no_limite_nao_cadastra_mais_veiculos(): void
    {
        $conta = $this->contaComPlano('starter', 3);
        foreach (['AAA1A11', 'BBB2B22', 'CCC3C33'] as $placa) {
            $this->veiculo($conta, $placa);
        }

        $this->assertTrue($conta->fresh()->atingiuLimiteDeVeiculos());
    }

    public function test_cadastro_acima_do_limite_e_recusado_com_mensagem(): void
    {
        $conta = $this->contaComPlano('starter', 1);
        $this->veiculo($conta, 'AAA1A11');
        [, $headers] = $this->autenticarConta('frota', $conta);

        $this->postJson('/api/conta/veiculos', [
            'placa' => 'BBB2B22', 'marca' => 'Fiat', 'modelo' => 'Strada', 'ano' => 2022,
        ], $headers)
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Seu plano Starter permite até 1 veículos. Troque de plano para cadastrar mais.']);

        $this->assertSame(1, VeiculoConta::where('conta_id', $conta->id)->count());
    }

    public function test_me_informa_o_plano_da_conta_da_frota(): void
    {
        $conta = $this->contaComPlano('basico', 10);
        [, $headers] = $this->autenticarConta('frota', $conta);

        $this->getJson('/api/me', $headers)
            ->assertOk()
            ->assertJsonPath('plano', 'basico')
            ->assertJsonPath('limite_veiculos', 10);
    }

    public function test_comando_define_o_plano_e_recusa_plano_invalido(): void
    {
        $conta = Conta::create(['tipo' => 'frota', 'nome' => 'Comando']);

        $this->artisan('conta:plano', ['conta' => $conta->id, 'plano' => 'profissional'])->assertSuccessful();
        $this->assertSame(20, $conta->fresh()->limite_veiculos);

        $this->artisan('conta:plano', ['conta' => $conta->id, 'plano' => 'livre'])->assertSuccessful();
        $this->assertNull($conta->fresh()->limite_veiculos);

        $this->artisan('conta:plano', ['conta' => $conta->id, 'plano' => 'inventado'])->assertFailed();
    }
}
