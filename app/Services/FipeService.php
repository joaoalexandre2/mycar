<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FipeService
{
    public const TIPOS = ['carros', 'motos', 'caminhoes'];

    public function marcas(string $tipo = 'carros'): array
    {
        return $this->buscar($tipo, "{$tipo}/marcas");
    }

    public function modelos(string $tipo, int $marca): array
    {
        return $this->buscar($tipo, "{$tipo}/marcas/{$marca}/modelos");
    }

    public function anos(string $tipo, int $marca, int $modelo): array
    {
        return $this->buscar($tipo, "{$tipo}/marcas/{$marca}/modelos/{$modelo}/anos");
    }

    public function valor(string $tipo, int $marca, int $modelo, string $ano): array
    {
        return $this->buscar($tipo, "{$tipo}/marcas/{$marca}/modelos/{$modelo}/anos/{$ano}");
    }

    private function buscar(string $tipo, string $caminho): array
    {
        if (!in_array($tipo, self::TIPOS, true)) {
            throw new RuntimeException("Tipo de veículo inválido: {$tipo}");
        }

        return Cache::remember(
            'fipe:' . $caminho,
            (int) config('services.fipe.cache_ttl'),
            function () use ($caminho) {
                try {
                    return Http::baseUrl(config('services.fipe.url'))
                        ->timeout((int) config('services.fipe.timeout'))
                        ->acceptJson()
                        ->get($caminho)
                        ->throw()
                        ->json();
                } catch (ConnectionException|RequestException $e) {
                    throw new FipeIndisponivelException($e->getMessage(), previous: $e);
                }
            }
        );
    }
}
