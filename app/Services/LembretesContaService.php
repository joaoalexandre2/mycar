<?php

namespace App\Services;

use App\Models\VeiculoConta;
use Illuminate\Support\Carbon;

/**
 * Lembretes de IPVA, licenciamento e revisão dos veículos de uma conta
 * (perfis pessoa e frota). Mesma regra dos avisos de oficina: avisa uma vez
 * por ciclo, quando falta até 30 dias.
 *
 * Usa VeiculoConta, então app('conta.atual') precisa apontar para a conta.
 */
class LembretesContaService
{
    public const DIAS_A_FRENTE = 30;

    /**
     * Itens que ainda não foram avisados e que vencem em até 30 dias (ou a
     * revisão já atrasada).
     *
     * @return array<int, array{veiculo_id: int, tipo: string, veiculo: string, placa: string, data: string, dias: int, valor_estimado: ?float, ciclo: int|string}>
     */
    public function pendentes(): array
    {
        $hoje = now()->startOfDay();
        $limite = now()->addDays(self::DIAS_A_FRENTE)->endOfDay();
        $itens = [];

        foreach (VeiculoConta::all() as $veiculo) {
            $nome = trim($veiculo->apelido ?: "{$veiculo->marca} {$veiculo->modelo}");

            foreach ([
                'ipva' => [$veiculo->proximo_vencimento_ipva, $veiculo->ipva_estimado, $veiculo->ipva_alertado_ano],
                'licenciamento' => [$veiculo->proximo_vencimento_licenciamento, $veiculo->licenciamento_valor, $veiculo->licenciamento_alertado_ano],
            ] as $tipo => [$data, $valor, $alertadoAno]) {
                if ($data === null) {
                    continue;
                }

                $dia = Carbon::parse($data)->startOfDay();

                if ($dia->gt($limite) || $alertadoAno === $dia->year) {
                    continue;
                }

                $itens[] = $this->item($veiculo, $tipo, $nome, $dia, $hoje, $valor, $dia->year);
            }

            if ($veiculo->revisao_prevista_em !== null && $veiculo->revisao_alertada_em === null) {
                $dia = $veiculo->revisao_prevista_em->copy()->startOfDay();

                if ($dia->lte($limite)) {
                    $itens[] = $this->item($veiculo, 'revisao', $nome, $dia, $hoje, null, $dia->toDateString());
                }
            }
        }

        usort($itens, fn (array $a, array $b) => $a['data'] <=> $b['data']);

        return $itens;
    }

    /**
     * Grava que os itens foram avisados, para não repetir.
     *
     * @param array<int, array<string, mixed>> $itens
     */
    public function marcarComoAvisados(array $itens): void
    {
        foreach ($itens as $item) {
            $veiculo = VeiculoConta::find($item['veiculo_id']);

            if (!$veiculo) {
                continue;
            }

            match ($item['tipo']) {
                'ipva' => $veiculo->ipva_alertado_ano = $item['ciclo'],
                'licenciamento' => $veiculo->licenciamento_alertado_ano = $item['ciclo'],
                'revisao' => $veiculo->revisao_alertada_em = $item['ciclo'],
            };

            $veiculo->saveQuietly();
        }
    }

    private function item(VeiculoConta $veiculo, string $tipo, string $nome, Carbon $dia, Carbon $hoje, ?float $valor, int|string $ciclo): array
    {
        return [
            'veiculo_id' => $veiculo->id,
            'tipo' => $tipo,
            'veiculo' => $nome,
            'placa' => $veiculo->placa,
            'data' => $dia->toDateString(),
            'dias' => (int) $hoje->diffInDays($dia, false),
            'valor_estimado' => $valor,
            'ciclo' => $ciclo,
        ];
    }
}
