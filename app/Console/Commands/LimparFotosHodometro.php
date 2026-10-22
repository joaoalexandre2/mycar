<?php

namespace App\Console\Commands;

use App\Models\UsoVeiculo;
use App\Models\UsoVeiculoFoto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class LimparFotosHodometro extends Command
{
    protected $signature = 'frota:limpar-fotos {--dias=90 : Idade, em dias, a partir da qual a foto sai do disco}';

    protected $description = 'Apaga do disco as fotos de hodômetro com mais de 90 dias; o km e o histórico do uso continuam';

    public function handle(): int
    {
        $limite = now()->subDays((int) $this->option('dias'));
        $apagadas = 0;

        // Roda fora de qualquer login: precisa enxergar as fotos de todas as contas.
        UsoVeiculoFoto::withoutGlobalScopes()
            ->whereNull('apagada_em')
            ->where('created_at', '<', $limite)
            ->orderBy('id')
            ->each(function (UsoVeiculoFoto $foto) use (&$apagadas) {
                Storage::disk('local')->delete($foto->caminho);

                $foto->forceFill(['apagada_em' => now()])->save();

                // O uso deixa de apontar para um arquivo que não existe mais.
                $campo = $foto->momento === 'saida' ? 'foto_saida' : 'foto_chegada';
                UsoVeiculo::withoutGlobalScopes()
                    ->whereKey($foto->uso_veiculo_id)
                    ->where($campo, $foto->caminho)
                    ->update([$campo => null]);

                $apagadas++;
            });

        $this->info("Fotos apagadas: {$apagadas}.");

        return self::SUCCESS;
    }
}
