<?php

namespace Tests\Feature;

use App\Models\FotoVeiculoConta;
use App\Models\VeiculoConta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

/** O veículo traz a capa do cartão: a miniatura da foto mais recente do álbum. */
class FotoCapaTest extends TestCase
{
    use RefreshDatabase;
    use AutenticaUsuario;

    private function veiculo(string $placa = 'AAA1B25'): VeiculoConta
    {
        return VeiculoConta::create(['placa' => $placa, 'marca' => 'Fiat', 'modelo' => 'Uno', 'ano' => 2018]);
    }

    private function foto(VeiculoConta $v, string $nome, ?string $criadaEm = null): FotoVeiculoConta
    {
        $foto = FotoVeiculoConta::create([
            'veiculo_conta_id' => $v->id, 'caminho' => "{$nome}.jpg", 'caminho_miniatura' => "{$nome}-mini.jpg",
            'mime' => 'image/jpeg', 'tamanho' => 10,
        ]);

        if ($criadaEm) {
            $foto->forceFill(['created_at' => $criadaEm])->saveQuietly();
        }

        return $foto;
    }

    public function test_sem_foto_a_capa_vem_nula(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $this->veiculo();

        $this->getJson('/api/conta/veiculos', $headers)->assertStatus(200)->assertJsonPath('data.0.foto_capa_url', null);
    }

    public function test_a_capa_e_a_miniatura_da_foto_mais_recente_com_link_assinado(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');
        $v = $this->veiculo();
        $this->foto($v, 'antiga', '2026-01-01 10:00:00');
        $nova = $this->foto($v, 'nova', '2026-03-01 10:00:00');

        $url = $this->getJson('/api/conta/veiculos', $headers)->json('data.0.foto_capa_url');

        $this->assertStringContainsString("/api/fotos/{$nova->id}/miniatura", $url);
        $this->assertStringContainsString('signature=', $url);
        $this->assertStringStartsWith('/api/', $url); // relativo: não depende do APP_URL
    }

    public function test_cada_veiculo_tem_a_sua_capa(): void
    {
        [, $headers] = $this->autenticarConta('frota');
        $a = $this->veiculo('AAA1B25');
        $this->veiculo('BBB2C36');
        $fotoA = $this->foto($a, 'a');

        $dados = collect($this->getJson('/api/conta/veiculos', $headers)->json('data'))->keyBy('placa');

        $this->assertStringContainsString("/fotos/{$fotoA->id}/miniatura", $dados['AAA1B25']['foto_capa_url']);
        $this->assertNull($dados['BBB2C36']['foto_capa_url']);
    }

    public function test_a_capa_de_uma_conta_nao_aparece_na_outra(): void
    {
        [, $headersA] = $this->autenticarConta('pessoa');
        $this->foto($this->veiculo(), 'segredo');

        [, $headersB] = $this->autenticarConta('pessoa');

        $this->getJson('/api/conta/veiculos', $headersB)->assertJsonCount(0, 'data');
    }
}
