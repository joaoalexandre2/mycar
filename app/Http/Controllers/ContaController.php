<?php

namespace App\Http\Controllers;

use App\Models\Abastecimento;
use App\Models\Servico;
use App\Models\VeiculoConta;
use App\Services\CalculoConsumo;
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

    public function resumo(Request $request, CalculoConsumo $calculo)
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
            'consumo' => $this->consumoDaConta($veiculos, $calculo),
            'gasto_mes' => $this->gastoDoMes(),
        ]);
    }

    /**
     * Consumo médio (km/l) da conta: média dos veículos que já têm pelo menos um
     * intervalo de tanque cheio, e preço médio do litro de tudo que foi abastecido.
     * Sem dado suficiente, km_por_litro vem nulo (a tela mostra como começar).
     *
     * @param \Illuminate\Support\Collection<int, VeiculoConta> $veiculos
     * @return array{km_por_litro: ?float, preco_medio_litro: ?float, veiculos_com_dados: int}
     */
    private function consumoDaConta($veiculos, CalculoConsumo $calculo): array
    {
        $consumos = [];
        $gasto = 0.0;
        $litros = 0.0;

        foreach ($veiculos as $veiculo) {
            $resultado = $calculo->calcular($veiculo->abastecimentos()->get()->map(fn (Abastecimento $a) => [
                'id' => $a->id,
                'data' => $a->data->toDateString(),
                'km' => $a->km,
                'litros' => (float) $a->litros,
                'valor_total' => (float) $a->valor_total,
                'tanque_cheio' => $a->tanque_cheio,
            ]));

            if ($resultado['consumo_medio_km_l'] !== null) {
                $consumos[] = $resultado['consumo_medio_km_l'];
            }

            $gasto += $resultado['total_gasto'];
            $litros += $resultado['total_litros'];
        }

        return [
            'km_por_litro' => $consumos ? round(array_sum($consumos) / count($consumos), 1) : null,
            'preco_medio_litro' => $litros > 0 ? round($gasto / $litros, 2) : null,
            'veiculos_com_dados' => count($consumos),
        ];
    }

    /**
     * Gasto registrado no mês corrente: combustível (abastecimentos) e serviços
     * com valor informado. O seguro, que não tem data de pagamento, fica de fora.
     *
     * @return array{total: float, combustivel: float, servicos: float}
     */
    private function gastoDoMes(): array
    {
        $inicio = now()->startOfMonth()->toDateString();
        $fim = now()->endOfMonth()->toDateString();

        $combustivel = (float) Abastecimento::whereBetween('data', [$inicio, $fim])->sum('valor_total');
        $servicos = (float) Servico::whereBetween('realizado_em', [$inicio, $fim])->whereNotNull('valor')->sum('valor');

        return [
            'total' => round($combustivel + $servicos, 2),
            'combustivel' => round($combustivel, 2),
            'servicos' => round($servicos, 2),
        ];
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
