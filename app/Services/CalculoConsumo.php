<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Consumo (km/l) e custo por km a partir dos abastecimentos de um veículo,
 * pelo método do tanque cheio:
 *
 * - o primeiro tanque cheio só marca o ponto de partida (não se sabe quanto
 *   havia no tanque antes);
 * - a cada novo tanque cheio, a distância desde o último cheio é dividida
 *   pelos litros colocados desde então (parciais inclusos);
 * - abastecimentos parciais acumulam litros e valor, mas não fecham intervalo.
 */
class CalculoConsumo
{
    /**
     * @param Collection<int, array{id: int, data: string, km: int, litros: float, valor_total: float, tanque_cheio: bool}> $registros
     * @return array{
     *   registros: array<int, array<string, mixed>>,
     *   consumo_medio_km_l: ?float, custo_por_km: ?float,
     *   total_gasto: float, total_litros: float,
     *   preco_medio_litro: ?float, km_atual: ?int
     * }
     */
    public function calcular(Collection $registros): array
    {
        $ordenados = $registros
            ->sortBy([['data', 'asc'], ['km', 'asc'], ['id', 'asc']])
            ->values();

        $kmDoUltimoCheio = null;
        $litrosAcumulados = 0.0;
        $valorAcumulado = 0.0;

        $distanciaTotal = 0;
        $litrosDosIntervalos = 0.0;
        $valorDosIntervalos = 0.0;

        $saida = [];

        foreach ($ordenados as $registro) {
            $linha = [
                'id' => $registro['id'],
                'preco_litro' => $registro['litros'] > 0
                    ? round($registro['valor_total'] / $registro['litros'], 3)
                    : null,
                'consumo_km_l' => null,
                'custo_por_km' => null,
            ];

            if ($kmDoUltimoCheio !== null) {
                $litrosAcumulados += $registro['litros'];
                $valorAcumulado += $registro['valor_total'];

                if ($registro['tanque_cheio']) {
                    $distancia = $registro['km'] - $kmDoUltimoCheio;

                    if ($distancia > 0 && $litrosAcumulados > 0) {
                        $linha['consumo_km_l'] = round($distancia / $litrosAcumulados, 2);
                        $linha['custo_por_km'] = round($valorAcumulado / $distancia, 3);

                        $distanciaTotal += $distancia;
                        $litrosDosIntervalos += $litrosAcumulados;
                        $valorDosIntervalos += $valorAcumulado;
                    }

                    $kmDoUltimoCheio = $registro['km'];
                    $litrosAcumulados = 0.0;
                    $valorAcumulado = 0.0;
                }
            } elseif ($registro['tanque_cheio']) {
                $kmDoUltimoCheio = $registro['km'];
            }

            $saida[] = $linha;
        }

        $totalLitros = (float) $ordenados->sum('litros');
        $totalGasto = (float) $ordenados->sum('valor_total');

        return [
            'registros' => $saida,
            'consumo_medio_km_l' => $litrosDosIntervalos > 0 ? round($distanciaTotal / $litrosDosIntervalos, 2) : null,
            'custo_por_km' => $distanciaTotal > 0 ? round($valorDosIntervalos / $distanciaTotal, 3) : null,
            'total_gasto' => round($totalGasto, 2),
            'total_litros' => round($totalLitros, 3),
            'preco_medio_litro' => $totalLitros > 0 ? round($totalGasto / $totalLitros, 3) : null,
            'km_atual' => $ordenados->isEmpty() ? null : (int) $ordenados->max('km'),
        ];
    }
}
