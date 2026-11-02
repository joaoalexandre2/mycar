<?php

namespace App\Http\Controllers;

use App\Models\ChecklistUso;
use App\Models\UsoVeiculo;
use App\Models\UsoVeiculoEvento;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Checklist de saída e de devolução de uma reserva. O motorista só faz o da própria reserva;
 * administrador e gestor fazem o de qualquer uma.
 */
class ChecklistUsoController extends Controller
{
    public function index(Request $request, $id)
    {
        $uso = $this->usoDaPessoa($request, $id);

        if (!$uso) {
            return response()->json(['message' => 'Reserva não encontrada.'], 404);
        }

        return response()->json([
            'itens' => ChecklistUso::ITENS,
            'checklists' => ChecklistUso::where('uso_veiculo_id', $uso->id)->get()->map(fn (ChecklistUso $c) => $this->formatar($c))->values(),
        ]);
    }

    public function store(Request $request, $id)
    {
        $uso = $this->usoDaPessoa($request, $id);

        if (!$uso) {
            return response()->json(['message' => 'Reserva não encontrada.'], 404);
        }

        if ($uso->status === 'cancelado') {
            return response()->json(['message' => 'Uma reserva cancelada não tem checklist.'], 422);
        }

        $dados = $request->validate([
            'momento' => ['required', Rule::in(ChecklistUso::MOMENTOS)],
            'itens' => ['required', 'array'],
            'itens.*.item' => ['required', Rule::in(array_keys(ChecklistUso::ITENS))],
            'itens.*.ok' => ['required', 'boolean'],
            'observacoes' => ['nullable', 'string', 'max:1000'],
        ]);

        $respondidos = collect($dados['itens'])->pluck('item')->unique();

        if ($respondidos->count() !== count(ChecklistUso::ITENS)) {
            return response()->json(['message' => 'Responda todos os itens do checklist.'], 422);
        }

        if (ChecklistUso::where('uso_veiculo_id', $uso->id)->where('momento', $dados['momento'])->exists()) {
            return response()->json(['message' => 'Este checklist já foi feito.'], 422);
        }

        $itens = collect($dados['itens'])
            ->map(fn (array $i) => ['item' => $i['item'], 'ok' => (bool) $i['ok']])
            ->values()
            ->all();

        $checklist = ChecklistUso::create([
            'uso_veiculo_id' => $uso->id,
            'momento' => $dados['momento'],
            'itens' => $itens,
            'observacoes' => $dados['observacoes'] ?? null,
            'feito_por_user_id' => $request->user()->id,
        ]);

        UsoVeiculoEvento::registrar($uso, 'checklist_' . $dados['momento'], $request->user()->id, [
            'problemas' => $checklist->problemas(),
        ]);

        return response()->json($this->formatar($checklist), 201);
    }

    /** A reserva, se a pessoa pode vê-la: o motorista só as próprias. */
    private function usoDaPessoa(Request $request, $id): ?UsoVeiculo
    {
        $query = UsoVeiculo::query()->whereKey($id);

        if ($request->user()->papelNaConta() === 'motorista') {
            $query->where('motorista_id', $request->user()->motoristaVinculado()?->id ?? 0);
        }

        return $query->first();
    }

    /** @return array<string, mixed> */
    private function formatar(ChecklistUso $c): array
    {
        return [
            'id' => $c->id,
            'momento' => $c->momento,
            'itens' => $c->itens,
            'problemas' => $c->problemas(),
            'observacoes' => $c->observacoes,
            'quando' => $c->created_at?->toIso8601String(),
        ];
    }
}
