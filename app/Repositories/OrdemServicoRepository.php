<?php

namespace App\Repositories;

use App\Models\OrdemServico;
use App\Repositories\Interfaces\OrdemServicoRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class OrdemServicoRepository implements OrdemServicoRepositoryInterface
{
    public function criar(array $dados): OrdemServico
    {
        return OrdemServico::create($dados);
    }

    public function listar(array $filtros = []): Collection
    {
        return $this->aplicarFiltros(
            OrdemServico::with('veiculo.cliente'),
            $filtros
        )->get();
    }

    public function paginar(array $filtros, int $porPagina): LengthAwarePaginator
    {
        return $this->aplicarFiltros(
            OrdemServico::with('veiculo.cliente'),
            $filtros
        )->orderByDesc('data_abertura')->paginate($porPagina);
    }

    /**
     * Totais gerais, sempre sobre a tabela inteira
     * (independentes de busca/filtro de status).
     */
    public function resumo(): array
    {
        return [
            'total' => OrdemServico::count(),
            'abertas' => OrdemServico::where('status', 'aberta')->count(),
            'emAndamento' => OrdemServico::whereIn('status', [
                'em_andamento',
                'aguardando_peca',
            ])->count(),
            'finalizadas' => OrdemServico::where('status', 'finalizada')->count(),
            'valorTotal' => (float) OrdemServico::sum('valor'),
        ];
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

    private function aplicarFiltros(Builder $query, array $filtros): Builder
    {
        $busca = trim((string) ($filtros['busca'] ?? ''));

        if ($busca !== '') {
            $query->where(function (Builder $query) use ($busca) {
                $query->where('descricao', 'like', "%{$busca}%")
                    ->orWhereHas('veiculo', function (Builder $query) use ($busca) {
                        $query->where('placa', 'like', "%{$busca}%")
                            ->orWhere('modelo', 'like', "%{$busca}%")
                            ->orWhereHas('cliente', function (Builder $query) use ($busca) {
                                $query->where('nome', 'like', "%{$busca}%");
                            });
                    });
            });
        }

        $status = $filtros['status'] ?? 'todos';

        if ($status !== 'todos' && $status !== null) {
            $query->where('status', $status);
        }

        return $query;
    }
}
