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

    private const TIPOS_V2 = ['carros' => 'cars', 'motos' => 'motorcycles', 'caminhoes' => 'trucks'];

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

    /**
     * Anos que a marca tem na tabela (já com o combustível: "2014 Flex"), para
     * escolher o ano antes do modelo. O código ("2014-5") é o mesmo da consulta de valor.
     *
     * @return array<int, array{codigo: string, nome: string}>
     */
    public function anosDaMarca(string $tipo, int $marca): array
    {
        return $this->normalizar($this->buscarV2($tipo, "brands/{$marca}/years"));
    }

    /**
     * Só os modelos da marca que existem naquele ano (código "2014-5").
     *
     * @return array<int, array{codigo: string, nome: string}>
     */
    public function modelosDoAno(string $tipo, int $marca, string $ano): array
    {
        return $this->normalizar($this->buscarV2($tipo, "brands/{$marca}/years/{$ano}/models"));
    }

    /**
     * A v2 devolve {code, name}; o app usa {codigo, nome}, como na v1.
     *
     * @param  array<int, array{code: string|int, name: string}>  $itens
     * @return array<int, array{codigo: string, nome: string}>
     */
    private function normalizar(array $itens): array
    {
        return array_map(
            fn (array $item) => ['codigo' => (string) $item['code'], 'nome' => (string) $item['name']],
            array_values($itens),
        );
    }

    /** A v2 usa os tipos em inglês no caminho: cars, motorcycles, trucks. */
    private function buscarV2(string $tipo, string $caminho): array
    {
        $segmento = self::TIPOS_V2[$tipo] ?? null;

        if ($segmento === null) {
            throw new RuntimeException("Tipo de veículo inválido: {$tipo}");
        }

        return $this->requisitar(config('services.fipe.url_v2'), "fipe:v2:{$segmento}/{$caminho}", "{$segmento}/{$caminho}");
    }

    /**
     * Converte o valor devolvido pela FIPE ("R$ 60.250,50") em número.
     */
    public static function valorParaDecimal(string $valor): float
    {
        return (float) str_replace(['.', ','], ['', '.'], preg_replace('/[^\d.,]/', '', $valor));
    }

    private function buscar(string $tipo, string $caminho): array
    {
        if (!in_array($tipo, self::TIPOS, true)) {
            throw new RuntimeException("Tipo de veículo inválido: {$tipo}");
        }

        return $this->requisitar(config('services.fipe.url'), 'fipe:' . $caminho, $caminho);
    }

    private function requisitar(string $base, string $chaveDoCache, string $caminho): array
    {
        return Cache::remember(
            $chaveDoCache,
            (int) config('services.fipe.cache_ttl'),
            function () use ($base, $caminho) {
                try {
                    return Http::baseUrl($base)
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
