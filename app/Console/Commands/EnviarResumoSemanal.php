<?php

namespace App\Console\Commands;

use App\Mail\ResumoSemanalEmail;
use App\Models\Oficina;
use App\Services\ResumoSemanalService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class EnviarResumoSemanal extends Command
{
    protected $signature = 'oficinas:resumo-semanal
        {--oficina= : Envia só para a oficina com este id}
        {--dry-run : Mostra o que seria enviado, sem mandar e-mail}';

    protected $description = 'Envia à oficina um resumo semanal do que está atrasado ou vence nos próximos 30 dias';

    public function handle(ResumoSemanalService $servico): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $enviados = 0;

        $oficinas = Oficina::query()
            ->where('resumo_semanal', true)
            ->when($this->option('oficina'), fn ($consulta, $id) => $consulta->whereKey($id))
            ->get();

        foreach ($oficinas as $oficina) {
            // Vincula a oficina: os models de domínio só enxergam os dados dela.
            app()->instance('oficina.atual', $oficina->id);

            $resumo = $servico->montar();

            if ($resumo['total'] === 0) {
                continue;
            }

            $destinatarios = $oficina->usuarios()
                ->whereNotNull('email_verified_at')
                ->pluck('email')
                ->all();

            if ($destinatarios === []) {
                continue;
            }

            if ($dryRun) {
                $this->line("[teste] {$oficina->nome}: {$resumo['total']} item(ns) para " . implode(', ', $destinatarios));

                continue;
            }

            Mail::to($destinatarios)->send(new ResumoSemanalEmail($oficina, $resumo));

            $enviados++;
        }

        $this->info($dryRun ? 'Teste concluído, nada foi enviado.' : "Resumos enviados: {$enviados}");

        return self::SUCCESS;
    }
}
