<?php

namespace App\Http\Controllers;

use App\Models\ChecklistUso;
use App\Models\Motorista;
use App\Models\Multa;
use App\Models\UsoVeiculo;
use App\Models\VeiculoConta;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Painel da frota: alertas do que vence, checklists com problema e quanto cada veículo foi usado. */
class PainelFrotaController extends Controller
{
    private const DIAS_DE_AVISO = 30;

    public function alertas()
    {
        $hoje = Carbon::today();
        $limite = $hoje->copy()->addDays(self::DIAS_DE_AVISO);

        $cnh = Motorista::query()
            ->where('ativo', true)
            ->whereNotNull('cnh_validade')
            ->where('cnh_validade', '<=', $limite->toDateString())
            ->orderBy('cnh_validade')
            ->get()
            ->map(fn (Motorista $m) => [
                'id' => $m->id,
                'nome' => $m->nome,
                'validade' => $m->cnh_validade?->toDateString(),
                'situacao' => $m->cnh_validade->lt($hoje) ? 'vencida' : 'vence_em_breve',
            ])
            ->values();

        $revisoes = VeiculoConta::query()
            ->whereNotNull('revisao_prevista_em')
            ->where('revisao_prevista_em', '<=', $limite->toDateString())
            ->orderBy('revisao_prevista_em')
            ->get()
            ->map(fn (VeiculoConta $v) => [
                'id' => $v->id,
                'placa' => $v->placa,
                'nome' => trim($v->marca . ' ' . $v->modelo),
                'data' => $v->revisao_prevista_em?->toDateString(),
            ])
            ->values();

        $multasAbertas = Multa::query()->where('situacao', Multa::ABERTA);

        $checklists = ChecklistUso::query()
            ->where('created_at', '>=', now()->subDays(self::DIAS_DE_AVISO))
            ->orderByDesc('id')
            ->limit(300)
            ->get()
            ->filter(fn (ChecklistUso $c) => $c->problemas() > 0)
            ->map(fn (ChecklistUso $c) => [
                'uso_id' => $c->uso_veiculo_id,
                'momento' => $c->momento,
                'problemas' => $c->problemas(),
                'itens' => collect($c->itens)->where('ok', false)->pluck('item')
                    ->map(fn (string $chave) => ChecklistUso::ITENS[$chave] ?? $chave)->values(),
                'quando' => $c->created_at?->toIso8601String(),
            ])
            ->values();

        return response()->json([
            'cnh' => $cnh,
            'revisoes' => $revisoes,
            'multas_abertas' => ['quantidade' => (clone $multasAbertas)->count(), 'valor' => (float) (clone $multasAbertas)->sum('valor')],
            'checklists_com_problema' => $checklists,
        ]);
    }

    /** Quanto cada veículo esteve reservado no período (padrão: últimos 30 dias). */
    public function disponibilidade(Request $request)
    {
        $dados = $request->validate([
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date', 'after_or_equal:de'],
        ]);

        $inicio = isset($dados['de']) ? Carbon::parse($dados['de'])->startOfDay() : Carbon::today()->subDays(29);
        $fim = isset($dados['ate']) ? Carbon::parse($dados['ate'])->endOfDay() : Carbon::today()->endOfDay();
        $horasPeriodo = max(1, $inicio->diffInHours($fim));

        $usos = UsoVeiculo::query()
            ->where('status', '!=', 'cancelado')
            ->where('inicio_previsto', '<=', $fim)
            ->where('fim_previsto', '>=', $inicio)
            ->get();

        $multas = Multa::query()->whereBetween('data_hora', [$inicio, $fim])->get()->groupBy('veiculo_conta_id');

        $veiculos = VeiculoConta::query()->orderBy('placa')->get()->map(function (VeiculoConta $v) use ($usos, $multas, $inicio, $fim, $horasPeriodo) {
            $dosVeiculo = $usos->where('veiculo_conta_id', $v->id);

            $horasUsadas = $dosVeiculo->sum(function (UsoVeiculo $u) use ($inicio, $fim) {
                $de = $u->saida_em ?? $u->inicio_previsto;
                $ate = $u->chegada_em ?? $u->fim_previsto;
                $de = $de->greaterThan($inicio) ? $de : $inicio;
                $ate = $ate->lessThan($fim) ? $ate : $fim;

                return $ate->greaterThan($de) ? $de->diffInMinutes($ate) / 60 : 0;
            });

            $kmRodados = $dosVeiculo
                ->filter(fn (UsoVeiculo $u) => $u->km_saida !== null && $u->km_chegada !== null && $u->km_chegada >= $u->km_saida)
                ->sum(fn (UsoVeiculo $u) => $u->km_chegada - $u->km_saida);

            $utilizacao = round(min(100, $horasUsadas / $horasPeriodo * 100), 1);

            return [
                'veiculo_id' => $v->id,
                'placa' => $v->placa,
                'nome' => trim($v->marca . ' ' . $v->modelo),
                'horas_usadas' => round($horasUsadas, 1),
                'utilizacao_pct' => $utilizacao,
                'ociosidade_pct' => round(100 - $utilizacao, 1),
                'reservas' => $dosVeiculo->count(),
                'km_rodados' => (int) $kmRodados,
                'multas' => $multas->get($v->id, collect())->count(),
            ];
        })->values();

        $totalHoras = $veiculos->sum('horas_usadas');
        $capacidade = $veiculos->count() * $horasPeriodo;

        return response()->json([
            'de' => $inicio->toDateString(),
            'ate' => $fim->toDateString(),
            'horas_periodo' => $horasPeriodo,
            'utilizacao_media_pct' => $capacidade > 0 ? round(min(100, $totalHoras / $capacidade * 100), 1) : 0,
            'veiculos' => $veiculos,
        ]);
    }
}
