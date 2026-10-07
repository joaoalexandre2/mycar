<?php

namespace Tests\Feature;

use App\Services\Precos\ComparadorPrecos;
use App\Services\Precos\FontePrecos;
use App\Services\Precos\FontePrecosIndisponivelException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\AutenticaUsuario;
use Tests\TestCase;

class ComparadorTest extends TestCase
{
    use RefreshDatabase;
    use AutenticaUsuario;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function configurar(): void
    {
        config([
            'services.mercadolivre.client_id' => 'id-de-teste',
            'services.mercadolivre.client_secret' => 'segredo-de-teste',
        ]);
    }

    /** @param array<int, float> $precos */
    private function resultados(array $precos): array
    {
        return ['results' => array_map(fn ($p, $i) => [
            'id' => "MLB{$i}", 'title' => "Pneu 185/65 R15 #{$i}", 'price' => $p, 'currency_id' => 'BRL',
            'permalink' => "https://produto.mercadolivre.com.br/MLB-{$i}",
            'thumbnail' => "http://http2.mlstatic.com/{$i}.jpg",
            'seller' => ['nickname' => "LOJA{$i}"], 'shipping' => ['free_shipping' => $i % 2 === 0],
        ], $precos, array_keys($precos))];
    }

    private function fake(array $precos): void
    {
        Http::fake([
            '*/oauth/token' => Http::response(['access_token' => 'TOKEN-ML', 'expires_in' => 21600]),
            '*/sites/MLB/search*' => Http::response($this->resultados($precos)),
        ]);
    }

    // ------------------------------------------------------------------- API

    public function test_exige_autenticacao_e_perfil_de_conta(): void
    {
        $this->getJson('/api/conta/comparador?q=pneu')->assertStatus(401);

        [, $headers] = $this->autenticar();
        $this->getJson('/api/conta/comparador?q=pneu 185/65 R15', $headers)->assertStatus(403);
    }

    public function test_valida_a_busca(): void
    {
        [, $headers] = $this->autenticarConta('pessoa');

        $this->getJson('/api/conta/comparador', $headers)->assertStatus(422)->assertJsonValidationErrors('q');
        $this->getJson('/api/conta/comparador?q=ab', $headers)->assertStatus(422);
        $this->getJson('/api/conta/comparador?q='.str_repeat('a', 121), $headers)->assertStatus(422);
    }

    public function test_sem_chaves_do_mercado_livre_avisa_e_nao_chama_a_loja(): void
    {
        Http::fake();
        config(['services.mercadolivre.client_id' => null, 'services.mercadolivre.client_secret' => null]);
        [, $headers] = $this->autenticarConta('pessoa');

        $this->getJson('/api/conta/comparador?q=pneu 185/65 R15', $headers)
            ->assertStatus(200)->assertJsonPath('status', 'sem_configuracao')->assertJsonCount(0, 'itens');

        Http::assertNothingSent();
    }

    public function test_devolve_as_tres_ofertas_mais_baratas_e_destaca_a_menor(): void
    {
        $this->configurar();
        $this->fake([420.0, 389.9, 455.0, 399.0, 512.0, 430.0]);
        [, $headers] = $this->autenticarConta('pessoa');

        $r = $this->getJson('/api/conta/comparador?q=pneu 185/65 R15', $headers)->assertStatus(200);

        $r->assertJsonPath('status', 'ok')
            ->assertJsonPath('fonte', 'Mercado Livre')
            ->assertJsonCount(3, 'itens')
            ->assertJsonPath('total_encontrado', 6)
            ->assertJsonPath('mais_barato.preco', 389.9)
            ->assertJsonPath('mais_barato.loja', 'LOJA1')
            ->assertJsonPath('mais_barato.url', 'https://produto.mercadolivre.com.br/MLB-1');

        $this->assertSame([389.9, 399, 420], array_column($r->json('itens'), 'preco'));
        $this->assertSame('https://http2.mlstatic.com/1.jpg', $r->json('itens.0.imagem')); // força https
        $this->assertGreaterThan(0, $r->json('mais_barato.economia_vs_mediana'));
    }

    public function test_usa_o_token_oauth_e_so_anuncios_novos(): void
    {
        $this->configurar();
        $this->fake([400.0, 410.0, 420.0]);
        [, $headers] = $this->autenticarConta('pessoa');

        $this->getJson('/api/conta/comparador?q=pneu 185/65 R15', $headers)->assertStatus(200);

        Http::assertSent(fn ($req) => str_contains($req->url(), '/oauth/token')
            && $req['grant_type'] === 'client_credentials' && $req['client_id'] === 'id-de-teste');
        Http::assertSent(fn ($req) => str_contains($req->url(), '/sites/MLB/search')
            && $req->hasHeader('Authorization', 'Bearer TOKEN-ML')
            && $req['condition'] === 'new' && $req['q'] === 'pneu 185/65 R15');
    }

    public function test_descarta_precos_que_destoam_da_mediana(): void
    {
        $this->configurar();
        // 9,90 (acessório) e 3.900 (atacado) destoam de ~400: não podem virar o "mais barato".
        $this->fake([9.9, 395.0, 400.0, 410.0, 3900.0, 420.0]);
        [, $headers] = $this->autenticarConta('pessoa');

        $r = $this->getJson('/api/conta/comparador?q=pneu 185/65 R15', $headers)->assertStatus(200);

        $this->assertSame(395, $r->json('mais_barato.preco'));
        $this->assertSame(4, $r->json('total_encontrado'));
    }

    public function test_guarda_o_resultado_em_cache_para_nao_repetir_a_busca(): void
    {
        $this->configurar();
        $this->fake([400.0, 410.0, 420.0]);
        [, $headers] = $this->autenticarConta('pessoa');

        $this->getJson('/api/conta/comparador?q=Pneu 185/65 R15', $headers)->assertStatus(200);
        $this->getJson('/api/conta/comparador?q=pneu 185/65 r15', $headers)->assertStatus(200);

        Http::assertSentCount(2); // 1 token + 1 busca (a segunda consulta veio do cache)
    }

    public function test_loja_fora_do_ar_nao_derruba(): void
    {
        $this->configurar();
        Http::fake(['*' => Http::response('erro', 500)]);
        [, $headers] = $this->autenticarConta('pessoa');

        $this->getJson('/api/conta/comparador?q=pneu 185/65 R15', $headers)
            ->assertStatus(200)->assertJsonPath('status', 'indisponivel')->assertJsonPath('mais_barato', null);
    }

    public function test_sem_resultados(): void
    {
        $this->configurar();
        $this->fake([]);
        [, $headers] = $this->autenticarConta('pessoa');

        $this->getJson('/api/conta/comparador?q=peca-que-nao-existe-xyz', $headers)
            ->assertStatus(200)->assertJsonPath('status', 'sem_resultados')->assertJsonCount(0, 'itens');
    }

    // ------------------------------------------------- serviço, sem rede

    public function test_com_poucas_ofertas_nao_descarta_nenhuma(): void
    {
        $fonte = new class implements FontePrecos {
            public function nome(): string { return 'Teste'; }
            public function configurada(): bool { return true; }
            public function buscar(string $consulta, int $limite = 50): array
            {
                return [
                    ['titulo' => 'A', 'preco' => 10.0, 'loja' => 'X', 'url' => 'https://a', 'imagem' => null, 'frete_gratis' => false],
                    ['titulo' => 'B', 'preco' => 900.0, 'loja' => 'Y', 'url' => 'https://b', 'imagem' => null, 'frete_gratis' => false],
                ];
            }
        };

        $r = (new ComparadorPrecos($fonte))->comparar('qualquer coisa');

        $this->assertSame('ok', $r['status']);
        $this->assertSame(2, $r['total_encontrado']);
        $this->assertSame(10.0, $r['mais_barato']['preco']);
    }

    public function test_excecao_da_fonte_vira_status_indisponivel(): void
    {
        $fonte = new class implements FontePrecos {
            public function nome(): string { return 'Teste'; }
            public function configurada(): bool { return true; }
            public function buscar(string $consulta, int $limite = 50): array
            {
                throw new FontePrecosIndisponivelException('fora');
            }
        };

        $this->assertSame('indisponivel', (new ComparadorPrecos($fonte))->comparar('qualquer coisa')['status']);
    }
}
