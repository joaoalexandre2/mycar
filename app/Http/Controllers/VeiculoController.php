<?php

namespace App\Http\Controllers;

use App\Models\Veiculo;
use Illuminate\Http\Request;

class VeiculoController extends Controller
{
    public function store(Request $request)
    {
        $dados = $request->validate([
            'cliente_id' => [
                'required',
                'exists:clientes,id',
            ],
            'placa' => [
                'required',
                'string',
                'max:10',
                'unique:veiculos,placa',
            ],
            'marca' => [
                'required',
                'string',
                'max:100',
            ],
            'modelo' => [
                'required',
                'string',
                'max:100',
            ],
            'ano' => [
                'required',
                'integer',
                'min:1900',
                'max:' . date('Y'),
            ],
        ]);

        $veiculo = Veiculo::create($dados);

        return response()->json($veiculo, 201);
    }

    public function index()
    {
        return response()->json(Veiculo::all());
    }

    public function show($id)
{
    $veiculo = Veiculo::with('cliente')->find($id);

    if (!$veiculo) {
        return response()->json([
            'message' => 'Veículo não encontrado.'
        ], 404);
    }

    return response()->json($veiculo);
}

public function update(Request $request, $id)
{
    $veiculo = Veiculo::find($id);

    if (!$veiculo) {
        return response()->json([
            'message' => 'Veículo não encontrado.'
        ], 404);
    }

    $dados = $request->validate([
        'cliente_id' => [
            'required',
            'exists:clientes,id',
        ],
        'placa' => [
            'required',
            'string',
            'max:10',
            'unique:veiculos,placa,' . $id,
        ],
        'marca' => [
            'required',
            'string',
            'max:100',
        ],
        'modelo' => [
            'required',
            'string',
            'max:100',
        ],
        'ano' => [
            'required',
            'integer',
            'min:1900',
            'max:' . date('Y'),
        ],
    ]);

    $veiculo->update($dados);

    return response()->json($veiculo);
}

public function destroy($id)
{
    $veiculo = Veiculo::find($id);

    if (!$veiculo) {
        return response()->json([
            'message' => 'Veículo não encontrado.'
        ], 404);
    }

    $veiculo->delete();

    return response()->json([
        'message' => 'Veículo removido com sucesso.'
    ]);
}
}