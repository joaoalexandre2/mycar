<?php

namespace App\Repositories;

use App\Models\Veiculo;
use Illuminate\Database\Eloquent\Collection;

class VeiculoRepository
{
    public function create(array $dados): Veiculo
    {
        return Veiculo::create($dados);
    }

    public function listar(): Collection
    {
        return Veiculo::with('cliente')->get();
    }

    public function buscarPorId(int $id): ?Veiculo
    {
        return Veiculo::with('cliente')->find($id);
    }

    public function atualizar(int $id, array $dados): ?Veiculo
    {
        $veiculo = Veiculo::find($id);

        if (!$veiculo) {
            return null;
        }

        $veiculo->update($dados);

        return $veiculo;
    }

    public function deletar(int $id): bool
    {
        $veiculo = Veiculo::find($id);

        if (!$veiculo) {
            return false;
        }

        return $veiculo->delete();
    }
}