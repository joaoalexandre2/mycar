<?php

namespace App\Http\Controllers;

use App\Models\AtendimentoEstetica;

/** Contas a receber da estética: o que está em aberto, por forma de pagamento e há quantos dias. */
class FinanceiroEsteticaController extends Controller
{
    public function index()
    {
        $emAberto = AtendimentoEstetica::query()
            ->with('servicos')
            ->where('pago', false)
            ->orderBy('entrada_em')
            ->get()
            ->map(fn (AtendimentoEstetica $a) => [
                'id' => $a->id,
                'placa' => $a->placa,
                'cliente_nome' => $a->cliente_nome,
                'etapa' => $a->etapa,
                'valor' => $a->totalDosServicos(),
                'forma_pagamento' => $a->forma_pagamento,
                'dias_em_aberto' => $a->diasEmAberto(),
            ])
            ->values();

        $porForma = $emAberto->groupBy(fn (array $c) => $c['forma_pagamento'] ?? 'a_definir')
            ->map(fn ($grupo) => ['quantidade' => $grupo->count(), 'valor' => round((float) $grupo->sum('valor'), 2)]);

        return response()->json([
            'total_em_aberto' => round((float) $emAberto->sum('valor'), 2),
            'quantidade_em_aberto' => $emAberto->count(),
            'por_forma' => $porForma,
            'contas' => $emAberto,
        ]);
    }
}
