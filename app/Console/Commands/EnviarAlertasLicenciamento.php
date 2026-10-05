<?php

namespace App\Console\Commands;

use App\Mail\AlertaLicenciamentoEmail;
use App\Models\Oficina;
use App\Models\Veiculo;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class EnviarAlertasLicenciamento extends Command
{
    protected $signature = 'veiculos:alertar-licenciamento';

    protected $description = 'Envia e-mail ao cliente quando o licenciamento (CRLV) estimado do veículo está atrasado ou vence nos próximos 30 dias';

    public function handle(): int
    {
        $limite = now()->addDays(30);
        $total = 0;

        foreach (Oficina::ativas()->get() as $oficina) {
            app()->instance('oficina.atual', $oficina->id);

            foreach (Veiculo::with('cliente')->get() as $veiculo) {
                $vencimento = $veiculo->proximo_vencimento_licenciamento;

                if ($vencimento === null) {
                    continue;
                }

                $vencimentoCarbon = Carbon::parse($vencimento)->endOfDay();

                if ($vencimentoCarbon->gt($limite)) {
                    continue;
                }

                $cicloAno = $vencimentoCarbon->year;

                if ($veiculo->licenciamento_alertado_ano === $cicloAno) {
                    continue;
                }

                $cliente = $veiculo->cliente;

                if (!$cliente || empty($cliente->email)) {
                    continue;
                }

                $atrasado = $vencimentoCarbon->isPast();

                Mail::to($cliente->email)->send(new AlertaLicenciamentoEmail($veiculo, $vencimento, $atrasado));

                $veiculo->licenciamento_alertado_ano = $cicloAno;
                $veiculo->saveQuietly();

                $total++;
            }
        }

        $this->info("Alertas de licenciamento enviados: {$total}");

        return self::SUCCESS;
    }
}
