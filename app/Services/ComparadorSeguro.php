<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Compara a apólice atual e as propostas de um veículo, e dá a faixa de
 * referência pelo valor FIPE. Não é cotação: ver config/seguro.php.
 */
class ComparadorSeguro
{
    /**
     * @param Collection<int, array{id: int, tipo: string, valor_anual: float}> $seguros
     * @return array{
     *   referencia: ?array{baixo: float, medio: float, alto: float},
     *   apolice_atual_id: ?int, melhor_proposta_id: ?int,
     *   itens: array<int, array<string, mixed>>
     * }
     */
    public function comparar(Collection $seguros, ?float $valorFipe): array
    {
        $referencia = null;

        if ($valorFipe !== null && $valorFipe > 0) {
            $referencia = [
                'baixo' => round($valorFipe * config('seguro.percentual_baixo') / 100, 2),
                'medio' => round($valorFipe * config('seguro.percentual_medio') / 100, 2),
                'alto' => round($valorFipe * config('seguro.percentual_alto') / 100, 2),
            ];
        }

        // A apólice atual é a de vigência mais longa (a mais recente cadastrada, se empatar).
        $apolice = $seguros
            ->where('tipo', 'apolice')
            ->sortByDesc(fn (array $s) => ($s['vigencia_fim'] ?? '0000-00-00') . str_pad((string) $s['id'], 10, '0', STR_PAD_LEFT))
            ->first();

        $propostas = $seguros->where('tipo', 'proposta')->sortBy('valor_anual')->values();
        $melhor = $propostas->first();

        $itens = $seguros->map(function (array $s) use ($referencia, $apolice) {
            $acimaDaReferencia = $referencia
                ? round(($s['valor_anual'] / $referencia['medio'] - 1) * 100, 1)
                : null;

            return [
                'id' => $s['id'],
                'valor_mensal' => round($s['valor_anual'] / 12, 2),
                'vs_referencia_pct' => $acimaDaReferencia,
                // Só nas propostas: quanto sobra (ou falta) em relação ao que paga hoje.
                'economia_vs_apolice' => $s['tipo'] === 'proposta' && $apolice
                    ? round($apolice['valor_anual'] - $s['valor_anual'], 2)
                    : null,
            ];
        })->values()->all();

        return [
            'referencia' => $referencia,
            'apolice_atual_id' => $apolice['id'] ?? null,
            'melhor_proposta_id' => $melhor['id'] ?? null,
            'itens' => $itens,
        ];
    }
}
