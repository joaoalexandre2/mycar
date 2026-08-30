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

    public function index()
    {
        $clientes = $this->clienteService->listar();

        return response()->json($clientes, 200);
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

//    public function update(UpdateClienteRequest $request, int $id)
// {
//     dd([
//         'id' => $id,
//         'all' => $request->all(),
//         'json' => $request->json()->all(),
//         'content' => $request->getContent(),
//         'headers' => $request->headers->all(),
//     ]);
// }

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
