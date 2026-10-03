<?php

namespace App\Http\Controllers;

use App\Models\Veiculo;
use App\Models\VeiculoPeca;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VeiculoPecaController extends Controller
{
    public function index($id)
    {
        $veiculo = Veiculo::find($id);

        if (!$veiculo) {
            return response()->json(['message' => 'Veículo não encontrado.'], 404);
        }

        return response()->json([
            'tipos' => VeiculoPeca::TIPOS,
            'pecas' => $veiculo->pecas()
                ->orderByDesc('usado_em')
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function store(Request $request, $id)
    {
        $veiculo = Veiculo::find($id);

        if (!$veiculo) {
            return response()->json(['message' => 'Veículo não encontrado.'], 404);
        }

        $dados = $request->validate([
            'tipo' => ['required', Rule::in(array_keys(VeiculoPeca::TIPOS))],
            'especificacao' => ['required', 'string', 'max:120'],
            'marca' => ['nullable', 'string', 'max:60'],
            'fonte' => ['required', Rule::in(VeiculoPeca::FONTES)],
            'usado_em' => ['nullable', 'date', 'before_or_equal:today'],
            'observacao' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json($veiculo->pecas()->create($dados), 201);
    }

    public function destroy($id, $pecaId)
    {
        $peca = VeiculoPeca::where('veiculo_id', $id)->find($pecaId);

        if (!$peca) {
            return response()->json(['message' => 'Peça não encontrada.'], 404);
        }

        $peca->delete();

        return response()->json(['message' => 'Peça removida com sucesso.']);
    }
}
