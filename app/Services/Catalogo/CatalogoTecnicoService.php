<?php

namespace App\Services\Catalogo;

use App\Models\Veiculo;

class CatalogoTecnicoService
{
    /** @param iterable<CatalogoTecnico> $fontes em ordem de prioridade */
    public function __construct(private iterable $fontes) {}

    /**
     * Primeira fonte que souber responder vence.
     *
     * @return array{fonte: string, dados: array<string, mixed>}|null
     */
    public function fichaPara(Veiculo $veiculo): ?array
    {
        // As fontes de ficha técnica cobrem carros.
        if (!$veiculo->ehCarro()) {
            return null;
        }

        foreach ($this->fontes as $fonte) {
            $dados = $fonte->fichaPara($veiculo);

            if ($dados !== null) {
                return ['fonte' => $fonte->nome(), 'dados' => $dados];
            }
        }

        return null;
    }
}
