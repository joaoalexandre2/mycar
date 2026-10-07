<?php

namespace App\Services\Precos;

use Illuminate\Support\Facades\Cache;

/**
 * Busca uma peça nas fontes de preço e devolve as 3 ofertas mais baratas,
 * descartando as que destoam demais da mediana (acessórios, anúncios de
 * outra coisa ou erro de preço), para o "mais barato" não ser um engano.
 */
class ComparadorPrecos
{
    /** Preço abaixo disto da mediana costuma ser outra coisa (ex.: capa, brinquedo). */
    private const PISO_DA_MEDIANA = 0.4;

    /** Preço acima disto da mediana costuma ser kit ou atacado. */
    private const TETO_DA_MEDIANA = 3.0;

    public function __construct(private readonly FontePrecos $fonte)
    {
    }

    /**
     * @return array{status: string, fonte: string, consulta: string, itens: array<int, array<string, mixed>>, mais_barato: ?array<string, mixed>, total_encontrado: int, mediana: ?float}
     */
    public function comparar(string $consulta): array
    {
        $consulta = trim(preg_replace('/\s+/u', ' ', $consulta));

        $base = [
            'fonte' => $this->fonte->nome(),
            'consulta' => $consulta,
            'itens' => [],
            'mais_barato' => null,
            'total_encontrado' => 0,
            'mediana' => null,
        ];

        if (!$this->fonte->configurada()) {
            return ['status' => 'sem_configuracao'] + $base;
        }

        try {
            $ofertas = Cache::remember(
                'precos:'.md5(mb_strtolower($consulta)),
                (int) config('services.mercadolivre.cache_ttl'),
                fn () => $this->fonte->buscar($consulta),
            );
        } catch (FontePrecosIndisponivelException) {
            return ['status' => 'indisponivel'] + $base;
        }

        $validas = $this->descartarDestoantes($ofertas);

        if (!$validas) {
            return ['status' => 'sem_resultados'] + $base;
        }

        usort($validas, fn ($a, $b) => $a['preco'] <=> $b['preco']);

        $mediana = $this->mediana(array_column($validas, 'preco'));
        $itens = array_slice($validas, 0, 3);
        $maisBarato = $itens[0] + ['economia_vs_mediana' => round($mediana - $itens[0]['preco'], 2)];

        return [
            'status' => 'ok',
            'itens' => $itens,
            'mais_barato' => $maisBarato,
            'total_encontrado' => count($validas),
            'mediana' => round($mediana, 2),
        ] + $base;
    }

    /**
     * @param array<int, array<string, mixed>> $ofertas
     * @return array<int, array<string, mixed>>
     */
    private function descartarDestoantes(array $ofertas): array
    {
        $ofertas = array_values(array_filter($ofertas, fn ($o) => ($o['preco'] ?? 0) > 0));

        if (count($ofertas) < 4) {
            return $ofertas; // poucos dados: não dá para dizer o que destoa
        }

        $mediana = $this->mediana(array_column($ofertas, 'preco'));

        return array_values(array_filter(
            $ofertas,
            fn ($o) => $o['preco'] >= $mediana * self::PISO_DA_MEDIANA && $o['preco'] <= $mediana * self::TETO_DA_MEDIANA,
        ));
    }

    /** @param array<int, float> $valores */
    private function mediana(array $valores): float
    {
        sort($valores);
        $n = count($valores);
        $meio = intdiv($n, 2);

        return $n % 2 ? $valores[$meio] : ($valores[$meio - 1] + $valores[$meio]) / 2;
    }
}
