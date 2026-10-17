<?php

namespace App\Console\Commands;

use App\Services\PecasAplicacao;
use Illuminate\Console\Command;

/**
 * Mostra, por modelo do catálogo, quantos códigos de peça já foram importados.
 * Serve de lista de pendências para pedir catálogos aos fabricantes.
 */
class CoberturaPecasAplicacao extends Command
{
    protected $signature = 'pecas:cobertura {--sem-codigos : só os modelos que ainda não têm nenhum código}';

    protected $description = 'Lista os modelos do catálogo e quantos códigos de peça cada um já tem.';

    public function handle(PecasAplicacao $aplicacao): int
    {
        $linhas = [];
        $comCodigos = 0;

        foreach (collect(config('pecas_catalogo.modelos'))->pluck(3)->unique()->sort()->values() as $modelo) {
            $total = $aplicacao->total($modelo);
            $comCodigos += $total > 0 ? 1 : 0;

            if ($this->option('sem-codigos') && $total > 0) {
                continue;
            }

            $linhas[] = [$modelo, $total];
        }

        $this->table(['Modelo', 'Códigos'], $linhas);
        $this->info(sprintf('%d de %d modelos com ao menos um código.', $comCodigos, collect(config('pecas_catalogo.modelos'))->pluck(3)->unique()->count()));

        return self::SUCCESS;
    }
}
