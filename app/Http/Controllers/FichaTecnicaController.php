<?php

namespace App\Http\Controllers;

use App\Models\FichaTecnica;
use App\Models\Veiculo;
use App\Services\Catalogo\CatalogoTecnicoService;
use Illuminate\Http\Request;

class FichaTecnicaController extends Controller
{
    public function show($id, CatalogoTecnicoService $catalogo)
    {
        $veiculo = Veiculo::find($id);

        if (!$veiculo) {
            return response()->json(['message' => 'Veículo não encontrado.'], 404);
        }

        return response()->json($catalogo->fichaPara($veiculo) ?? ['fonte' => null, 'dados' => null]);
    }

    public function update(Request $request, $id)
    {
        $veiculo = Veiculo::find($id);

        if (!$veiculo) {
            return response()->json(['message' => 'Veículo não encontrado.'], 404);
        }

        $dados = $request->validate([
            'oleo_viscosidade' => ['nullable', 'string', 'max:20'],
            'oleo_especificacao' => ['nullable', 'string', 'max:60'],
            'oleo_capacidade_litros' => ['nullable', 'numeric', 'min:0', 'max:99'],
            'filtro_oleo' => ['nullable', 'string', 'max:60'],
            'filtro_ar' => ['nullable', 'string', 'max:60'],
            'filtro_combustivel' => ['nullable', 'string', 'max:60'],
            'pneu_medida' => ['nullable', 'string', 'max:30'],
            'pneu_pressao_dianteira' => ['nullable', 'integer', 'min:0', 'max:100'],
            'pneu_pressao_traseira' => ['nullable', 'integer', 'min:0', 'max:100'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ]);

        $ficha = FichaTecnica::updateOrCreate(['veiculo_id' => $veiculo->id], $dados);

        return response()->json(['fonte' => 'manual', 'dados' => $ficha->only(FichaTecnica::CAMPOS)]);
    }
}
