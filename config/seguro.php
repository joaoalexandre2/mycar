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

    // Moto: o Índice de Preços do Seguro de Moto (IPSM, TEx/Serasa Experian), a mesma série
    // do IPSA do carro, mede o prêmio como % do valor do veículo: 8,7% em abril de 2026 (o
    // menor da série, em fevereiro, também 8,7%; renovações 6,7% e apólices novas 9,6% em
    // janeiro; pico de 10,1% em julho de 2025). Motos baratas (125 cc) custam menos que isso.
    'moto' => [
        'percentual_baixo' => 6.7,
        'percentual_medio' => 8.7,
        'percentual_alto' => 10.1,
    ],

    // Caminhão: sem faixa. As únicas fontes brasileiras são sites de corretoras, sem data nem
    // metodologia, e divergem (3% a 8%, 3% a 10%); não há índice publicado como o IPSA/IPSM.
    // Enquanto não houver fonte confiável, o app não mostra referência para caminhão.

    // Quantos dias antes do fim da apólice o lembrete é enviado (hora de cotar de novo).
    'dias_lembrete' => 45,
];
