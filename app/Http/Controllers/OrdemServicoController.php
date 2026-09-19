<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrdemServicoRequest;
use App\Http\Requests\UpdateOrdemServicoRequest;
use App\Services\OrdemServicoService;
use Illuminate\Http\Request;

class OrdemServicoController extends Controller
{
    private OrdemServicoService $ordemServicoService;

    public function __construct(
        OrdemServicoService $ordemServicoService
    ) {
        $this->ordemServicoService = $ordemServicoService;
    }

    public function store(StoreOrdemServicoRequest $request)
    {
        $ordemServico = $this->ordemServicoService->criar(
            $request->validated()
        );

        return response()->json($ordemServico, 201);
    }

    public function index(Request $request)
    {
        $filtros = [
            'busca' => $request->query('busca'),
            'status' => $request->query('status', 'todos'),
        ];

        if ($request->boolean('all')) {
            return response()->json(
                $this->ordemServicoService->listar($filtros),
                200
            );
        }

        $porPaginaSolicitada = (int) $request->query('per_page', 15);
        $porPagina = $porPaginaSolicitada > 0 ? min($porPaginaSolicitada, 100) : 15;
        $paginador = $this->ordemServicoService->paginar($filtros, $porPagina);

        return response()->json([
            'data' => $paginador->items(),
            'meta' => [
                'current_page' => $paginador->currentPage(),
                'last_page' => $paginador->lastPage(),
                'per_page' => $paginador->perPage(),
                'total' => $paginador->total(),
            ],
            'resumo' => $this->ordemServicoService->resumo(),
        ], 200);
    }

    public function show(int $id)
    {
        $ordemServico = $this->ordemServicoService->buscarPorId($id);

        if (!$ordemServico) {
            return response()->json([
                'message' => 'Ordem de serviço não encontrada.',
            ], 404);
        }

        return response()->json($ordemServico, 200);
    }

    public function update(
        UpdateOrdemServicoRequest $request,
        int $id
    ) {
        $ordemServico = $this->ordemServicoService->atualizar(
            $id,
            $request->validated()
        );

        if (!$ordemServico) {
            return response()->json([
                'message' => 'Ordem de serviço não encontrada.',
            ], 404);
        }

        return response()->json($ordemServico, 200);
    }

    public function destroy(int $id)
    {
        $removido = $this->ordemServicoService->remover($id);

        if (!$removido) {
            return response()->json([
                'message' => 'Ordem de serviço não encontrada.',
            ], 404);
        }

        return response()->json([
            'message' => 'Ordem de serviço removida com sucesso.',
        ], 200);
    }
}
