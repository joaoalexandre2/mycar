<?php

namespace App\Services;

use App\Models\Abastecimento;
use App\Models\CentroCusto;
use App\Models\Despesa;
use App\Models\Motorista;
use App\Models\Multa;
use App\Models\Seguro;
use App\Models\Servico;
use App\Models\UsoVeiculo;
use App\Models\VeiculoConta;
use App\Support\FusoDaFrota;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tudo que a conta gastou com a frota, consolidado uma única vez:
 * combustível (abastecimentos), serviços (manutenção da conta), despesas avulsas e multas.
 * Cada gasto tem um só registro de origem, então nada entra duas vezes.
 *
 * O seguro é valor anual da apólice atual: fica à parte e não entra no total do período.
 * Os filtros de data são dias de Brasília; os campos de data-hora são comparados em UTC.
 */
class ConsolidacaoDeDespesas
{
    private const ROTULOS_COMBUSTIVEL = [
        'gasolina' => 'Gasolina',
        'etanol' => 'Etanol',
        'diesel' => 'Diesel',
        'gnv' => 'GNV',
        'outro' => 'Outro combustível',
    ];

    public function consolidar(?string $de, ?string $ate, ?int $veiculoId): array
    {
        $veiculos = VeiculoConta::all()->keyBy('id');
        $motoristas = Motorista::all()->keyBy('id');
        $centros = CentroCusto::all()->keyBy('id');

        $abastecimentos = $this->noPeriodo(Abastecimento::query(), 'data', $de, $ate, $veiculoId)->get();
        $servicos = $this->noPeriodo(Servico::query(), 'realizado_em', $de, $ate, $veiculoId)->whereNotNull('valor')->get();
        $avulsas = $this->noPeriodo(Despesa::query()->with('categoria'), 'data', $de, $ate, $veiculoId)->get();
        $multas = $this->multasNoPeriodo($de, $ate, $veiculoId)->get();
        $usos = $this->usosNoPeriodo($de, $ate, $veiculoId)->get();

        $combustivel = $this->combustivel($abastecimentos);
        $servicosResumo = $this->servicos($servicos);
        $avulsasResumo = $this->avulsas($avulsas);
        $multasResumo = [
            'total' => round((float) $multas->sum('valor'), 2),
            'quantidade' => $multas->count(),
        ];

        $totalCombustivel = round((float) $abastecimentos->sum('valor_total'), 2);
        $totalServicos = round((float) $servicos->sum('valor'), 2);
        $total = round($totalCombustivel + $totalServicos + $avulsasResumo['total'] + $multasResumo['total'], 2);

        $porVeiculo = $this->porVeiculo($veiculos, $veiculoId, $abastecimentos, $servicos, $avulsas, $multas, $usos);
        $kmTotal = (int) $porVeiculo->sum('km_rodados');

        return [
            'periodo' => ['de' => $de, 'ate' => $ate],
            'total' => $total,
            'combustivel' => [
                'total' => $totalCombustivel,
                'litros' => round((float) $abastecimentos->sum('litros'), 1),
                'itens' => $combustivel,
            ],
            'servicos' => [
                'total' => $totalServicos,
                'itens' => $servicosResumo,
            ],
            'avulsas' => $avulsasResumo,
            'multas' => $multasResumo,
            'seguro' => $this->seguro($veiculos, $veiculoId),

            'por_veiculo' => $porVeiculo,
            'por_motorista' => $this->porMotorista($avulsas, $multas, $motoristas),
            'por_centro' => $this->porCentro($avulsas, $centros),
            'mensal' => $this->mensal($abastecimentos, $servicos, $avulsas, $multas),
            'indicadores' => [
                'custo_total' => $total,
                'custo_medio_por_veiculo' => $porVeiculo->count() > 0 ? round($total / $porVeiculo->count(), 2) : 0.0,
                'km_rodados' => $kmTotal,
                'custo_por_km' => $kmTotal > 0 ? round($total / $kmTotal, 2) : null,
                'veiculo_maior_custo' => $this->destaque($porVeiculo, 'total'),
                'veiculo_maior_manutencao' => $this->destaque($porVeiculo, 'servicos'),
            ],
        ];
    }

    private function noPeriodo(Builder $consulta, string $coluna, ?string $de, ?string $ate, ?int $veiculoId): Builder
    {
        if ($de) {
            $consulta->whereDate($coluna, '>=', $de);
        }

        if ($ate) {
            $consulta->whereDate($coluna, '<=', $ate);
        }

        if ($veiculoId !== null) {
            $consulta->where('veiculo_conta_id', $veiculoId);
        }

        return $consulta;
    }

    /** Multas guardam data e hora: o período é o dia de Brasília, comparado em UTC. */
    private function multasNoPeriodo(?string $de, ?string $ate, ?int $veiculoId): Builder
    {
        $consulta = Multa::query();

        if ($de) {
            $consulta->where('data_hora', '>=', FusoDaFrota::dia($de)->format('Y-m-d H:i:s'));
        }

        if ($ate) {
            $consulta->where('data_hora', '<=', FusoDaFrota::dia($ate, true)->format('Y-m-d H:i:s'));
        }

        if ($veiculoId !== null) {
            $consulta->where('veiculo_conta_id', $veiculoId);
        }

        return $consulta;
    }

    /** Quilometragem vem dos usos concluídos que saíram no período. */
    private function usosNoPeriodo(?string $de, ?string $ate, ?int $veiculoId): Builder
    {
        $consulta = UsoVeiculo::query()
            ->where('status', 'concluido')
            ->whereNotNull('km_saida')
            ->whereNotNull('km_chegada');

        if ($de) {
            $consulta->where('saida_em', '>=', FusoDaFrota::dia($de)->format('Y-m-d H:i:s'));
        }

        if ($ate) {
            $consulta->where('saida_em', '<=', FusoDaFrota::dia($ate, true)->format('Y-m-d H:i:s'));
        }

        if ($veiculoId !== null) {
            $consulta->where('veiculo_conta_id', $veiculoId);
        }

        return $consulta;
    }

    private function combustivel($abastecimentos)
    {
        return $abastecimentos
            ->groupBy(fn (Abastecimento $a) => $a->combustivel ?: 'outro')
            ->map(fn ($grupo, $tipo) => [
                'tipo' => $tipo,
                'rotulo' => self::ROTULOS_COMBUSTIVEL[$tipo] ?? ucfirst($tipo),
                'total' => round((float) $grupo->sum('valor_total'), 2),
                'litros' => round((float) $grupo->sum('litros'), 1),
                'quantidade' => $grupo->count(),
            ])
            ->sortByDesc('total')
            ->values();
    }

    private function servicos($servicos)
    {
        return $servicos
            ->groupBy(fn (Servico $s) => $s->tipo === 'outro' ? 'outro:' . mb_strtolower(trim((string) $s->titulo)) : $s->tipo)
            ->map(fn ($grupo) => [
                'tipo' => $grupo->first()->tipo,
                'rotulo' => $grupo->first()->rotulo,
                'total' => round((float) $grupo->sum('valor'), 2),
                'quantidade' => $grupo->count(),
            ])
            ->sortByDesc('total')
            ->values();
    }

    private function avulsas($avulsas): array
    {
        $itens = $avulsas
            ->groupBy('categoria_id')
            ->map(fn ($grupo) => [
                'categoria_id' => $grupo->first()->categoria_id,
                'rotulo' => $grupo->first()->categoria?->nome ?? 'Sem categoria',
                'total' => round((float) $grupo->sum('valor'), 2),
                'quantidade' => $grupo->count(),
            ])
            ->sortByDesc('total')
            ->values();

        return [
            'total' => round((float) $avulsas->sum('valor'), 2),
            'itens' => $itens,
        ];
    }

    private function seguro($veiculos, ?int $veiculoId): array
    {
        // Apólice atual de cada veículo (a de vigência mais longa), sem filtro de data.
        $apolices = Seguro::query()
            ->where('tipo', 'apolice')
            ->when($veiculoId !== null, fn ($q) => $q->where('veiculo_conta_id', $veiculoId))
            ->orderByDesc('vigencia_fim')
            ->get()
            ->unique('veiculo_conta_id')
            ->values();

        return [
            'valor_anual_total' => round((float) $apolices->sum('valor_anual'), 2),
            'itens' => $apolices->map(fn (Seguro $s) => [
                'veiculo' => ($v = $veiculos->get($s->veiculo_conta_id))
                    ? trim($v->apelido ?: "{$v->marca} {$v->modelo}")
                    : null,
                'seguradora' => $s->seguradora,
                'valor_anual' => (float) $s->valor_anual,
                'vigencia_fim' => $s->vigencia_fim?->toDateString(),
            ])->values(),
        ];
    }

    private function porVeiculo($veiculos, ?int $veiculoId, $abastecimentos, $servicos, $avulsas, $multas, $usos)
    {
        return $veiculos
            ->when($veiculoId !== null, fn ($c) => $c->only([$veiculoId]))
            ->map(function (VeiculoConta $v) use ($abastecimentos, $servicos, $avulsas, $multas, $usos) {
                $combustivel = round((float) $abastecimentos->where('veiculo_conta_id', $v->id)->sum('valor_total'), 2);
                $servico = round((float) $servicos->where('veiculo_conta_id', $v->id)->sum('valor'), 2);
                $avulsa = round((float) $avulsas->where('veiculo_conta_id', $v->id)->sum('valor'), 2);
                $multa = round((float) $multas->where('veiculo_conta_id', $v->id)->sum('valor'), 2);
                $total = round($combustivel + $servico + $avulsa + $multa, 2);
                $km = (int) $usos->where('veiculo_conta_id', $v->id)->sum(fn (UsoVeiculo $u) => max(0, $u->km_chegada - $u->km_saida));

                return [
                    'veiculo_id' => $v->id,
                    'veiculo' => trim($v->apelido ?: "{$v->marca} {$v->modelo}"),
                    'placa' => $v->placa,
                    'combustivel' => $combustivel,
                    'servicos' => $servico,
                    'avulsas' => $avulsa,
                    'multas' => $multa,
                    'total' => $total,
                    'km_rodados' => $km,
                    'custo_por_km' => $km > 0 ? round($total / $km, 2) : null,
                ];
            })
            ->sortByDesc('total')
            ->values();
    }

    /** Motorista só existe em despesas avulsas e em multas: abastecimento e serviço não o registram. */
    private function porMotorista($avulsas, $multas, $motoristas)
    {
        $linhas = $avulsas
            ->map(fn (Despesa $d) => ['motorista_id' => $d->motorista_id, 'tipo' => 'avulsa', 'valor' => (float) $d->valor])
            ->concat($multas->map(fn (Multa $m) => ['motorista_id' => $m->motorista_id, 'tipo' => 'multa', 'valor' => (float) $m->valor]));

        return $linhas
            ->groupBy(fn (array $linha) => $linha['motorista_id'] ?? 0)
            ->map(function ($grupo, $motoristaId) use ($motoristas) {
                $avulsa = round((float) $grupo->where('tipo', 'avulsa')->sum('valor'), 2);
                $multa = round((float) $grupo->where('tipo', 'multa')->sum('valor'), 2);
                $id = (int) $motoristaId ?: null;

                return [
                    'motorista_id' => $id,
                    'nome' => $id === null ? 'Sem motorista' : ($motoristas->get($id)?->nome ?? 'Motorista removido'),
                    'avulsas' => $avulsa,
                    'multas' => $multa,
                    'total' => round($avulsa + $multa, 2),
                ];
            })
            ->sortByDesc('total')
            ->values();
    }

    private function porCentro($avulsas, $centros)
    {
        return $avulsas
            ->groupBy(fn (Despesa $d) => $d->centro_custo_id ?? 0)
            ->map(function ($grupo, $centroId) use ($centros) {
                $id = (int) $centroId ?: null;

                return [
                    'centro_custo_id' => $id,
                    'nome' => $id === null ? 'Sem centro de custo' : ($centros->get($id)?->nome ?? 'Centro removido'),
                    'total' => round((float) $grupo->sum('valor'), 2),
                    'quantidade' => $grupo->count(),
                ];
            })
            ->sortByDesc('total')
            ->values();
    }

    /** Gasto por mês, em dias de Brasília. Só meses com algum gasto. */
    private function mensal($abastecimentos, $servicos, $avulsas, $multas)
    {
        $linhas = collect()
            ->concat($abastecimentos->map(fn ($a) => ['mes' => $this->mes($a->data), 'fonte' => 'combustivel', 'valor' => (float) $a->valor_total]))
            ->concat($servicos->map(fn ($s) => ['mes' => $this->mes($s->realizado_em), 'fonte' => 'servicos', 'valor' => (float) $s->valor]))
            ->concat($avulsas->map(fn ($d) => ['mes' => $this->mes($d->data), 'fonte' => 'avulsas', 'valor' => (float) $d->valor]))
            ->concat($multas->filter(fn ($m) => $m->valor !== null)->map(fn ($m) => ['mes' => FusoDaFrota::local($m->data_hora)->format('Y-m'), 'fonte' => 'multas', 'valor' => (float) $m->valor]));

        return $linhas
            ->groupBy('mes')
            ->sortKeys()
            ->map(function ($grupo, $mes) {
                $porFonte = fn (string $fonte) => round((float) $grupo->where('fonte', $fonte)->sum('valor'), 2);
                $combustivel = $porFonte('combustivel');
                $servicos = $porFonte('servicos');
                $avulsas = $porFonte('avulsas');
                $multas = $porFonte('multas');

                return [
                    'mes' => $mes,
                    'combustivel' => $combustivel,
                    'servicos' => $servicos,
                    'avulsas' => $avulsas,
                    'multas' => $multas,
                    'total' => round($combustivel + $servicos + $avulsas + $multas, 2),
                ];
            })
            ->values();
    }

    private function mes($data): string
    {
        return Carbon::parse($data)->format('Y-m');
    }

    /** Veículo com o maior valor da coluna, ou null quando não há veículos. */
    private function destaque($porVeiculo, string $coluna): ?array
    {
        $veiculo = $porVeiculo->sortByDesc($coluna)->first();

        if (!$veiculo) {
            return null;
        }

        return ['veiculo_id' => $veiculo['veiculo_id'], 'placa' => $veiculo['placa'], 'valor' => $veiculo[$coluna]];
    }
}
