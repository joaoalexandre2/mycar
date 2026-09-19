<?php

namespace App\Http\Controllers;

use App\Repositories\Interfaces\ManutencaoRepositoryInterface;
use Illuminate\Http\Request;

class ManutencaoController extends Controller
{
    public function __construct(
        private ManutencaoRepositoryInterface $manutencaoRepository
    ) {}

    public function index(Request $request)
    {
        $filtros = [
            'busca' => $request->query('busca'),
            'status' => $request->query('status', 'todos'),
        ];

        if ($request->boolean('all')) {
            return response()->json(
                $this->manutencaoRepository->listar($filtros),
                200
            );
        }

        $porPaginaSolicitada = (int) $request->query('per_page', 15);
        $porPagina = $porPaginaSolicitada > 0 ? min($porPaginaSolicitada, 100) : 15;
        $paginador = $this->manutencaoRepository->paginar($filtros, $porPagina);

        return response()->json([
            'data' => $paginador->items(),
            'meta' => [
                'current_page' => $paginador->currentPage(),
                'last_page' => $paginador->lastPage(),
                'per_page' => $paginador->perPage(),
                'total' => $paginador->total(),
            ],
            'resumo' => $this->manutencaoRepository->resumo(),
        ], 200);
    }

    public function store(Request $request)
    {
        $dados = $this->validar($request);

        $manutencao = $this->manutencaoRepository->criar($dados);

        return response()->json($manutencao, 201);
    }

    public function show(int $id)
    {
        $manutencao = $this->manutencaoRepository->buscarPorId($id);

        if (!$manutencao) {
            return response()->json([
                'message' => 'Manutenção não encontrada.',
            ], 404);
        }

        return response()->json($manutencao, 200);
    }

    public function update(Request $request, int $id)
    {
        $dados = $this->validar($request);

        $manutencao = $this->manutencaoRepository->atualizar($id, $dados);

        if (!$manutencao) {
            return response()->json([
                'message' => 'Manutenção não encontrada.',
            ], 404);
        }

        return response()->json($manutencao, 200);
    }

    public function destroy(int $id)
    {
        $manutencao = $this->manutencaoRepository->buscarPorId($id);

        if (!$manutencao) {
            return response()->json([
                'message' => 'Manutenção não encontrada.',
            ], 404);
        }

        $this->manutencaoRepository->remover($id);

        return response()->json([
            'message' => 'Manutenção removida com sucesso.',
        ], 200);
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'veiculo_id' => [
                'required',
                'exists:veiculos,id',
            ],
            'tipo' => [
                'required',
                'string',
                'max:100',
            ],
            'descricao' => [
                'nullable',
                'string',
            ],
            'valor' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'data_manutencao' => [
                'required',
                'date',
            ],
            'quilometragem' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'proxima_data' => [
                'nullable',
                'date',
            ],
            'proxima_quilometragem' => [
                'nullable',
                'integer',
                'min:0',
            ],
        ]);
    }
}
