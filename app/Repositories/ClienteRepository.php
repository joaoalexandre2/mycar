<?php

namespace App\Repositories;

use App\Models\Cliente;
use App\Models\Veiculo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ClienteRepository
{
    public function create(array $dados): Cliente
    {
        return Cliente::create($dados);
    }

    public function listar(array $filtros = []): Collection
    {
        return $this->aplicarFiltros(
            Cliente::withCount('veiculos'),
            $filtros
        )->get();
    }

    public function paginar(array $filtros, int $porPagina): LengthAwarePaginator
    {
        return $this->aplicarFiltros(
            Cliente::withCount('veiculos'),
            $filtros
        )->orderBy('nome')->paginate($porPagina);
    }

    /**
     * Totais gerais, sempre sobre a tabela inteira
     * (independentes de busca/filtro de status).
     */
    public function resumo(): array
    {
        return [
            'total' => Cliente::count(),
            'ativos' => Cliente::where('ativo', true)->count(),
            'inativos' => Cliente::where('ativo', false)->count(),
            'totalVeiculos' => Veiculo::count(),
        ];
    }

    public function buscarPorId(int $id): ?Cliente
    {
        return Cliente::find($id);
    }

    public function atualizar(int $id, array $dados): ?Cliente
    {
        $cliente = Cliente::find($id);

        if (!$cliente) {
            return null;
        }

        $cliente->update($dados);

        return $cliente->fresh();
    }

    public function deletar(int $id): bool
    {
        $cliente = Cliente::find($id);

        if (!$cliente) {
            return false;
        }

        return (bool) $cliente->delete();
    }

    private function aplicarFiltros(Builder $query, array $filtros): Builder
    {
        $busca = trim((string) ($filtros['busca'] ?? ''));

        if ($busca !== '') {
            $buscaDigitos = preg_replace('/\D/', '', $busca);

            $query->where(function (Builder $query) use ($busca, $buscaDigitos) {
                $query->where('nome', 'like', "%{$busca}%")
                    ->orWhere('telefone', 'like', "%{$busca}%");

                if ($buscaDigitos !== '') {
                    $query->orWhere('cpf', 'like', "%{$buscaDigitos}%");
                }
            });
        }

        $status = $filtros['status'] ?? 'todos';

        if ($status === 'ativos') {
            $query->where('ativo', true);
        } elseif ($status === 'inativos') {
            $query->where('ativo', false);
        }

        return $query;
    }
}
