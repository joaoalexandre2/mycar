<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Manutencao;
use App\Models\Veiculo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Monta o resumo semanal de uma oficina: o que está atrasado ou vence nos
 * próximos dias, e quais clientes não puderam ser avisados por falta de
 * e-mail (os avisos automáticos só saem para quem tem e-mail cadastrado).
 *
 * Usa os models de domínio, então app('oficina.atual') precisa apontar
 * para a oficina antes de chamar montar(): é esse vínculo que garante que
 * só entram dados dela.
 */
class ResumoSemanalService
{
    public const DIAS_A_FRENTE = 30;

    public const LIMITE_POR_SECAO = 15;

    /**
     * @return array{
     *   manutencoes_atrasadas: array, manutencoes_proximas: array,
     *   ipva: array, licenciamento: array,
     *   sem_email: int, total: int
     * }
     */
    public function montar(): array
    {
        $hoje = now()->startOfDay();
        $limite = now()->addDays(self::DIAS_A_FRENTE)->endOfDay();

        $atrasadas = [];
        $proximas = [];

        foreach ($this->manutencoesPendentes($limite) as $manutencao) {
            $dia = $manutencao->proxima_data->copy()->startOfDay();
            $item = $this->itemManutencao($manutencao, $dia, $hoje);

            if ($dia->lt($hoje)) {
                $atrasadas[] = $item;
            } else {
                $proximas[] = $item;
            }
        }

        $ipva = [];
        $licenciamento = [];

        foreach (Veiculo::with('cliente')->get() as $veiculo) {
            $item = $this->itemTributo($veiculo, $veiculo->proximo_vencimento_ipva, $limite, $hoje);
            if ($item) {
                $ipva[] = $item;
            }

            $item = $this->itemTributo($veiculo, $veiculo->proximo_vencimento_licenciamento, $limite, $hoje);
            if ($item) {
                $licenciamento[] = $item;
            }
        }

        $secoes = [
            'manutencoes_atrasadas' => $atrasadas,
            'manutencoes_proximas' => $proximas,
            'ipva' => $this->ordenarPorData($ipva),
            'licenciamento' => $this->ordenarPorData($licenciamento),
        ];

        $semEmail = collect($secoes)
            ->flatten(1)
            ->filter(fn (array $item) => !$item['cliente_tem_email'])
            ->pluck('cliente_nome')
            ->unique()
            ->count();

        return [
            'manutencoes_atrasadas' => $this->secao($secoes['manutencoes_atrasadas']),
            'manutencoes_proximas' => $this->secao($secoes['manutencoes_proximas']),
            'ipva' => $this->secao($secoes['ipva']),
            'licenciamento' => $this->secao($secoes['licenciamento']),
            'sem_email' => $semEmail,
            'total' => collect($secoes)->sum(fn (array $itens) => count($itens)),
        ];
    }

    /**
     * Manutenções com próxima data até o limite, ignorando as que já foram
     * refeitas: se existe uma manutenção posterior do mesmo tipo no mesmo
     * veículo, a "próxima data" da antiga perdeu o sentido.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Manutencao>
     */
    private function manutencoesPendentes(Carbon $limite)
    {
        return Manutencao::with('veiculo.cliente')
            ->whereNotNull('proxima_data')
            ->whereDate('proxima_data', '<=', $limite->toDateString())
            ->whereNotExists(function ($consulta) {
                $consulta->select(DB::raw(1))
                    ->from('manutencoes as posterior')
                    ->whereColumn('posterior.veiculo_id', 'manutencoes.veiculo_id')
                    ->whereRaw('lower(posterior.tipo) = lower(manutencoes.tipo)')
                    ->whereColumn('posterior.data_manutencao', '>', 'manutencoes.data_manutencao');
            })
            ->orderBy('proxima_data')
            ->get();
    }

    private function itemManutencao(Manutencao $manutencao, Carbon $dia, Carbon $hoje): array
    {
        $veiculo = $manutencao->veiculo;

        return $this->item($veiculo, $veiculo?->cliente, $dia, $hoje) + [
            'descricao' => $manutencao->tipo,
        ];
    }

    private function itemTributo(Veiculo $veiculo, ?string $vencimento, Carbon $limite, Carbon $hoje): ?array
    {
        if ($vencimento === null) {
            return null;
        }

        $dia = Carbon::parse($vencimento)->startOfDay();

        if ($dia->gt($limite)) {
            return null;
        }

        return $this->item($veiculo, $veiculo->cliente, $dia, $hoje) + [
            'descricao' => null,
        ];
    }

    private function item(?Veiculo $veiculo, ?Cliente $cliente, Carbon $dia, Carbon $hoje): array
    {
        return [
            'veiculo' => $veiculo ? trim("{$veiculo->marca} {$veiculo->modelo}") : '—',
            'placa' => $veiculo?->placa ?? '—',
            'cliente_nome' => $cliente?->nome ?? '—',
            'cliente_telefone' => $cliente?->telefone,
            'cliente_tem_email' => $cliente !== null && !empty($cliente->email),
            'data' => $dia->format('d/m/Y'),
            'dias' => (int) $hoje->diffInDays($dia, false),
            '_ordem' => $dia->toDateString(),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $itens
     * @return array<int, array<string, mixed>>
     */
    private function ordenarPorData(array $itens): array
    {
        usort($itens, fn (array $a, array $b) => $a['_ordem'] <=> $b['_ordem']);

        return $itens;
    }

    /**
     * Limita a lista para o e-mail não ficar enorme e informa quantos ficaram de fora.
     *
     * @param array<int, array<string, mixed>> $itens
     * @return array{itens: array<int, array<string, mixed>>, total: int, restantes: int}
     */
    private function secao(array $itens): array
    {
        $total = count($itens);

        return [
            'itens' => array_map(
                fn (array $item) => array_diff_key($item, ['_ordem' => true]),
                array_slice($itens, 0, self::LIMITE_POR_SECAO)
            ),
            'total' => $total,
            'restantes' => max(0, $total - self::LIMITE_POR_SECAO),
        ];
    }
}
