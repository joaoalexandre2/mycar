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

    // Alíquota de IPVA (%) usada, de forma APROXIMADA, nos estados sem alíquota
    // confirmada abaixo. Média das alíquotas conhecidas (~2,8%), arredondada.
    'aliquota_media_ipva' => 3.0,

    // IPVA NÃO é cobrado de veículo com MAIS de tantos anos de fabricação, em
    // qualquer estado: Emenda Constitucional 137/2025 (art. 155 da Constituição).
    // "Mais de 20" é estrito: um carro de exatamente 20 anos fica no aviso abaixo,
    // porque as fontes divergem se ele já está isento. Vale para carros de passeio,
    // caminhonetes e veículos de uso misto.
    'idade_isencao_ipva_constitucional' => 20,

    // A partir de quantos anos de fabricação avisamos que o estado PODE isentar o IPVA
    // (alguns estados isentam com 10, 15 ou 18 anos). Só um aviso: a regra de cada
    // estado não está cadastrada aqui. Acima da idade constitucional, a isenção é certa.
    'idade_aviso_isencao_ipva' => 15,

    // Alíquota de IPVA (%) de MOTOCICLETA e de CAMINHÃO por estado, ano-base 2026.
    //
    // Só entra aqui o valor confirmado por fonte oficial (Sefaz de SC, RS, BA, ES, DF,
    // RJ, MG, SP, PE, PA) ou que mais de uma tabela pública trazia igual (Serasa, ValorFinal,
    // CNN Brasil, Numerando) sem que nenhuma outra o contrariasse. Onde as fontes divergem, o
    // estado muda a alíquota por cilindrada ou não achamos fonte, o estado fica de fora e vale
    // a média abaixo (o app avisa "alíquota média do país").
    //
    // Fora dos números: vários estados isentam motos de baixa cilindrada (SC até 200 cc,
    // PR e PA até 170 cc) e a alíquota por cilindrada não está cadastrada; é só estimativa.
    // Caminhonete e picape são "automóvel" (usam a alíquota de carro), não caminhão.
    'ipva_moto' => [
        'BA' => 1.0, 'DF' => 2.0, 'ES' => 1.0, 'GO' => 3.0, 'MG' => 2.0, 'PA' => 1.0, 'RJ' => 2.0,
        'RO' => 2.0, 'RR' => 2.0, 'RS' => 2.0, 'SC' => 1.0, 'SE' => 2.0, 'SP' => 2.0,
    ],

    'ipva_caminhao' => [
        'AC' => 1.0, 'AL' => 1.0, 'AM' => 1.0, 'BA' => 1.0, 'CE' => 1.0, 'DF' => 1.0, 'ES' => 1.0,
        'GO' => 1.25, 'MG' => 1.0, 'MT' => 1.0, 'PA' => 1.0, 'PB' => 1.0, 'PE' => 1.0, 'RN' => 1.0,
        'RO' => 1.0, 'RS' => 1.0, 'SC' => 1.0, 'SE' => 1.0, 'SP' => 1.5,
    ],

    // Média usada nos estados que ficaram de fora das tabelas acima: o valor mais comum
    // entre as fontes (motos 2%, caminhões 1%). APROXIMADO.
    'aliquota_media_ipva_moto' => 2.0,
    'aliquota_media_ipva_caminhao' => 1.0,

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
