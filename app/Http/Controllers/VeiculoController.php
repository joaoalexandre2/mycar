<?php

namespace App\Http\Controllers;

use App\Models\Veiculo;
use App\Services\FipeIndisponivelException;
use App\Services\FipeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class VeiculoController extends Controller
{
    public function store(Request $request, FipeService $fipe)
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
            'uf' => ['nullable', 'string', 'size:2', 'in:' . implode(',', array_keys(config('tributos.estados')))],
        'fipe_marca_id' => ['nullable', 'integer'],
            'fipe_modelo_id' => ['nullable', 'integer'],
            'fipe_ano' => ['nullable', 'string', 'max:10'],
        ]);

        $veiculo = Veiculo::create($dados);

        $this->preencherValorFipe($veiculo, $fipe);

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

public function update(Request $request, $id, FipeService $fipe)
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
        'uf' => ['nullable', 'string', 'size:2', 'in:' . implode(',', array_keys(config('tributos.estados')))],
        'fipe_marca_id' => ['nullable', 'integer'],
        'fipe_modelo_id' => ['nullable', 'integer'],
        'fipe_ano' => ['nullable', 'string', 'max:10'],
    ]);

    $veiculo->update($dados);

    $codigosMudaram = $veiculo->wasChanged(['fipe_marca_id', 'fipe_modelo_id', 'fipe_ano']);

    if ($codigosMudaram || $veiculo->fipe_valor === null) {
        $this->preencherValorFipe($veiculo, $fipe);
    }

    return response()->json($veiculo);
}

public function consultarFipe($id, FipeService $fipe)
{
    $veiculo = Veiculo::find($id);

    if (!$veiculo) {
        return response()->json([
            'message' => 'Veículo não encontrado.'
        ], 404);
    }

    if (!$veiculo->fipe_marca_id || !$veiculo->fipe_modelo_id || !$veiculo->fipe_ano) {
        return response()->json([
            'message' => 'Veículo sem código FIPE (marca, modelo e ano) cadastrado.'
        ], 422);
    }

    try {
        $resultado = $fipe->valor(
            'carros',
            $veiculo->fipe_marca_id,
            $veiculo->fipe_modelo_id,
            $veiculo->fipe_ano
        );
    } catch (FipeIndisponivelException) {
        return response()->json([
            'message' => 'Não foi possível consultar a tabela FIPE no momento.'
        ], 502);
    }

    $veiculo->update([
        'fipe_valor' => $this->valorParaDecimal($resultado['Valor'] ?? ''),
        'fipe_consultado_em' => now(),
    ]);

    return response()->json($veiculo);
}

    /**
     * Busca o valor FIPE quando o veículo tem os três códigos. Se a FIPE
     * estiver fora do ar, o veículo continua salvo e o valor fica em branco
     * (pode ser consultado depois pelo botão de atualizar).
     */
    private function preencherValorFipe(Veiculo $veiculo, FipeService $fipe): void
    {
        if (!$veiculo->fipe_marca_id || !$veiculo->fipe_modelo_id || !$veiculo->fipe_ano) {
            return;
        }

        try {
            $resultado = $fipe->valor(
                'carros',
                $veiculo->fipe_marca_id,
                $veiculo->fipe_modelo_id,
                $veiculo->fipe_ano
            );
        } catch (FipeIndisponivelException) {
            return;
        }

        $veiculo->update([
            'fipe_valor' => $this->valorParaDecimal($resultado['Valor'] ?? ''),
            'fipe_consultado_em' => now(),
        ]);
    }

    private function valorParaDecimal(string $valor): float
    {
        return (float) str_replace(['.', ','], ['', '.'], preg_replace('/[^\d.,]/', '', $valor));
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
