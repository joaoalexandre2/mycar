<?php

namespace App\Repositories;

use App\Models\Manutencao;
use App\Repositories\Interfaces\ManutencaoRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ManutencaoRepository implements ManutencaoRepositoryInterface
{
    public function criar(array $dados): Manutencao
    {
        return Manutencao::create($dados)->load('veiculo.cliente');
    }

    public function listar(): Collection
    {
        return Manutencao::with('veiculo.cliente')
            ->orderByDesc('data_manutencao')
            ->get();
    }

    public function listarPorVeiculo(int $veiculoId): Collection
    {
        return Manutencao::with('veiculo.cliente')
            ->where('veiculo_id', $veiculoId)
            ->orderByDesc('data_manutencao')
            ->get();
    }

    public function buscarPorId(int $id): ?Manutencao
    {
        return Manutencao::with('veiculo.cliente')->find($id);
    }

    public function atualizar(int $id, array $dados): ?Manutencao
    {
        $manutencao = Manutencao::find($id);

        if (!$manutencao) {
            return null;
        }

        $manutencao->update($dados);

        return $manutencao->fresh('veiculo.cliente');
    }

    public function remover(int $id): bool
    {
        $manutencao = Manutencao::find($id);

        if (!$manutencao) {
            return false;
        }

        return (bool) $manutencao->delete();
    }
}
