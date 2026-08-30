<?php

namespace App\Repositories;

use App\Models\Manutencao;
use App\Repositories\Interfaces\ManutencaoRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ManutencaoRepository implements ManutencaoRepositoryInterface
{
    public function criar(array $dados): Manutencao
    {
        return Manutencao::create($dados);
    }

    public function listarPorVeiculo(int $veiculoId): Collection
    {
        return Manutencao::where('veiculo_id', $veiculoId)
            ->orderByDesc('data_manutencao')
            ->get();
    }

    public function buscarPorId(int $id): ?Manutencao
    {
        return Manutencao::find($id);
    }

    public function atualizar(int $id, array $dados): Manutencao
    {
        $manutencao = Manutencao::findOrFail($id);

        $manutencao->update($dados);

        return $manutencao->fresh();
    }

    public function remover(int $id): bool
    {
        $manutencao = Manutencao::findOrFail($id);

        return $manutencao->delete();
    }
}