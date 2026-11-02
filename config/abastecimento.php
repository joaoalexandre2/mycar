<?php

return [

    /*
     * Faixa de preço por litro (R$) aceita sem aviso. Fora dela o abastecimento é gravado,
     * mas com um alerta para quem confere. Ajuste conforme o mercado da região.
     */
    'preco_litro' => [
        'min' => (float) env('ABASTECIMENTO_PRECO_LITRO_MIN', 2.0),
        'max' => (float) env('ABASTECIMENTO_PRECO_LITRO_MAX', 15.0),
    ],

    /*
     * Quanto o consumo de um abastecimento pode fugir da média do veículo antes de virar alerta.
     * 0.35 = 35% para mais ou para menos.
     */
    'desvio_consumo' => (float) env('ABASTECIMENTO_DESVIO_CONSUMO', 0.35),

];
