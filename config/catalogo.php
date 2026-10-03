<?php

/*
| Fontes de dados técnicos, em ordem de prioridade. Para plugar um catálogo
| comercial, crie uma classe que implemente App\Services\Catalogo\CatalogoTecnico
| e coloque-a ANTES de FichaManualCatalogo (a ficha manual vira o fallback).
*/

return [

    'fontes' => [
        App\Services\Catalogo\FichaManualCatalogo::class,
    ],

];
