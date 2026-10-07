<?php

namespace App\Services\Precos;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Preços do Mercado Livre pela API oficial (aplicativo gratuito do
 * desenvolvedor, fluxo "client credentials"). Só anúncios novos.
 */
class MercadoLivreFonte implements FontePrecos
{
    public function nome(): string
    {
        return 'Mercado Livre';
    }

    public function configurada(): bool
    {
        return filled(config('services.mercadolivre.client_id'))
            && filled(config('services.mercadolivre.client_secret'));
    }

    public function buscar(string $consulta, int $limite = 50): array
    {
        $cfg = config('services.mercadolivre');

        try {
            $resposta = Http::baseUrl($cfg['url'])
                ->timeout((int) $cfg['timeout'])
                ->withToken($this->token())
                ->acceptJson()
                ->get("/sites/{$cfg['site']}/search", [
                    'q' => $consulta,
                    'limit' => $limite,
                    'condition' => 'new',
                ])
                ->throw();
        } catch (ConnectionException|RequestException $e) {
            // Token recusado ou vencido: esquece o token para pedir outro na próxima.
            Cache::forget('ml:token');

            throw new FontePrecosIndisponivelException($e->getMessage(), previous: $e);
        }

        $ofertas = [];

        foreach ($resposta->json('results', []) as $item) {
            if (($item['currency_id'] ?? 'BRL') !== 'BRL' || empty($item['price']) || empty($item['permalink'])) {
                continue;
            }

            $ofertas[] = [
                'titulo' => (string) ($item['title'] ?? ''),
                'preco' => (float) $item['price'],
                'loja' => (string) ($item['official_store_name'] ?? $item['seller']['nickname'] ?? 'Vendedor do Mercado Livre'),
                'url' => (string) $item['permalink'],
                'imagem' => isset($item['thumbnail']) ? str_replace('http://', 'https://', $item['thumbnail']) : null,
                'frete_gratis' => (bool) ($item['shipping']['free_shipping'] ?? false),
            ];
        }

        return $ofertas;
    }

    private function token(): string
    {
        $cfg = config('services.mercadolivre');

        return Cache::remember('ml:token', 5 * 3600, function () use ($cfg) {
            try {
                $resposta = Http::baseUrl($cfg['url'])
                    ->timeout((int) $cfg['timeout'])
                    ->asForm()
                    ->acceptJson()
                    ->post('/oauth/token', [
                        'grant_type' => 'client_credentials',
                        'client_id' => $cfg['client_id'],
                        'client_secret' => $cfg['client_secret'],
                    ])
                    ->throw();
            } catch (ConnectionException|RequestException $e) {
                throw new FontePrecosIndisponivelException('Não foi possível autenticar no Mercado Livre.', previous: $e);
            }

            $token = $resposta->json('access_token');

            if (!$token) {
                throw new FontePrecosIndisponivelException('O Mercado Livre não devolveu o token de acesso.');
            }

            return $token;
        });
    }
}
