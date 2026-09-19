<?php

namespace App\Http\Controllers;

use App\Models\Veiculo;
use Illuminate\Database\Eloquent\Builder;
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

    public function index(Request $request)
    {
        $query = $this->aplicarBusca(
            Veiculo::with('cliente'),
            $request->query('busca')
        );

        if ($request->boolean('all')) {
            return response()->json($query->get(), 200);
        }

        $porPaginaSolicitada = (int) $request->query('per_page', 15);
        $porPagina = $porPaginaSolicitada > 0 ? min($porPaginaSolicitada, 100) : 15;
        $paginador = $query->orderBy('marca')->orderBy('modelo')->paginate($porPagina);

        return response()->json([
            'data' => $paginador->items(),
            'meta' => [
                'current_page' => $paginador->currentPage(),
                'last_page' => $paginador->lastPage(),
                'per_page' => $paginador->perPage(),
                'total' => $paginador->total(),
            ],
            'resumo' => [
                'total' => Veiculo::count(),
                'clientesComVeiculo' => Veiculo::distinct('cliente_id')->count('cliente_id'),
                'marcas' => Veiculo::distinct('marca')->count('marca'),
                'anoMedio' => (float) Veiculo::avg('ano'),
            ],
        ], 200);
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

    private function aplicarBusca(Builder $query, ?string $busca): Builder
    {
        $busca = trim((string) $busca);

        if ($busca === '') {
            return $query;
        }

        return $query->where(function (Builder $query) use ($busca) {
            $query->where('placa', 'like', "%{$busca}%")
                ->orWhere('marca', 'like', "%{$busca}%")
                ->orWhere('modelo', 'like', "%{$busca}%")
                ->orWhereHas('cliente', function (Builder $query) use ($busca) {
                    $query->where('nome', 'like', "%{$busca}%");
                });
        });
    }
}
