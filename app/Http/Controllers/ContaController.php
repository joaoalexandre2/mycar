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
            // Com a data real do CRLV cadastrada, ela vale no lugar da estimativa de licenciamento.
            $temCrlv = $veiculo->crlvAtual() !== null;

            foreach ([
                'ipva' => [$veiculo->proximo_vencimento_ipva, $veiculo->ipva_estimado],
                'licenciamento' => [$veiculo->proximo_vencimento_licenciamento, $veiculo->licenciamento_valor],
            ] as $tipo => [$data, $valor]) {
                if ($data === null || ($tipo === 'licenciamento' && $temCrlv)) {
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

            // Serviços (óleo, bateria...) perto da próxima troca, por prazo ou por km.
            $kmAtual = $veiculo->kmAtual();

            foreach ($veiculo->servicosVigentes() as $servico) {
                $dias = $servico->diasRestantes($hoje);
                $faltam = $servico->kmRestante($kmAtual);

                $porPrazo = $dias !== null && $dias <= self::DIAS_A_FRENTE;
                $porKm = $faltam !== null && $faltam <= (int) config('servicos.margem_km');

                if (!$porPrazo && !$porKm) {
                    continue;
                }

                $vencimentos[] = [
                    'tipo' => 'servico',
                    'rotulo' => $servico->rotulo,
                    'veiculo_id' => $veiculo->id,
                    'veiculo' => trim($veiculo->apelido ?: "{$veiculo->marca} {$veiculo->modelo}"),
                    'placa' => $veiculo->placa,
                    // Só por km: sem data própria, aparece como "hoje".
                    'data' => $porPrazo ? $servico->proximo_em->toDateString() : $hoje->toDateString(),
                    'dias' => $porPrazo ? $dias : 0,
                    'valor_estimado' => null,
                    'km_restante' => $faltam,
                    'proxima_km' => $servico->proxima_km,
                    'por_km' => !$porPrazo,
                ];
            }

            foreach ($veiculo->documentosComVencimento() as $documento) {
                $dia = $documento->vencimento->copy()->startOfDay();

                if ($dia->gt($limite)) {
                    continue;
                }

                $vencimentos[] = [
                    'tipo' => 'documento',
                    'rotulo' => $documento->rotulo,
                    'veiculo_id' => $veiculo->id,
                    'veiculo' => trim($veiculo->apelido ?: "{$veiculo->marca} {$veiculo->modelo}"),
                    'placa' => $veiculo->placa,
                    'data' => $dia->toDateString(),
                    'dias' => (int) $hoje->diffInDays($dia, false),
                    'valor_estimado' => null,
                ];
            }

            // Fim da apólice de seguro (a de vigência mais longa), na mesma janela.
            $apolice = $veiculo->seguros()->where('tipo', 'apolice')->whereNotNull('vigencia_fim')->orderByDesc('vigencia_fim')->first();

            if ($apolice) {
                $dia = $apolice->vigencia_fim->copy()->startOfDay();

                if ($dia->lte($limite)) {
                    $vencimentos[] = [
                        'tipo' => 'seguro',
                        'veiculo_id' => $veiculo->id,
                        'veiculo' => trim($veiculo->apelido ?: "{$veiculo->marca} {$veiculo->modelo}"),
                        'placa' => $veiculo->placa,
                        'data' => $dia->toDateString(),
                        'dias' => (int) $hoje->diffInDays($dia, false),
                        'valor_estimado' => (float) $apolice->valor_anual,
                    ];
                }
            }

            // Revisão informada pelo dono; atrasada também aparece.
            if ($veiculo->revisao_prevista_em !== null) {
                $dia = $veiculo->revisao_prevista_em->copy()->startOfDay();

                if ($dia->lte($limite)) {
                    $vencimentos[] = [
                        'tipo' => 'revisao',
                        'veiculo_id' => $veiculo->id,
                        'veiculo' => trim($veiculo->apelido ?: "{$veiculo->marca} {$veiculo->modelo}"),
                        'placa' => $veiculo->placa,
                        'data' => $dia->toDateString(),
                        'dias' => (int) $hoje->diffInDays($dia, false),
                        'valor_estimado' => null,
                    ];
                }
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

    public function preferencias(Request $request)
    {
        return response()->json($this->dadosPreferencias($request));
    }

    public function atualizarPreferencias(Request $request)
    {
        $dados = $request->validate([
            'lembretes_email' => ['required', 'boolean'],
        ]);

        $request->user()->conta->update($dados);

        return response()->json($this->dadosPreferencias($request));
    }

    private function dadosPreferencias(Request $request): array
    {
        $conta = $request->user()->conta()->first();

        return [
            'nome' => $conta?->nome,
            'tipo' => $conta?->tipo,
            'lembretes_email' => (bool) $conta?->lembretes_email,
        ];
    }
}
