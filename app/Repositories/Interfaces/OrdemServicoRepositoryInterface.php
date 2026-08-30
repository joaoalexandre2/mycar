<?php

namespace App\Repositories\Interfaces;

use App\Models\OrdemServico;
use Illuminate\Database\Eloquent\Collection;

interface OrdemServicoRepositoryInterface
{
    public function criar(array $dados): OrdemServico;

    public function listar(): Collection;

    public function buscarPorId(int $id): ?OrdemServico;

    public function atualizar(int $id, array $dados): ?OrdemServico;

    public function deletar(int $id): bool;
}