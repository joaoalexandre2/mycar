<?php

namespace App\Services;

use App\Models\Cliente;
use App\Repositories\ClienteRepository;

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

    public function listar()
    {
        return $this->clienteRepository->listar();
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