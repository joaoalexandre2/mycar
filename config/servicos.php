<?php

/*
|--------------------------------------------------------------------------
| Serviços de manutenção do veículo (perfis pessoa e frota)
|--------------------------------------------------------------------------
|
| Tipos que a pessoa pode registrar. "outro" pede um nome livre. Os
| intervalos de aviso (meses e/ou km) são escolhidos por ela a cada serviço;
| o app só sugere valores comuns na tela.
|
*/

return [
    'tipos' => [
        'oleo' => 'Troca de óleo',
        'bateria' => 'Bateria',
        'palhetas' => 'Palhetas do limpador',
        'filtros' => 'Filtros',
        'pneus' => 'Pneus',
        'freios' => 'Freios',
        'alinhamento' => 'Alinhamento e balanceamento',
        'correia' => 'Correia dentada',
        'outro' => 'Outro',
    ],

    // Avisa por km quando faltar até esta distância para a próxima troca.
    'margem_km' => 1000,

    // Avisa por prazo quando faltar até este número de dias.
    'margem_dias' => 30,
];
