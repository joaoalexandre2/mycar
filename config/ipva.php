<?php

/*
|--------------------------------------------------------------------------
| Calendário de vencimento do IPVA por final de placa
|--------------------------------------------------------------------------
|
| ESTIMATIVA genérica (um mês por final de placa, vencimento no último dia
| do mês). O IPVA real varia por estado: datas, parcelamento (cota única
| ou parcelas) e descontos para pagamento antecipado mudam de uma UF para
| outra e de um ano para o outro. NÃO é fonte oficial — sempre confirme no
| Detran/Sefaz do estado. O sistema mostra essa data como "estimativa".
|
*/

return [

    'meses_por_final_placa' => [
        1 => 1,  // Janeiro
        2 => 2,  // Fevereiro
        3 => 3,  // Março
        4 => 4,  // Abril
        5 => 5,  // Maio
        6 => 6,  // Junho
        7 => 7,  // Julho
        8 => 8,  // Agosto
        9 => 9,  // Setembro
        0 => 10, // Outubro
    ],

];
