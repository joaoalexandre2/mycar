<?php

namespace App\Repositories;

use App\Models\Manutencao;
use App\Repositories\Interfaces\ManutencaoRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ManutencaoRepository implements ManutencaoRepositoryInterface
{
    public function criar(array $dados): Manutencao
    {
        return Manutencao::create($dados)->load('veiculo.cliente');
    }

    public function listar(array $filtros = []): Collection
    {
        return $this->aplicarFiltros(
            Manutencao::with('veiculo.cliente'),
            $filtros
        )->orderByDesc('data_manutencao')->get();
    }

    public function paginar(array $filtros, int $porPagina): LengthAwarePaginator
    {
        return $this->aplicarFiltros(
            Manutencao::with('veiculo.cliente'),
            $filtros
        )->orderByDesc('data_manutencao')->paginate($porPagina);
    }

    /**
     * Totais gerais, sempre sobre a tabela inteira
     * (independentes de busca/filtro de status).
     */
    public function resumo(): array
    {
        return [
            'total' => Manutencao::count(),
            'emDia' => $this->contarPorStatus('em_dia'),
            'proximas' => $this->contarPorStatus('proxima'),
            'atrasadas' => $this->contarPorStatus('atrasada'),
            'veiculosMonitorados' => Manutencao::distinct('veiculo_id')->count('veiculo_id'),
        ];
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

    private function aplicarFiltros(Builder $query, array $filtros): Builder
    {
        $busca = trim((string) ($filtros['busca'] ?? ''));

        if ($busca !== '') {
            $query->where(function (Builder $query) use ($busca) {
                $query->where('tipo', 'like', "%{$busca}%")
                    ->orWhere('descricao', 'like', "%{$busca}%")
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
            $this->aplicarStatus($query, $status);
        }

        return $query;
    }

    /**
     * Replica em SQL a mesma regra usada no frontend
     * (utils/manutencoes: statusPorData) para derivar o
     * status a partir de proxima_data, já que essa coluna
     * não existe fisicamente na tabela.
     */
    private function aplicarStatus(Builder $query, string $status): Builder
    {
        $hoje = now()->toDateString();
        $limite = now()->addDays(30)->toDateString();

        return match ($status) {
            'atrasada' => $query->whereNotNull('proxima_data')
                ->whereDate('proxima_data', '<', $hoje),

            'proxima' => $query->whereNotNull('proxima_data')
                ->whereDate('proxima_data', '>=', $hoje)
                ->whereDate('proxima_data', '<=', $limite),

            'em_dia' => $query->where(function (Builder $query) use ($limite) {
                $query->whereNull('proxima_data')
                    ->orWhereDate('proxima_data', '>', $limite);
            }),

            default => $query,
        };
    }

    private function contarPorStatus(string $status): int
    {
        return $this->aplicarStatus(Manutencao::query(), $status)->count();
    }
}
