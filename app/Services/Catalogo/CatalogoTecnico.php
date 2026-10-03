<?php

namespace App\Services\Catalogo;

use App\Models\Veiculo;

/**
 * Fonte de dados técnicos (óleo, filtros, pneus) de um veículo. Cada fonte
 * — ficha manual hoje, catálogo comercial amanhã — implementa esta
 * interface; o CatalogoTecnicoService consulta as fontes em ordem.
 */
interface CatalogoTecnico
{
    /** Identificador gravado na resposta (ex.: "manual"). */
    public function nome(): string;

    /**
     * Dados técnicos do veículo, ou null se esta fonte não tem nada para ele.
     *
     * @return array<string, mixed>|null
     */
    public function fichaPara(Veiculo $veiculo): ?array;
}
