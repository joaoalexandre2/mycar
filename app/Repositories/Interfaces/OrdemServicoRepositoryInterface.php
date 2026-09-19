<?php

namespace App\Repositories\Interfaces;

use App\Models\OrdemServico;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface OrdemServicoRepositoryInterface
{
    public function criar(array $dados): OrdemServico;

    public function listar(array $filtros = []): Collection;

    public function paginar(array $filtros, int $porPagina): LengthAwarePaginator;

    public function resumo(): array;

    public function buscarPorId(int $id): ?OrdemServico;

    public function atualizar(int $id, array $dados): ?OrdemServico;

    public function deletar(int $id): bool;
}
