<?php

namespace App\Services\Catalogo;

use App\Models\FichaTecnica;
use App\Models\Veiculo;

class FichaManualCatalogo implements CatalogoTecnico
{
    public function nome(): string
    {
        return 'manual';
    }

    public function fichaPara(Veiculo $veiculo): ?array
    {
        $ficha = FichaTecnica::where('veiculo_id', $veiculo->id)->first();

        return $ficha ? $ficha->only(FichaTecnica::CAMPOS) : null;
    }
}
