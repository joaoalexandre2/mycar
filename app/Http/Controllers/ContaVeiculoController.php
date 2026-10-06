<?php

namespace App\Http\Controllers;

use App\Models\VeiculoConta;
use App\Services\FipeIndisponivelException;
use App\Services\FipeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Veículos da conta do usuário (perfis pessoa e frota). Sempre isolados pela
 * conta autenticada, via PertenceAConta.
 */
class ContaVeiculoController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->aplicarBusca(VeiculoConta::query(), $request->query('busca'));

        if ($request->boolean('all')) {
            return response()->json($query->orderBy('marca')->orderBy('modelo')->get());
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
                'total' => VeiculoConta::count(),
                'valorTotalFipe' => (float) VeiculoConta::sum('fipe_valor'),
            ],
        ]);
    }

    public function store(Request $request, FipeService $fipe)
    {
        $veiculo = VeiculoConta::create($this->validar($request));

        $this->preencherValorFipe($veiculo, $fipe);

        return response()->json($veiculo->fresh(), 201);
    }

    public function show($id)
    {
        $veiculo = VeiculoConta::find($id);

        if (!$veiculo) {
            return $this->naoEncontrado();
        }

        return response()->json($veiculo);
    }

    public function update(Request $request, $id, FipeService $fipe)
    {
        $veiculo = VeiculoConta::find($id);

        if (!$veiculo) {
            return $this->naoEncontrado();
        }

        $veiculo->update($this->validar($request, (int) $id));

        $codigosMudaram = $veiculo->wasChanged(['fipe_marca_id', 'fipe_modelo_id', 'fipe_ano']);

        if ($codigosMudaram || $veiculo->fipe_valor === null) {
            $this->preencherValorFipe($veiculo, $fipe);
        }

        return response()->json($veiculo->fresh());
    }

    public function destroy($id)
    {
        $veiculo = VeiculoConta::find($id);

        if (!$veiculo) {
            return $this->naoEncontrado();
        }

        $veiculo->delete();

        return response()->json(['message' => 'Veículo removido com sucesso.']);
    }

    public function consultarFipe($id, FipeService $fipe)
    {
        $veiculo = VeiculoConta::find($id);

        if (!$veiculo) {
            return $this->naoEncontrado();
        }

        if (!$veiculo->fipe_marca_id || !$veiculo->fipe_modelo_id || !$veiculo->fipe_ano) {
            return response()->json(['message' => 'Veículo sem código FIPE (marca, modelo e ano) cadastrado.'], 422);
        }

        if (!$this->preencherValorFipe($veiculo, $fipe)) {
            return response()->json(['message' => 'Não foi possível consultar a tabela FIPE no momento.'], 502);
        }

        return response()->json($veiculo->fresh());
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request, ?int $ignorarId = null): array
    {
        return $request->validate([
            'apelido' => ['nullable', 'string', 'max:60'],
            'placa' => [
                'required',
                'string',
                'max:10',
                // Única dentro da conta: a mesma placa pode existir em outra conta.
                Rule::unique('veiculos_conta', 'placa')
                    ->where('conta_id', app('conta.atual'))
                    ->ignore($ignorarId),
            ],
            'marca' => ['required', 'string', 'max:100'],
            'modelo' => ['required', 'string', 'max:100'],
            'ano' => ['required', 'integer', 'min:1900', 'max:' . date('Y')],
            'revisao_prevista_em' => ['nullable', 'date'],
            'uf' => ['nullable', 'string', 'size:2', Rule::in(array_keys(config('tributos.estados')))],
            'fipe_marca_id' => ['nullable', 'integer'],
            'fipe_modelo_id' => ['nullable', 'integer'],
            'fipe_ano' => ['nullable', 'string', 'max:10'],
        ]);
    }

    /**
     * Busca o valor FIPE quando o veículo tem os três códigos. Se a FIPE
     * estiver fora do ar, o veículo continua salvo e o valor fica em branco
     * (dá para consultar de novo depois). Devolve se conseguiu atualizar.
     */
    private function preencherValorFipe(VeiculoConta $veiculo, FipeService $fipe): bool
    {
        if (!$veiculo->fipe_marca_id || !$veiculo->fipe_modelo_id || !$veiculo->fipe_ano) {
            return false;
        }

        try {
            $resultado = $fipe->valor('carros', $veiculo->fipe_marca_id, $veiculo->fipe_modelo_id, $veiculo->fipe_ano);
        } catch (FipeIndisponivelException) {
            return false;
        }

        $veiculo->update([
            'fipe_valor' => FipeService::valorParaDecimal($resultado['Valor'] ?? ''),
            'fipe_consultado_em' => now(),
        ]);

        return true;
    }

    private function aplicarBusca(Builder $query, ?string $busca): Builder
    {
        $busca = trim((string) $busca);

        if ($busca === '') {
            return $query;
        }

        return $query->where(function (Builder $query) use ($busca) {
            $query->where('placa', 'like', "%{$busca}%")
                ->orWhere('apelido', 'like', "%{$busca}%")
                ->orWhere('marca', 'like', "%{$busca}%")
                ->orWhere('modelo', 'like', "%{$busca}%");
        });
    }

    private function naoEncontrado()
    {
        return response()->json(['message' => 'Veículo não encontrado.'], 404);
    }
}
