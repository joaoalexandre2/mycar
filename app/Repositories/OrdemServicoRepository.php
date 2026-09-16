<?php

namespace App\Repositories;

use App\Models\OrdemServico;
use App\Repositories\Interfaces\OrdemServicoRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class OrdemServicoRepository implements OrdemServicoRepositoryInterface
{
    public function criar(array $dados): OrdemServico
    {
        return OrdemServico::create($dados);
    }

    public function listar(): Collection
    {
        return OrdemServico::with('veiculo.cliente')->get();
    }

    public function buscarPorId(int $id): ?OrdemServico
    {
        return OrdemServico::with('veiculo.cliente')->find($id);
    }

    public function atualizar(int $id, array $dados): ?OrdemServico
    {
        $ordemServico = OrdemServico::find($id);

        if (!$ordemServico) {
            return null;
        }

        $ordemServico->update($dados);

        return $ordemServico->load('veiculo.cliente');
    }

    public function deletar(int $id): bool
    {
        $ordemServico = OrdemServico::find($id);

        if (!$ordemServico) {
            return false;
        }

        return $ordemServico->delete();
    }
}