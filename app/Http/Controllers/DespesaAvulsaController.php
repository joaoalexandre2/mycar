<?php

namespace App\Http\Controllers;

use App\Models\CategoriaDespesa;
use App\Models\Despesa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Despesas avulsas (pedágio, estacionamento, lavagem...) e as categorias que as classificam.
 * Só administrador e gestor mexem aqui; isolado pela conta via PertenceAConta.
 */
class DespesaAvulsaController extends Controller
{
    public function index(Request $request)
    {
        $filtros = $request->validate([
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date', 'after_or_equal:de'],
            'veiculo_id' => ['nullable', 'integer'],
            'categoria_id' => ['nullable', 'integer'],
            'motorista_id' => ['nullable', 'integer'],
            'centro_custo_id' => ['nullable', 'integer'],
        ]);

        $paginador = Despesa::query()
            ->with(['categoria', 'veiculo', 'motorista', 'centroCusto'])
            ->when(!empty($filtros['de']), fn ($q) => $q->whereDate('data', '>=', $filtros['de']))
            ->when(!empty($filtros['ate']), fn ($q) => $q->whereDate('data', '<=', $filtros['ate']))
            ->when(!empty($filtros['veiculo_id']), fn ($q) => $q->where('veiculo_conta_id', $filtros['veiculo_id']))
            ->when(!empty($filtros['categoria_id']), fn ($q) => $q->where('categoria_id', $filtros['categoria_id']))
            ->when(!empty($filtros['motorista_id']), fn ($q) => $q->where('motorista_id', $filtros['motorista_id']))
            ->when(!empty($filtros['centro_custo_id']), fn ($q) => $q->where('centro_custo_id', $filtros['centro_custo_id']))
            ->orderByDesc('data')
            ->orderByDesc('id')
            ->paginate(25);

        return response()->json([
            'data' => collect($paginador->items())->map(fn (Despesa $d) => $this->formatar($d))->values(),
            'meta' => [
                'current_page' => $paginador->currentPage(),
                'last_page' => $paginador->lastPage(),
                'per_page' => $paginador->perPage(),
                'total' => $paginador->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        CategoriaDespesa::garantirPadroes();

        $despesa = Despesa::create($this->validar($request));

        return response()->json($this->formatar($despesa->load(['categoria', 'veiculo', 'motorista', 'centroCusto'])), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $despesa = Despesa::find($id);

        if (!$despesa) {
            return response()->json(['message' => 'Despesa não encontrada.'], 404);
        }

        CategoriaDespesa::garantirPadroes();

        $despesa->update($this->validar($request));

        return response()->json($this->formatar($despesa->load(['categoria', 'veiculo', 'motorista', 'centroCusto'])));
    }

    public function destroy(int $id): JsonResponse
    {
        $despesa = Despesa::find($id);

        if (!$despesa) {
            return response()->json(['message' => 'Despesa não encontrada.'], 404);
        }

        $despesa->delete();

        return response()->json(null, 204);
    }

    public function categorias(): JsonResponse
    {
        CategoriaDespesa::garantirPadroes();

        return response()->json(
            CategoriaDespesa::query()->orderBy('nome')->get()->map(fn (CategoriaDespesa $c) => $this->formatarCategoria($c))->values()
        );
    }

    public function criarCategoria(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:60', Rule::unique('categorias_despesa', 'nome')->where('conta_id', app('conta.atual'))],
        ]);

        $categoria = CategoriaDespesa::create(['nome' => trim($dados['nome']), 'ativo' => true]);

        return response()->json($this->formatarCategoria($categoria), 201);
    }

    public function atualizarCategoria(Request $request, int $id): JsonResponse
    {
        $categoria = CategoriaDespesa::find($id);

        if (!$categoria) {
            return response()->json(['message' => 'Categoria não encontrada.'], 404);
        }

        $dados = $request->validate([
            'nome' => ['sometimes', 'required', 'string', 'max:60', Rule::unique('categorias_despesa', 'nome')->where('conta_id', app('conta.atual'))->ignore($categoria->id)],
            'ativo' => ['sometimes', 'boolean'],
        ]);

        if (isset($dados['nome'])) {
            $dados['nome'] = trim($dados['nome']);
        }

        $categoria->update($dados);

        return response()->json($this->formatarCategoria($categoria));
    }

    private function validar(Request $request): array
    {
        $conta = app('conta.atual');

        return $request->validate([
            'categoria_id' => ['required', 'integer', Rule::exists('categorias_despesa', 'id')->where('conta_id', $conta)->where('ativo', true)],
            'veiculo_conta_id' => ['nullable', 'integer', Rule::exists('veiculos_conta', 'id')->where('conta_id', $conta)],
            'motorista_id' => ['nullable', 'integer', Rule::exists('motoristas', 'id')->where('conta_id', $conta)],
            'centro_custo_id' => ['nullable', 'integer', Rule::exists('centros_custo', 'id')->where('conta_id', $conta)],
            'data' => ['required', 'date'],
            'valor' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'descricao' => ['nullable', 'string', 'max:160'],
            'comprovante' => ['nullable', 'string', 'max:80'],
        ]);
    }

    private function formatar(Despesa $d): array
    {
        return [
            'id' => $d->id,
            'data' => $d->data?->toDateString(),
            'valor' => (float) $d->valor,
            'descricao' => $d->descricao,
            'comprovante' => $d->comprovante,
            'categoria' => $d->categoria ? ['id' => $d->categoria->id, 'nome' => $d->categoria->nome] : null,
            'veiculo' => $d->veiculo ? ['id' => $d->veiculo->id, 'placa' => $d->veiculo->placa, 'nome' => trim($d->veiculo->apelido ?: "{$d->veiculo->marca} {$d->veiculo->modelo}")] : null,
            'motorista' => $d->motorista ? ['id' => $d->motorista->id, 'nome' => $d->motorista->nome] : null,
            'centro_custo' => $d->centroCusto ? ['id' => $d->centroCusto->id, 'nome' => $d->centroCusto->nome] : null,
        ];
    }

    private function formatarCategoria(CategoriaDespesa $c): array
    {
        return ['id' => $c->id, 'nome' => $c->nome, 'ativo' => (bool) $c->ativo];
    }
}
