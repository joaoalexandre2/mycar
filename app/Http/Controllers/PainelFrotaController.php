<?php

namespace App\Http\Controllers;

use App\Models\ChecklistUso;
use App\Models\Motorista;
use App\Models\Multa;
use App\Models\UsoVeiculo;
use App\Models\VeiculoConta;
use App\Support\FusoDaFrota;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Painel da frota: alertas do que vence, checklists com problema e quanto cada veículo foi usado.
 * A utilização real (saída e chegada registradas) é separada das horas só reservadas.
 */
class PainelFrotaController extends Controller
{
    private const DIAS_DE_AVISO = 30;

    /** Quantos checklists com problema aparecem na lista. O total é informado à parte. */
    private const LIMITE_DE_CHECKLISTS = 50;

    public function alertas()
    {
        $hoje = Carbon::today();
        $limite = $hoje->copy()->addDays(self::DIAS_DE_AVISO);

        $cnh = Motorista::query()
            ->select(['id', 'nome', 'cnh_validade'])
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
            ->select(['id', 'placa', 'marca', 'modelo', 'revisao_prevista_em'])
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

        // Multas em aberto: a quantidade e o valor são somados no banco.
        $multas = Multa::query()
            ->where('situacao', Multa::ABERTA)
            ->selectRaw('count(*) as quantidade, coalesce(sum(valor), 0) as valor')
            ->first();

        // Problemas filtrados no banco, pela coluna gravada: nada de ler o JSON de todos os checklists.
        $problemasBase = ChecklistUso::query()
            ->where('problemas', '>', 0)
            ->where('created_at', '>=', now()->subDays(self::DIAS_DE_AVISO));

        $checklists = (clone $problemasBase)
            ->orderByDesc('id')
            ->limit(self::LIMITE_DE_CHECKLISTS)
            ->get()
            ->map(fn (ChecklistUso $c) => [
                'uso_id' => $c->uso_veiculo_id,
                'momento' => $c->momento,
                'problemas' => $c->problemas,
                'itens' => collect($c->itens)->where('ok', false)->pluck('item')
                    ->map(fn (string $chave) => ChecklistUso::ITENS[$chave] ?? $chave)->values(),
                'quando' => $c->created_at?->toIso8601String(),
            ])
            ->values();

        return response()->json([
            'cnh' => $cnh,
            'revisoes' => $revisoes,
            'multas_abertas' => ['quantidade' => (int) $multas->quantidade, 'valor' => (float) $multas->valor],
            'checklists_com_problema' => $checklists,
            'checklists_com_problema_total' => (clone $problemasBase)->count(),
        ]);
    }

    /**
     * Quanto cada veículo foi usado no período (padrão: últimos 30 dias), em medidas separadas:
     * utilização real (saída e chegada registradas), horas reservadas (previstas) e disponibilidade.
     */
    public function disponibilidade(Request $request)
    {
        $dados = $request->validate([
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date', 'after_or_equal:de'],
        ]);

        $inicio = isset($dados['de']) ? FusoDaFrota::dia($dados['de']) : FusoDaFrota::dia(now(FusoDaFrota::NOME)->subDays(29)->toDateString());
        $fim = isset($dados['ate']) ? FusoDaFrota::dia($dados['ate'], fim: true) : FusoDaFrota::dia(now(FusoDaFrota::NOME)->toDateString(), fim: true);

        // Capacidade em dias-calendário completos: cada dia do período vale 24 horas, inclusive o último.
        $dias = FusoDaFrota::local($inicio)->startOfDay()->diffInDays(FusoDaFrota::local($fim)->startOfDay()) + 1;
        $horasPeriodo = (float) ($dias * 24);

        // Um uso entra se o intervalo previsto OU o real (saída até chegada) encosta no período.
        // Assim, um uso que saiu na reserva e chegou depois do horário previsto não some da conta.
        $usos = UsoVeiculo::query()
            ->select(['id', 'veiculo_conta_id', 'status', 'inicio_previsto', 'fim_previsto', 'saida_em', 'chegada_em', 'km_saida', 'km_chegada'])
            ->where('status', '!=', 'cancelado')
            ->where(function ($q) use ($inicio, $fim) {
                $q->where(function ($previsto) use ($inicio, $fim) {
                    $previsto->where('inicio_previsto', '<=', $fim)->where('fim_previsto', '>=', $inicio);
                })->orWhere(function ($real) use ($inicio, $fim) {
                    $real->whereNotNull('saida_em')
                        ->where('saida_em', '<=', $fim)
                        ->where(fn ($chegada) => $chegada->whereNull('chegada_em')->orWhere('chegada_em', '>=', $inicio));
                });
            })
            ->get()
            ->groupBy('veiculo_conta_id');

        $multasPorVeiculo = Multa::query()
            ->whereBetween('data_hora', [$inicio, $fim])
            ->selectRaw('veiculo_conta_id, count(*) as total')
            ->groupBy('veiculo_conta_id')
            ->pluck('total', 'veiculo_conta_id');

        $veiculos = VeiculoConta::query()
            ->select(['id', 'placa', 'marca', 'modelo'])
            ->orderBy('placa')
            ->get()
            ->map(function (VeiculoConta $v) use ($usos, $multasPorVeiculo, $inicio, $fim, $horasPeriodo) {
                $deste = $usos->get($v->id, collect());

                $horasReservadas = $deste->sum(fn (UsoVeiculo $u) => $this->horasNoPeriodo(
                    $u->inicio_previsto,
                    $u->fim_previsto,
                    $inicio,
                    $fim,
                ));

                // Só conta uso real: quem tem saída registrada. Sem saída, a hora ainda é só reserva.
                $horasEmUso = $deste
                    ->filter(fn (UsoVeiculo $u) => $u->saida_em !== null)
                    ->sum(fn (UsoVeiculo $u) => $this->horasNoPeriodo(
                        $u->saida_em,
                        $u->chegada_em ?? $fim,
                        $inicio,
                        $fim,
                    ));

                $kmRodados = $deste
                    ->filter(fn (UsoVeiculo $u) => $u->km_saida !== null && $u->km_chegada !== null && $u->km_chegada >= $u->km_saida)
                    ->sum(fn (UsoVeiculo $u) => $u->km_chegada - $u->km_saida);

                $reservaPct = round(min(100, $horasReservadas / $horasPeriodo * 100), 1);

                return [
                    'veiculo_id' => $v->id,
                    'placa' => $v->placa,
                    'nome' => trim($v->marca . ' ' . $v->modelo),
                    'horas_em_uso' => round($horasEmUso, 1),
                    'utilizacao_real_pct' => round(min(100, $horasEmUso / $horasPeriodo * 100), 1),
                    'horas_reservadas' => round($horasReservadas, 1),
                    'reserva_pct' => $reservaPct,
                    'disponibilidade_pct' => round(max(0, 100 - $reservaPct), 1),
                    'reservas' => $deste->count(),
                    'km_rodados' => (int) $kmRodados,
                    'multas' => (int) ($multasPorVeiculo[$v->id] ?? 0),
                ];
            })
            ->values();

        $capacidade = $veiculos->count() * $horasPeriodo;

        return response()->json([
            'de' => $inicio->toDateString(),
            'ate' => $fim->toDateString(),
            'horas_periodo' => $horasPeriodo,
            'utilizacao_real_media_pct' => $capacidade > 0 ? round(min(100, $veiculos->sum('horas_em_uso') / $capacidade * 100), 1) : 0,
            'reserva_media_pct' => $capacidade > 0 ? round(min(100, $veiculos->sum('horas_reservadas') / $capacidade * 100), 1) : 0,
            'veiculos' => $veiculos,
        ]);
    }

    /** Horas de um intervalo que caem dentro do período pedido. */
    private function horasNoPeriodo($de, $ate, Carbon $inicio, Carbon $fim): float
    {
        $inicioEfetivo = $de->greaterThan($inicio) ? $de : $inicio;
        $fimEfetivo = $ate->lessThan($fim) ? $ate : $fim;

        return $fimEfetivo->greaterThan($inicioEfetivo) ? $inicioEfetivo->diffInMinutes($fimEfetivo) / 60 : 0.0;
    }
}
