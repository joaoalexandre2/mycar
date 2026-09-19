<?php

namespace App\Services;

use App\Models\Cliente;
use App\Repositories\ClienteRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ClienteService
{
    private ClienteRepository $clienteRepository;

    public function __construct(ClienteRepository $clienteRepository)
    {
        $this->clienteRepository = $clienteRepository;
    }

    public function create(array $dados): Cliente
    {
        return $this->clienteRepository->create($dados);
    }

    public function listar(array $filtros = []): Collection
    {
        return $this->clienteRepository->listar($filtros);
    }

    public function paginar(array $filtros, int $porPagina): LengthAwarePaginator
    {
        return $this->clienteRepository->paginar($filtros, $porPagina);
    }

    public function resumo(): array
    {
        return $this->clienteRepository->resumo();
    }

    public function buscarPorId(int $id)
    {
        return $this->clienteRepository->buscarPorId($id);
    }

    public function atualizar(int $id, array $dados)
    {
        return $this->clienteRepository->atualizar($id, $dados);
    }

    public function remover(int $id)
    {
        return $this->clienteRepository->deletar($id);
    }
}
