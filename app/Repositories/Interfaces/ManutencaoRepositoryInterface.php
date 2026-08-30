<?php

namespace App\Repositories\Interfaces;

use App\Models\Manutencao;
use Illuminate\Database\Eloquent\Collection;

interface ManutencaoRepositoryInterface
{
    public function criar(array $dados): Manutencao;

    public function listarPorVeiculo(int $veiculoId): Collection;

    public function buscarPorId(int $id): ?Manutencao;

    public function atualizar(int $id, array $dados): Manutencao;

    public function remover(int $id): bool;
}