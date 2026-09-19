<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;

use App\Http\Requests\StoreClienteRequest;
use App\Http\Requests\UpdateClienteRequest;
use App\Services\ClienteService;

class ClienteController extends Controller
{
    private ClienteService $clienteService;

    public function __construct(ClienteService $clienteService)
    {
        $this->clienteService = $clienteService;
    }


    public function store(StoreClienteRequest $request)
    {
        $cliente = $this->clienteService->create(
            $request->validated()
        );

        return response()->json($cliente, 201);
    }

    public function index(Request $request)
    {
        $filtros = [
            'busca' => $request->query('busca'),
            'status' => $request->query('status', 'todos'),
        ];

        if ($request->boolean('all')) {
            return response()->json(
                $this->clienteService->listar($filtros),
                200
            );
        }

        $porPaginaSolicitada = (int) $request->query('per_page', 15);
        $porPagina = $porPaginaSolicitada > 0 ? min($porPaginaSolicitada, 100) : 15;
        $paginador = $this->clienteService->paginar($filtros, $porPagina);

        return response()->json([
            'data' => $paginador->items(),
            'meta' => [
                'current_page' => $paginador->currentPage(),
                'last_page' => $paginador->lastPage(),
                'per_page' => $paginador->perPage(),
                'total' => $paginador->total(),
            ],
            'resumo' => $this->clienteService->resumo(),
        ], 200);
    }

    public function show(int $id)
    {
        $cliente = $this->clienteService->buscarPorId($id);

        if (! $cliente) {
            return response()->json([
                'message' => 'Cliente não encontrado.',
            ], 404);
        }

        return response()->json($cliente, 200);
    }

public function update(UpdateClienteRequest $request, int $id)
{
    $cliente = $this->clienteService->atualizar(
        $id,
        $request->validated()
    );

    if (! $cliente) {
        return response()->json([
            'message' => 'Cliente não encontrado.',
        ], 404);
    }

    return response()->json($cliente, 200);
}

public function destroy(int $id)
{
    $cliente = $this->clienteService->remover($id);

    if (! $cliente) {
        return response()->json([
            'message' => 'Cliente não encontrado.',
        ], 404);
    }

    return response()->json([
        'message' => 'Cliente removido com sucesso.',
    ], 200);
}

}
