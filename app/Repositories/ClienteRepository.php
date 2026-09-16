<?php

namespace App\Repositories;

use App\Models\Cliente;

class ClienteRepository
{
    public function create(array $dados): Cliente
    {
        return Cliente::create($dados);
    }


    public function listar(): \Illuminate\Database\Eloquent\Collection
    {
        return Cliente::withCount('veiculos')->get();
    }


    public function buscarPorId(int $id): ?Cliente
    {
        return Cliente::with('veiculos')->find($id);
    }


   public function atualizar(int $id, array $dados): ?Cliente
{
    $cliente = Cliente::find($id);

    if (!$cliente) {
        return null;
    }

    $cliente->update($dados);

    return $cliente;
}

    public function deletar(int $id): bool
    {
        $cliente = Cliente::find($id);

        if (!$cliente) {
            return false;
        }

        return $cliente->delete();
    }


}