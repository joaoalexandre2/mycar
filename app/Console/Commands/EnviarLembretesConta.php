<?php

namespace App\Console\Commands;

use App\Mail\LembreteContaEmail;
use App\Models\Conta;
use App\Services\LembretesContaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class EnviarLembretesConta extends Command
{
    protected $signature = 'contas:alertar-vencimentos {--dry-run : Mostra o que seria enviado, sem mandar e-mail nem marcar nada}';

    protected $description = 'Avisa por e-mail as contas pessoa e frota sobre IPVA, licenciamento e revisão que vencem em até 30 dias';

    public function handle(LembretesContaService $servico): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $enviados = 0;

        foreach (Conta::where('lembretes_email', true)->get() as $conta) {
            // Vincula a conta: VeiculoConta só enxerga os veículos dela.
            app()->instance('conta.atual', $conta->id);

            $itens = $servico->pendentes();

            if ($itens === []) {
                continue;
            }

            // Só quem usa o perfil de conta: um usuário que voltou a ser oficina
            // mantém o conta_id, mas não recebe estes lembretes.
            $destinatarios = $conta->usuarios()
                ->whereIn('perfil', Conta::TIPOS)
                ->whereNotNull('email_verified_at')
                ->pluck('email')
                ->all();

            if ($destinatarios === []) {
                continue;
            }

            if ($dryRun) {
                $this->line("[teste] {$conta->nome}: " . count($itens) . ' item(ns) para ' . implode(', ', $destinatarios));

                continue;
            }

            Mail::to($destinatarios)->send(new LembreteContaEmail($conta, $itens));
            $servico->marcarComoAvisados($itens);

            $enviados++;
        }

        $this->info($dryRun ? 'Teste concluído, nada foi enviado.' : "Lembretes enviados: {$enviados}");

        return self::SUCCESS;
    }
}
