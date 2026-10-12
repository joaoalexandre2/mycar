<?php

namespace App\Repositories;

use App\Models\Veiculo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class VeiculoRepository
{
    public function create(array $dados): Veiculo
    {
        return Veiculo::create($dados);
    }

    public function listar(array $filtros = []): Collection
    {
        return $this->aplicarFiltros(Veiculo::with('cliente'), $filtros)->get();
    }

    public function paginar(array $filtros, int $porPagina): LengthAwarePaginator
    {
        return $this->aplicarFiltros(Veiculo::with('cliente'), $filtros)
            ->orderBy('marca')
            ->orderBy('modelo')
            ->paginate($porPagina);
    }

    /**
     * Totais gerais, sempre sobre a tabela inteira
     * (independentes da busca).
     */
    public function resumo(): array
    {
        return [
            'total' => Veiculo::count(),
            'clientesComVeiculo' => Veiculo::distinct('cliente_id')->count('cliente_id'),
            'marcas' => Veiculo::distinct('marca')->count('marca'),
            'anoMedio' => (float) Veiculo::avg('ano'),
        ];
    }

    public function buscarPorId(int $id): ?Veiculo
    {
        return Veiculo::with('cliente')->find($id);
    }

    /** Sem relações carregadas: para quem só precisa alterar o veículo. */
    public function encontrar(int $id): ?Veiculo
    {
        return Veiculo::find($id);
    }

    public function buscarComHistorico(int $id): ?Veiculo
    {
        return Veiculo::with(['cliente', 'ordensServico', 'manutencoes', 'historicoFipe'])->find($id);
    }

    public function atualizar(int $id, array $dados): ?Veiculo
    {
        $veiculo = Veiculo::find($id);

        if (!$veiculo) {
            return null;
        }

        $veiculo->update($dados);

        return $veiculo;
    }

    public function deletar(int $id): bool
    {
        $veiculo = Veiculo::find($id);

        if (!$veiculo) {
            return false;
        }

        return (bool) $veiculo->delete();
    }

    private function aplicarFiltros(Builder $query, array $filtros): Builder
    {
        $busca = trim((string) ($filtros['busca'] ?? ''));

        if ($busca === '') {
            return $query;
        }

        return $query->where(function (Builder $query) use ($busca) {
            $query->where('placa', 'like', "%{$busca}%")
                ->orWhere('marca', 'like', "%{$busca}%")
                ->orWhere('modelo', 'like', "%{$busca}%")
                ->orWhereHas('cliente', function (Builder $query) use ($busca) {
                    $query->where('nome', 'like', "%{$busca}%");
                });
        });
    }
}
