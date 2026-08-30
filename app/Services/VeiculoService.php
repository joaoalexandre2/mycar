<?php

namespace App\Services;

use App\Models\Veiculo;

class VeiculoService
{
    public function create(array $dados): Veiculo
    {
        return Veiculo::create($dados);
    }

    public function listar()
    {
        return Veiculo::with('cliente')->get();
    }

    public function buscarPorId($id)
    {
        return Veiculo::with('cliente')->find($id);
    }

    public function atualizar($id, array $dados)
    {
        $veiculo = Veiculo::find($id);

        if (!$veiculo) {
            return null;
        }

        $veiculo->update($dados);

        return $veiculo;
    }

    public function remover($id)
    {
        $veiculo = Veiculo::find($id);

        if (!$veiculo) {
            return false;
        }

        return $veiculo->delete();
    }
}