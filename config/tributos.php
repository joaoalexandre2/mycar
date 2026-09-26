<?php

/*
|--------------------------------------------------------------------------
| Tributos estaduais de veículos (IPVA e licenciamento anual) — ano-base 2026
|--------------------------------------------------------------------------
|
| Valores conferidos em mais de uma fonte pública (numerando.com.br,
| detranguia.com, garagem360.com.br, despachantedok.com.br). Onde as fontes
| divergiam ou traziam só uma referência, o valor ficou null de propósito:
| o sistema mostra "—" em vez de um número que pode estar errado.
|
| Para completar/corrigir, confirme no site do Detran/Sefaz do estado e edite
| aqui:
|   'ipva'          => alíquota em % sobre o valor FIPE (carro de passeio)
|   'licenciamento' => taxa anual em R$
|
| O IPVA calculado é uma ESTIMATIVA: alguns estados variam a alíquota por
| potência, cilindrada ou faixa de valor.
|
*/

return [

    'ano_base' => 2026,

    'estados' => [
        'AC' => ['ipva' => 2.0,  'licenciamento' => 200.25],
        'AL' => ['ipva' => null, 'licenciamento' => 36.03],
        'AM' => ['ipva' => null, 'licenciamento' => 122.88],
        'AP' => ['ipva' => null, 'licenciamento' => 128.54],
        'BA' => ['ipva' => 2.5,  'licenciamento' => 173.50],
        'CE' => ['ipva' => null, 'licenciamento' => null],
        'DF' => ['ipva' => 3.0,  'licenciamento' => 102.00],
        'ES' => ['ipva' => 2.0,  'licenciamento' => null],
        'GO' => ['ipva' => null, 'licenciamento' => 251.25],
        'MA' => ['ipva' => 2.5,  'licenciamento' => null],
        'MG' => ['ipva' => 4.0,  'licenciamento' => 35.62],
        'MS' => ['ipva' => 3.0,  'licenciamento' => 235.28],
        'MT' => ['ipva' => null, 'licenciamento' => 140.00],
        'PA' => ['ipva' => 2.5,  'licenciamento' => 288.08],
        'PB' => ['ipva' => 2.5,  'licenciamento' => 206.55],
        'PE' => ['ipva' => 2.4,  'licenciamento' => null],
        'PI' => ['ipva' => null, 'licenciamento' => 129.60],
        'PR' => ['ipva' => 1.9,  'licenciamento' => 90.94],
        'RJ' => ['ipva' => 4.0,  'licenciamento' => null],
        'RN' => ['ipva' => 3.0,  'licenciamento' => null],
        'RO' => ['ipva' => null, 'licenciamento' => 220.41],
        'RR' => ['ipva' => 3.0,  'licenciamento' => null],
        'RS' => ['ipva' => 3.0,  'licenciamento' => 109.27],
        'SC' => ['ipva' => 2.0,  'licenciamento' => null],
        'SE' => ['ipva' => null, 'licenciamento' => 207.36],
        'SP' => ['ipva' => 4.0,  'licenciamento' => null],
        'TO' => ['ipva' => null, 'licenciamento' => 79.63],
    ],

];
