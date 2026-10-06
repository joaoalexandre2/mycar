<?php

namespace App\Services;

use App\Models\Documento;
use App\Models\Seguro;
use App\Models\Servico;
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

            // Com a data real do CRLV cadastrada, ela vale no lugar da estimativa de licenciamento.
            $temCrlv = $veiculo->crlvAtual() !== null;

            foreach ([
                'ipva' => [$veiculo->proximo_vencimento_ipva, $veiculo->ipva_estimado, $veiculo->ipva_alertado_ano],
                'licenciamento' => [$veiculo->proximo_vencimento_licenciamento, $veiculo->licenciamento_valor, $veiculo->licenciamento_alertado_ano],
            ] as $tipo => [$data, $valor, $alertadoAno]) {
                if ($data === null || ($tipo === 'licenciamento' && $temCrlv)) {
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

            // Documentos (CRLV, vistoria, outros) com a data que o dono informou.
            foreach ($veiculo->documentosComVencimento() as $documento) {
                $dia = $documento->vencimento->copy()->startOfDay();

                if ($documento->alertado_em !== null || $dia->gt($limite)) {
                    continue;
                }

                $item = $this->item($veiculo, 'documento', $nome, $dia, $hoje, null, $dia->toDateString());
                $item['documento_id'] = $documento->id;
                $item['rotulo'] = $documento->rotulo;
                $itens[] = $item;
            }

            // Serviços (óleo, bateria...): só o mais recente de cada tipo, por prazo e/ou km.
            $kmAtual = $veiculo->kmAtual();

            foreach ($veiculo->servicosVigentes() as $servico) {
                if ($servico->proximo_em !== null && $servico->alerta_data_em === null) {
                    $dia = $servico->proximo_em->copy()->startOfDay();

                    if ($dia->lte(now()->addDays((int) config('servicos.margem_dias'))->endOfDay())) {
                        $item = $this->item($veiculo, 'servico', $nome, $dia, $hoje, null, $dia->toDateString());
                        $item['servico_id'] = $servico->id;
                        $item['motivo'] = 'data';
                        $item['rotulo'] = $servico->rotulo;
                        $itens[] = $item;
                    }
                }

                $faltam = $servico->kmRestante($kmAtual);

                if ($faltam !== null && $servico->alerta_km_em === null && $faltam <= (int) config('servicos.margem_km')) {
                    $item = $this->item($veiculo, 'servico', $nome, $hoje, $hoje, null, $hoje->toDateString());
                    $item['servico_id'] = $servico->id;
                    $item['motivo'] = 'km';
                    $item['rotulo'] = $servico->rotulo;
                    $item['km_restante'] = $faltam;
                    $item['proxima_km'] = $servico->proxima_km;
                    $itens[] = $item;
                }
            }

            // Seguro: avisa quando a apólice (a de vigência mais longa) está perto de acabar.
            $apolice = $veiculo->seguros()
                ->where('tipo', 'apolice')
                ->whereNotNull('vigencia_fim')
                ->orderByDesc('vigencia_fim')
                ->first();

            if ($apolice && $apolice->alertado_em === null) {
                $dia = $apolice->vigencia_fim->copy()->startOfDay();
                $limiteSeguro = now()->addDays((int) config('seguro.dias_lembrete'))->endOfDay();

                if ($dia->lte($limiteSeguro)) {
                    $item = $this->item($veiculo, 'seguro', $nome, $dia, $hoje, (float) $apolice->valor_anual, $dia->toDateString());
                    $item['seguro_id'] = $apolice->id;
                    $itens[] = $item;
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
            if ($item['tipo'] === 'servico') {
                $servico = Servico::find($item['servico_id']);

                if ($servico) {
                    $coluna = $item['motivo'] === 'km' ? 'alerta_km_em' : 'alerta_data_em';
                    $servico->{$coluna} = $item['ciclo'];
                    $servico->saveQuietly();
                }

                continue;
            }

            if ($item['tipo'] === 'documento') {
                $documento = Documento::find($item['documento_id']);

                if ($documento) {
                    $documento->alertado_em = $item['ciclo'];
                    $documento->saveQuietly();
                }

                continue;
            }

            if ($item['tipo'] === 'seguro') {
                $seguro = Seguro::find($item['seguro_id']);

                if ($seguro) {
                    $seguro->alertado_em = $item['ciclo'];
                    $seguro->saveQuietly();
                }

                continue;
            }

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
