<?php

namespace App\Console\Commands;

use App\Mail\AlertaIpvaEmail;
use App\Models\Oficina;
use App\Models\Veiculo;
use App\Support\FusoDaFrota;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class EnviarAlertasIpva extends Command
{
    protected $signature = 'veiculos:alertar-ipva';

    protected $description = 'Envia e-mail ao cliente quando o IPVA estimado do veículo está atrasado ou vence nos próximos 30 dias';

    public function handle(): int
    {
        // Os dias são os de Brasília: o servidor roda em UTC e, depois das 21h, já estaria no dia seguinte.
        $limite = now(FusoDaFrota::NOME)->addDays(30)->endOfDay();
        $total = 0;

        foreach (Oficina::ativas()->get() as $oficina) {
            app()->instance('oficina.atual', $oficina->id);

            foreach (Veiculo::with('cliente')->get() as $veiculo) {
                $vencimento = $veiculo->proximo_vencimento_ipva;

                if ($vencimento === null) {
                    continue;
                }

                $vencimentoCarbon = Carbon::parse($vencimento, FusoDaFrota::NOME)->endOfDay();

                if ($vencimentoCarbon->gt($limite)) {
                    continue;
                }

                $cicloAno = $vencimentoCarbon->year;

                if ($veiculo->ipva_alertado_ano === $cicloAno) {
                    continue;
                }

                $cliente = $veiculo->cliente;

                if (!$cliente || empty($cliente->email)) {
                    continue;
                }

                Mail::to($cliente->email)->send(
                    new AlertaIpvaEmail($veiculo, $vencimento, $vencimentoCarbon->isPast())
                );

                $veiculo->ipva_alertado_ano = $cicloAno;
                $veiculo->saveQuietly();

                $total++;
            }
        }

        $this->info("Alertas de IPVA enviados: {$total}");

        return self::SUCCESS;
    }
}
