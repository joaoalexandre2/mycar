<?php

/*
|--------------------------------------------------------------------------
| Vencimento do IPVA
|--------------------------------------------------------------------------
|
| O IPVA é cobrado uma vez por ano e, na cota única (a que costuma ter
| desconto), vence em janeiro. O sistema assume o último dia de janeiro
| para todos os veículos, qualquer que seja o final da placa. É uma
| ESTIMATIVA: parcelamento, datas e descontos variam por estado e por ano.
| Confirme sempre no Detran/Sefaz do estado.
|
*/

return [

    'mes_vencimento' => 1, // Janeiro

];
