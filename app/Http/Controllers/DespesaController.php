<?php

namespace App\Http\Controllers;

use App\Models\Abastecimento;
use App\Models\Seguro;
use App\Models\Servico;
use App\Models\VeiculoConta;
use Illuminate\Http\Request;

/**
 * Tudo que a conta registrou de gasto, por categoria: combustível (por tipo),
 * serviços (óleo, bateria...) e seguro. Isolado pela conta via PertenceAConta.
 *
 * O seguro não tem data de pagamento no sistema, então aparece à parte, como
 * valor anual da apólice atual, e não entra no total do período.
 */
class DespesaController extends Controller
{
    private const ROTULOS_COMBUSTIVEL = [
        'gasolina' => 'Gasolina',
        'etanol' => 'Etanol',
        'diesel' => 'Diesel',
        'gnv' => 'GNV',
        'outro' => 'Outro combustível',
    ];

    public function index(Request $request)
    {
        $filtros = $request->validate([
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date', 'after_or_equal:de'],
            'veiculo_id' => ['nullable', 'integer'],
        ]);

        $veiculos = VeiculoConta::all()->keyBy('id');
        $veiculoId = $filtros['veiculo_id'] ?? null;

        if ($veiculoId !== null && !$veiculos->has($veiculoId)) {
            return response()->json(['message' => 'Veículo não encontrado.'], 404);
        }

        $noPeriodo = function ($consulta, string $coluna) use ($filtros, $veiculoId) {
            if (!empty($filtros['de'])) {
                $consulta->whereDate($coluna, '>=', $filtros['de']);
            }

            if (!empty($filtros['ate'])) {
                $consulta->whereDate($coluna, '<=', $filtros['ate']);
            }

            if ($veiculoId !== null) {
                $consulta->where('veiculo_conta_id', $veiculoId);
            }

            return $consulta;
        };

        $abastecimentos = $noPeriodo(Abastecimento::query(), 'data')->get();
        $servicos = $noPeriodo(Servico::query(), 'realizado_em')->whereNotNull('valor')->get();

        $combustivel = $abastecimentos
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

        $porServico = $servicos
            ->groupBy(fn (Servico $s) => $s->tipo === 'outro' ? 'outro:'.mb_strtolower(trim((string) $s->titulo)) : $s->tipo)
            ->map(fn ($grupo) => [
                'tipo' => $grupo->first()->tipo,
                'rotulo' => $grupo->first()->rotulo,
                'total' => round((float) $grupo->sum('valor'), 2),
                'quantidade' => $grupo->count(),
            ])
            ->sortByDesc('total')
            ->values();

        $totalCombustivel = round((float) $abastecimentos->sum('valor_total'), 2);
        $totalServicos = round((float) $servicos->sum('valor'), 2);

        // Apólice atual de cada veículo (a de vigência mais longa), sem filtro de data.
        $apolices = Seguro::query()
            ->where('tipo', 'apolice')
            ->when($veiculoId !== null, fn ($q) => $q->where('veiculo_conta_id', $veiculoId))
            ->orderByDesc('vigencia_fim')
            ->get()
            ->unique('veiculo_conta_id')
            ->values();

        $porVeiculo = $veiculos
            ->when($veiculoId !== null, fn ($c) => $c->only([$veiculoId]))
            ->map(function (VeiculoConta $v) use ($abastecimentos, $servicos) {
                $combustivel = round((float) $abastecimentos->where('veiculo_conta_id', $v->id)->sum('valor_total'), 2);
                $servico = round((float) $servicos->where('veiculo_conta_id', $v->id)->sum('valor'), 2);

                return [
                    'veiculo_id' => $v->id,
                    'veiculo' => trim($v->apelido ?: "{$v->marca} {$v->modelo}"),
                    'placa' => $v->placa,
                    'combustivel' => $combustivel,
                    'servicos' => $servico,
                    'total' => round($combustivel + $servico, 2),
                ];
            })
            ->sortByDesc('total')
            ->values();

        return response()->json([
            'periodo' => ['de' => $filtros['de'] ?? null, 'ate' => $filtros['ate'] ?? null],
            'total' => round($totalCombustivel + $totalServicos, 2),
            'combustivel' => [
                'total' => $totalCombustivel,
                'litros' => round((float) $abastecimentos->sum('litros'), 1),
                'itens' => $combustivel,
            ],
            'servicos' => [
                'total' => $totalServicos,
                'itens' => $porServico,
            ],
            'seguro' => [
                'valor_anual_total' => round((float) $apolices->sum('valor_anual'), 2),
                'itens' => $apolices->map(fn (Seguro $s) => [
                    'veiculo' => ($v = $veiculos->get($s->veiculo_conta_id))
                        ? trim($v->apelido ?: "{$v->marca} {$v->modelo}")
                        : null,
                    'seguradora' => $s->seguradora,
                    'valor_anual' => (float) $s->valor_anual,
                    'vigencia_fim' => $s->vigencia_fim?->toDateString(),
                ])->values(),
            ],
            'por_veiculo' => $porVeiculo,
        ]);
    }
}
