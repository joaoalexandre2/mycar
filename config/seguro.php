<?php

/*
|--------------------------------------------------------------------------
| Faixa de referência do seguro do carro
|--------------------------------------------------------------------------
|
| REFERÊNCIA, não cotação. O preço real depende do CEP de pernoite, da
| idade e do histórico do condutor, do bônus, da franquia e das coberturas,
| e só a seguradora calcula. Aqui usamos o percentual do valor FIPE que o
| mercado costuma cobrar por ano (faixas publicadas em guias de mercado de
| 2026: de 3% a 8%, com o IPSA em 4,7% em janeiro de 2026) só para a pessoa
| ter uma ideia se uma proposta está cara ou barata. Ajuste aqui se o
| mercado mudar.
|
*/

return [
    'percentual_baixo' => 3.0,
    'percentual_medio' => 4.7,
    'percentual_alto' => 8.0,

    // Quantos dias antes do fim da apólice o lembrete é enviado (hora de cotar de novo).
    'dias_lembrete' => 45,
];
