<?php

namespace App\Http\Controllers;

use App\Models\CodigoPeca;
use App\Models\VeiculoConta;
use App\Services\CatalogoPecas;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * "Meu código": o código de peça que a pessoa confirmou para um veículo dela.
 * Isolado pela conta via PertenceAConta.
 */
class CodigoPecaController extends Controller
{
    public function store(Request $request, $id)
    {
        $veiculo = VeiculoConta::find($id);

        if (!$veiculo) {
            return response()->json(['message' => 'Veículo não encontrado.'], 404);
        }

        $dados = $request->validate([
            'peca_id' => ['required', 'string', Rule::in(CatalogoPecas::idsDasPecas())],
            'marca' => ['nullable', 'string', 'max:60'],
            'codigo' => ['required', 'string', 'max:60'],
            'observacoes' => ['nullable', 'string', 'max:255'],
        ], [
            'peca_id.in' => 'Peça não encontrada no catálogo.',
        ], [
            'codigo' => 'código',
        ]);

        $dados['codigo'] = Str::upper(trim($dados['codigo']));

        $codigo = $veiculo->codigosPecas()->create($dados);

        return response()->json($this->formatar($codigo), 201);
    }

    public function destroy($id, $codigoId)
    {
        $veiculo = VeiculoConta::find($id);

        if (!$veiculo) {
            return response()->json(['message' => 'Veículo não encontrado.'], 404);
        }

        $codigo = $veiculo->codigosPecas()->find($codigoId);

        if (!$codigo) {
            return response()->json(['message' => 'Código não encontrado.'], 404);
        }

        $codigo->delete();

        return response()->json(['message' => 'Código removido.']);
    }

    /** @return array<string, mixed> */
    public static function formatar(CodigoPeca $codigo): array
    {
        return [
            'id' => $codigo->id,
            'peca_id' => $codigo->peca_id,
            'marca' => $codigo->marca,
            'codigo' => $codigo->codigo,
            'observacoes' => $codigo->observacoes,
        ];
    }
}
