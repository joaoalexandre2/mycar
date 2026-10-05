<?php

namespace App\Http\Controllers;

use App\Models\VeiculoConta;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Visão geral da conta (perfis pessoa e frota): quantos veículos, quanto
 * valem e o que vence em breve.
 */
class ContaController extends Controller
{
    /** Janela, em dias, dos vencimentos que aparecem na visão geral. */
    private const DIAS_A_FRENTE = 60;

    public function resumo(Request $request)
    {
        $usuario = $request->user();
        $hoje = now()->startOfDay();
        $limite = now()->addDays(self::DIAS_A_FRENTE)->endOfDay();

        $veiculos = VeiculoConta::all();
        $vencimentos = [];

        foreach ($veiculos as $veiculo) {
            foreach ([
                'ipva' => [$veiculo->proximo_vencimento_ipva, $veiculo->ipva_estimado],
                'licenciamento' => [$veiculo->proximo_vencimento_licenciamento, $veiculo->licenciamento_valor],
            ] as $tipo => [$data, $valor]) {
                if ($data === null) {
                    continue;
                }

                $dia = Carbon::parse($data)->startOfDay();

                if ($dia->gt($limite)) {
                    continue;
                }

                $vencimentos[] = [
                    'tipo' => $tipo,
                    'veiculo_id' => $veiculo->id,
                    'veiculo' => trim($veiculo->apelido ?: "{$veiculo->marca} {$veiculo->modelo}"),
                    'placa' => $veiculo->placa,
                    'data' => $dia->toDateString(),
                    'dias' => (int) $hoje->diffInDays($dia, false),
                    'valor_estimado' => $valor,
                ];
            }
        }

        usort($vencimentos, fn (array $a, array $b) => $a['data'] <=> $b['data']);

        return response()->json([
            'conta' => [
                'nome' => $usuario->conta?->nome,
                'tipo' => $usuario->conta?->tipo,
            ],
            'total_veiculos' => $veiculos->count(),
            'valor_total_fipe' => (float) $veiculos->sum('fipe_valor'),
            'vencimentos' => $vencimentos,
            'dias_a_frente' => self::DIAS_A_FRENTE,
        ]);
    }
}
