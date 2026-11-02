<?php

namespace App\Http\Controllers;

use App\Models\AcessoFrotaEvento;

/** Histórico de quem mudou acesso na frota (grupos, membros e papéis). Só o administrador lê. */
class AcessosFrotaController extends Controller
{
    private const LIMITE = 200;

    public function index()
    {
        $eventos = AcessoFrotaEvento::query()
            ->with('ator:id,name')
            ->orderByDesc('id')
            ->limit(self::LIMITE)
            ->get();

        return response()->json($eventos->map(fn (AcessoFrotaEvento $e) => [
            'id' => $e->id,
            'quando' => $e->created_at?->toIso8601String(),
            'ator' => $e->ator?->name,
            'alvo_tipo' => $e->alvo_tipo,
            'alvo_id' => $e->alvo_id,
            'acao' => $e->acao,
            'detalhes' => $e->detalhes,
        ])->values());
    }
}
