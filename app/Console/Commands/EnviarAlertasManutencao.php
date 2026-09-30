<?php

namespace App\Console\Commands;

use App\Mail\AlertaManutencaoEmail;
use App\Models\Manutencao;
use App\Models\Oficina;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class EnviarAlertasManutencao extends Command
{
    protected $signature = 'manutencoes:alertar';

    protected $description = 'Envia e-mail ao cliente para manutenções atrasadas ou que vencem nos próximos 30 dias';

    public function handle(): int
    {
        $hoje = now()->toDateString();
        $limite = now()->addDays(30)->toDateString();
        $total = 0;

        foreach (Oficina::all() as $oficina) {
            app()->instance('oficina.atual', $oficina->id);

            $manutencoes = Manutencao::whereNull('alertado_em')
                ->whereNotNull('proxima_data')
                ->whereDate('proxima_data', '<=', $limite)
                ->with('veiculo.cliente')
                ->get();

            foreach ($manutencoes as $manutencao) {
                $cliente = $manutencao->veiculo?->cliente;

                if (!$cliente || empty($cliente->email)) {
                    continue;
                }

                $atrasada = $manutencao->proxima_data->toDateString() < $hoje;

                Mail::to($cliente->email)->send(new AlertaManutencaoEmail($manutencao, $atrasada));

                $manutencao->timestamps = false;
                $manutencao->alertado_em = now();
                $manutencao->save();

                $total++;
            }
        }

        $this->info("Alertas enviados: {$total}");

        return self::SUCCESS;
    }
}
