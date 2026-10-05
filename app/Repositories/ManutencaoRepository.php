<?php

namespace App\Repositories;

use App\Models\Manutencao;
use App\Models\VeiculoPeca;
use App\Repositories\Interfaces\ManutencaoRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ManutencaoRepository implements ManutencaoRepositoryInterface
{
    private const RELACOES = ['veiculo.cliente', 'pecas'];

    /**
     * Salva a manutenção e, se a requisição trouxe "pecas", registra essas
     * peças no veículo na mesma transação: ou grava tudo ou nada.
     */
    public function criar(array $dados): Manutencao
    {
        $pecas = $this->extrairPecas($dados);

        return DB::transaction(function () use ($dados, $pecas) {
            $manutencao = Manutencao::create($dados);

            if ($pecas !== null) {
                $this->sincronizarPecas($manutencao, $pecas);
            }

            return $manutencao->load(self::RELACOES);
        });
    }

    public function listar(array $filtros = []): Collection
    {
        return $this->aplicarFiltros(
            Manutencao::with(self::RELACOES),
            $filtros
        )->orderByDesc('data_manutencao')->get();
    }

    public function paginar(array $filtros, int $porPagina): LengthAwarePaginator
    {
        return $this->aplicarFiltros(
            Manutencao::with(self::RELACOES),
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
        return Manutencao::with(self::RELACOES)
            ->where('veiculo_id', $veiculoId)
            ->orderByDesc('data_manutencao')
            ->get();
    }

    public function buscarPorId(int $id): ?Manutencao
    {
        return Manutencao::with(self::RELACOES)->find($id);
    }

    /**
     * "pecas" ausente na requisição = não mexe nas peças já registradas
     * (clientes antigos continuam funcionando). "pecas" = [] remove todas
     * as peças desta manutenção. Qualquer outra lista substitui as atuais.
     */
    public function atualizar(int $id, array $dados): ?Manutencao
    {
        $pecas = $this->extrairPecas($dados);

        return DB::transaction(function () use ($id, $dados, $pecas) {
            $manutencao = Manutencao::find($id);

            if (!$manutencao) {
                return null;
            }

            $manutencao->update($dados);

            if ($pecas !== null) {
                $this->sincronizarPecas($manutencao, $pecas);
            } else {
                // Mudou o veículo ou a data? As peças registradas acompanham.
                VeiculoPeca::where('manutencao_id', $manutencao->id)->get()->each->update([
                    'veiculo_id' => $manutencao->veiculo_id,
                    'usado_em' => $manutencao->data_manutencao->toDateString(),
                ]);
            }

            return $manutencao->fresh(self::RELACOES);
        });
    }

    public function remover(int $id): bool
    {
        $manutencao = Manutencao::find($id);

        if (!$manutencao) {
            return false;
        }

        return (bool) $manutencao->delete();
    }

    /**
     * Tira "pecas" dos dados da manutenção (não é coluna dela).
     *
     * @return array<int, array<string, mixed>>|null null quando não veio na requisição
     */
    private function extrairPecas(array &$dados): ?array
    {
        $pecas = $dados['pecas'] ?? null;
        unset($dados['pecas']);

        return $pecas;
    }

    /**
     * Substitui as peças registradas por esta manutenção. Entram no veículo
     * como "serviço", com a data da própria manutenção.
     *
     * @param array<int, array<string, mixed>> $pecas
     */
    private function sincronizarPecas(Manutencao $manutencao, array $pecas): void
    {
        VeiculoPeca::where('manutencao_id', $manutencao->id)->delete();

        foreach ($pecas as $peca) {
            VeiculoPeca::create([
                'veiculo_id' => $manutencao->veiculo_id,
                'manutencao_id' => $manutencao->id,
                'tipo' => $peca['tipo'],
                'especificacao' => $peca['especificacao'],
                'marca' => $peca['marca'] ?? null,
                'fonte' => 'servico',
                'usado_em' => $manutencao->data_manutencao->toDateString(),
                'observacao' => $peca['observacao'] ?? null,
            ]);
        }
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
