<?php

namespace App\Http\Controllers;

use App\Models\AtendimentoEstetica;

/** Histórico de um carro pela placa: o que foi feito, quanto custou e quando deve voltar. */
class HistoricoEsteticaController extends Controller
{
    /** Dias de intervalo para sugerir o retorno do carro depois da última entrega. */
    public const DIAS_ATE_O_RETORNO = 90;

    public function show(string $placa)
    {
        $normalizada = AtendimentoEstetica::normalizarPlaca($placa);

        $atendimentos = AtendimentoEstetica::query()
            ->with('servicos')
            ->where('placa', $normalizada)
            ->orderByDesc('entrada_em')
            ->limit(200)
            ->get();

        $ultimaEntrega = $atendimentos->firstWhere('etapa', AtendimentoEstetica::ENTREGUE)?->entregue_em;

        return response()->json([
            'placa' => $normalizada,
            'modelo' => $atendimentos->first()?->modelo,
            'total_gasto' => round((float) $atendimentos->sum(fn (AtendimentoEstetica $a) => $a->totalDosServicos()), 2),
            'ultima_entrega' => $ultimaEntrega?->toIso8601String(),
            'retorno_previsto' => $ultimaEntrega ? $ultimaEntrega->copy()->addDays(self::DIAS_ATE_O_RETORNO)->toDateString() : null,
            'atendimentos' => $atendimentos->map(fn (AtendimentoEstetica $a) => [
                'id' => $a->id,
                'entrada_em' => $a->entrada_em?->toIso8601String(),
                'etapa' => $a->etapa,
                'servicos' => $a->servicos->pluck('nome')->values(),
                'total' => $a->totalDosServicos(),
                'pago' => (bool) $a->pago,
            ])->values(),
        ]);
    }
}
