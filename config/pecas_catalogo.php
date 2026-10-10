<?php

/*
|--------------------------------------------------------------------------
| Catálogo de peças (perfis pessoa e frota)
|--------------------------------------------------------------------------
|
| O que ESTE catálogo é: a lista dos TIPOS de peça de um carro (amortecedor,
| coifa da homocinética, pastilha...) por sistema, com posição, um intervalo
| típico de troca e observações. Cada modelo é ligado a uma categoria de
| carroceria, e as peças aparecem conforme essa categoria.
|
| O que NÃO é: não traz código de fabricante (OEM), nem a compatibilidade
| exata por ano/motor/versão. Isso muda de versão para versão e só se
| confirma pelo chassi ou pelo código do fabricante. A tela avisa isso.
|
| Os modelos são os campeões de venda do Brasil nos últimos ~15 anos
| (rankings da Fenabrave, de memória, não consultados em tempo real).
| Para incluir um modelo, basta uma linha em "modelos".
|
| Tudo é array simples (sem closures) para funcionar com config:cache.
|
*/

return [

    // Categorias de carroceria usadas para decidir quais peças valem.
    'categorias' => [
        'hatch' => 'Hatch',
        'sedan' => 'Sedã',
        'suv' => 'SUV / utilitário esportivo',
        'picape' => 'Picape',
        'minivan' => 'Minivan / monovolume',
        'utilitario' => 'Furgão / utilitário',
    ],

    // Ordem em que os sistemas aparecem na tela.
    'sistemas' => [
        'motor' => 'Motor',
        'alimentacao' => 'Alimentação e filtros',
        'arrefecimento' => 'Arrefecimento',
        'transmissao' => 'Transmissão e embreagem',
        'suspensao' => 'Suspensão',
        'direcao' => 'Direção',
        'freios' => 'Freios',
        'eletrica' => 'Elétrica e iluminação',
        'climatizacao' => 'Ar-condicionado e ventilação',
        'escapamento' => 'Escapamento',
        'carroceria' => 'Carroceria e vidros',
        'pneus' => 'Pneus e rodas',
    ],

    /*
    | Modelos: [marcas (separadas por |), palavras do modelo (todas precisam
    | aparecer no nome do veículo), categoria, nome para exibir].
    | Vale o mais específico (mais palavras): "Corolla Cross" ganha de "Corolla".
    */
    'modelos' => [
        // Fiat
        ['fiat', 'uno', 'hatch', 'Fiat Uno'],
        ['fiat', 'palio', 'hatch', 'Fiat Palio'],
        ['fiat', 'siena', 'sedan', 'Fiat Siena'],
        ['fiat', 'grand siena', 'sedan', 'Fiat Grand Siena'],
        ['fiat', 'strada', 'picape', 'Fiat Strada'],
        ['fiat', 'toro', 'picape', 'Fiat Toro'],
        ['fiat', 'mobi', 'hatch', 'Fiat Mobi'],
        ['fiat', 'argo', 'hatch', 'Fiat Argo'],
        ['fiat', 'cronos', 'sedan', 'Fiat Cronos'],
        ['fiat', 'punto', 'hatch', 'Fiat Punto'],
        ['fiat', 'idea', 'minivan', 'Fiat Idea'],
        ['fiat', 'fiorino', 'utilitario', 'Fiat Fiorino'],
        ['fiat', 'doblo', 'minivan', 'Fiat Doblò'],
        ['fiat', 'pulse', 'suv', 'Fiat Pulse'],
        ['fiat', 'fastback', 'suv', 'Fiat Fastback'],

        // Volkswagen
        ['volkswagen|vw', 'gol', 'hatch', 'Volkswagen Gol'],
        ['volkswagen|vw', 'voyage', 'sedan', 'Volkswagen Voyage'],
        ['volkswagen|vw', 'fox', 'hatch', 'Volkswagen Fox'],
        ['volkswagen|vw', 'polo', 'hatch', 'Volkswagen Polo'],
        ['volkswagen|vw', 'virtus', 'sedan', 'Volkswagen Virtus'],
        ['volkswagen|vw', 'up', 'hatch', 'Volkswagen up!'],
        ['volkswagen|vw', 'saveiro', 'picape', 'Volkswagen Saveiro'],
        ['volkswagen|vw', 'amarok', 'picape', 'Volkswagen Amarok'],
        ['volkswagen|vw', 't cross', 'suv', 'Volkswagen T-Cross'],
        ['volkswagen|vw', 'nivus', 'suv', 'Volkswagen Nivus'],
        ['volkswagen|vw', 'taos', 'suv', 'Volkswagen Taos'],
        ['volkswagen|vw', 'tiguan', 'suv', 'Volkswagen Tiguan'],
        ['volkswagen|vw', 'jetta', 'sedan', 'Volkswagen Jetta'],
        ['volkswagen|vw', 'golf', 'hatch', 'Volkswagen Golf'],
        ['volkswagen|vw', 'kombi', 'utilitario', 'Volkswagen Kombi'],

        // Chevrolet
        ['chevrolet|gm', 'onix', 'hatch', 'Chevrolet Onix'],
        ['chevrolet|gm', 'onix plus', 'sedan', 'Chevrolet Onix Plus'],
        ['chevrolet|gm', 'prisma', 'sedan', 'Chevrolet Prisma'],
        ['chevrolet|gm', 'celta', 'hatch', 'Chevrolet Celta'],
        ['chevrolet|gm', 'classic', 'sedan', 'Chevrolet Classic'],
        ['chevrolet|gm', 'corsa', 'hatch', 'Chevrolet Corsa'],
        ['chevrolet|gm', 'agile', 'hatch', 'Chevrolet Agile'],
        ['chevrolet|gm', 's10', 'picape', 'Chevrolet S10'],
        ['chevrolet|gm', 'montana', 'picape', 'Chevrolet Montana'],
        ['chevrolet|gm', 'tracker', 'suv', 'Chevrolet Tracker'],
        ['chevrolet|gm', 'equinox', 'suv', 'Chevrolet Equinox'],
        ['chevrolet|gm', 'cruze', 'sedan', 'Chevrolet Cruze'],
        ['chevrolet|gm', 'cobalt', 'sedan', 'Chevrolet Cobalt'],
        ['chevrolet|gm', 'spin', 'minivan', 'Chevrolet Spin'],

        // Ford
        ['ford', 'ka', 'hatch', 'Ford Ka'],
        ['ford', 'ka sedan', 'sedan', 'Ford Ka Sedan'],
        ['ford', 'fiesta', 'hatch', 'Ford Fiesta'],
        ['ford', 'focus', 'hatch', 'Ford Focus'],
        ['ford', 'fusion', 'sedan', 'Ford Fusion'],
        ['ford', 'ecosport', 'suv', 'Ford EcoSport'],
        ['ford', 'ranger', 'picape', 'Ford Ranger'],
        ['ford', 'territory', 'suv', 'Ford Territory'],

        // Hyundai
        ['hyundai', 'hb20', 'hatch', 'Hyundai HB20'],
        ['hyundai', 'hb20s', 'sedan', 'Hyundai HB20S'],
        ['hyundai', 'creta', 'suv', 'Hyundai Creta'],
        ['hyundai', 'ix35', 'suv', 'Hyundai ix35'],
        ['hyundai', 'tucson', 'suv', 'Hyundai Tucson'],
        ['hyundai', 'i30', 'hatch', 'Hyundai i30'],

        // Renault
        ['renault', 'kwid', 'hatch', 'Renault Kwid'],
        ['renault', 'sandero', 'hatch', 'Renault Sandero'],
        ['renault', 'logan', 'sedan', 'Renault Logan'],
        ['renault', 'duster', 'suv', 'Renault Duster'],
        ['renault', 'oroch', 'picape', 'Renault Duster Oroch'],
        ['renault', 'captur', 'suv', 'Renault Captur'],
        ['renault', 'clio', 'hatch', 'Renault Clio'],
        ['renault', 'kangoo', 'utilitario', 'Renault Kangoo'],

        // Toyota
        ['toyota', 'corolla', 'sedan', 'Toyota Corolla'],
        ['toyota', 'corolla cross', 'suv', 'Toyota Corolla Cross'],
        ['toyota', 'hilux', 'picape', 'Toyota Hilux'],
        ['toyota', 'etios', 'hatch', 'Toyota Etios'],
        ['toyota', 'yaris', 'hatch', 'Toyota Yaris'],
        ['toyota', 'sw4', 'suv', 'Toyota SW4'],

        // Honda
        ['honda', 'civic', 'sedan', 'Honda Civic'],
        ['honda', 'fit', 'hatch', 'Honda Fit'],
        ['honda', 'city', 'sedan', 'Honda City'],
        ['honda', 'hr v', 'suv', 'Honda HR-V'],
        ['honda', 'wr v', 'suv', 'Honda WR-V'],

        // Jeep
        ['jeep', 'renegade', 'suv', 'Jeep Renegade'],
        ['jeep', 'compass', 'suv', 'Jeep Compass'],
        ['jeep', 'commander', 'suv', 'Jeep Commander'],

        // Nissan e Mitsubishi
        ['nissan', 'kicks', 'suv', 'Nissan Kicks'],
        ['nissan', 'march', 'hatch', 'Nissan March'],
        ['nissan', 'versa', 'sedan', 'Nissan Versa'],
        ['nissan', 'frontier', 'picape', 'Nissan Frontier'],
        ['mitsubishi', 'l200', 'picape', 'Mitsubishi L200'],
        ['mitsubishi', 'pajero', 'suv', 'Mitsubishi Pajero'],

        // Peugeot e Citroën
        ['peugeot', '208', 'hatch', 'Peugeot 208'],
        ['peugeot', '207', 'hatch', 'Peugeot 207'],
        ['citroen', 'c3', 'hatch', 'Citroën C3'],
        ['citroen', 'c3 aircross', 'suv', 'Citroën C3 Aircross'],
        ['citroen', 'aircross', 'suv', 'Citroën Aircross'],
        ['citroen', 'c4 cactus', 'suv', 'Citroën C4 Cactus'],
        ['citroen', 'c4 lounge', 'sedan', 'Citroën C4 Lounge'],
        ['citroen', 'c4', 'hatch', 'Citroën C4'],
        ['citroen', 'basalt', 'suv', 'Citroën Basalt'],
        ['peugeot', '2008', 'suv', 'Peugeot 2008'],
        ['peugeot', '308', 'hatch', 'Peugeot 308'],

        // BMW (nomes como "320iA 2.0", "X1 sDrive20i": 320* casa 320i, 320iA...)
        ['bmw', '118*', 'hatch', 'BMW Série 1 (118i)'],
        ['bmw', '120*', 'hatch', 'BMW Série 1 (120i)'],
        ['bmw', '320*', 'sedan', 'BMW Série 3 (320i)'],
        ['bmw', '328*', 'sedan', 'BMW Série 3 (328i)'],
        ['bmw', '330*', 'sedan', 'BMW Série 3 (330i)'],
        ['bmw', 'x1', 'suv', 'BMW X1'],
        ['bmw', 'x3', 'suv', 'BMW X3'],
        ['bmw', 'x5', 'suv', 'BMW X5'],

        // Audi
        ['audi', 'a3', 'hatch', 'Audi A3'],
        ['audi', 'a3 sedan', 'sedan', 'Audi A3 Sedan'],
        ['audi', 'a4', 'sedan', 'Audi A4'],
        ['audi', 'a5', 'sedan', 'Audi A5'],
        ['audi', 'q3', 'suv', 'Audi Q3'],
        ['audi', 'q5', 'suv', 'Audi Q5'],
        ['audi', 'q7', 'suv', 'Audi Q7'],

        // Mais modelos das demais marcas pedidas
        ['volkswagen|vw', 'spacefox', 'minivan', 'Volkswagen SpaceFox'],
        ['volkswagen|vw', 'crossfox', 'hatch', 'Volkswagen CrossFox'],
        ['volkswagen|vw', 'parati', 'utilitario', 'Volkswagen Parati'],
        ['volkswagen|vw', 'santana', 'sedan', 'Volkswagen Santana'],
        ['volkswagen|vw', 'passat', 'sedan', 'Volkswagen Passat'],
        ['volkswagen|vw', 'novo beetle', 'hatch', 'Volkswagen New Beetle'],
        ['chevrolet|gm', 'joy', 'hatch', 'Chevrolet Joy'],
        ['chevrolet|gm', 'vectra', 'sedan', 'Chevrolet Vectra'],
        ['chevrolet|gm', 'astra', 'hatch', 'Chevrolet Astra'],
        ['chevrolet|gm', 'zafira', 'minivan', 'Chevrolet Zafira'],
        ['chevrolet|gm', 'meriva', 'minivan', 'Chevrolet Meriva'],
        ['chevrolet|gm', 'captiva', 'suv', 'Chevrolet Captiva'],
        ['chevrolet|gm', 'trailblazer', 'suv', 'Chevrolet Trailblazer'],
        ['chevrolet|gm', 'blazer', 'suv', 'Chevrolet Blazer'],
        ['ford', 'maverick', 'picape', 'Ford Maverick'],
        ['ford', 'courier', 'picape', 'Ford Courier'],
        ['ford', 'edge', 'suv', 'Ford Edge'],
        ['ford', 'fiesta sedan', 'sedan', 'Ford Fiesta Sedan'],
        ['hyundai', 'hb20x', 'hatch', 'Hyundai HB20X'],
        ['hyundai', 'azera', 'sedan', 'Hyundai Azera'],
        ['hyundai', 'veloster', 'hatch', 'Hyundai Veloster'],
        ['renault', 'fluence', 'sedan', 'Renault Fluence'],
        ['renault', 'master', 'utilitario', 'Renault Master'],
        ['renault', 'megane', 'sedan', 'Renault Mégane'],
        ['renault', 'stepway', 'hatch', 'Renault Sandero Stepway'],
    ],

    // Título da página na Wikipédia quando difere do nome do modelo (para `modelos:raspar-fichas`).
    'wikipedia' => [
        'BMW Série 1 (118i)' => 'BMW Série 1',
        'BMW Série 1 (120i)' => 'BMW Série 1',
        'BMW Série 3 (320i)' => 'BMW Série 3',
        'BMW Série 3 (328i)' => 'BMW Série 3',
        'BMW Série 3 (330i)' => 'BMW Série 3',
        'Audi A3 Sedan' => 'Audi A3',
    ],

    // Título da página usado só para a FOTO (`modelos:raspar-imagens`), quando a página
    // principal do modelo não tem imagem ou mostra outro carro.
    'wikipedia_imagem' => [
        'BMW Série 3 (320i)' => 'BMW Série 3 (F30)',
        'BMW Série 3 (328i)' => 'BMW Série 3 (F30)',
        'BMW Série 3 (330i)' => 'BMW Série 3 (G20)',
        'Ford Ka Sedan' => 'Ford Ka',
        'Ford Fiesta Sedan' => 'Ford Fiesta',
        'Renault Sandero Stepway' => 'Renault Sandero',
    ],

    // Marca da peça original de cada montadora (chave = primeira marca do modelo).
    'originais' => [
        'fiat' => 'Mopar',
        'jeep' => 'Mopar',
        'chevrolet' => 'ACDelco',
        'ford' => 'Motorcraft',
        'hyundai' => 'Mobis',
        'volkswagen' => 'Peças originais Volkswagen',
        'renault' => 'Peças originais Renault',
        'toyota' => 'Toyota Genuine Parts',
        'honda' => 'Honda Genuine Parts',
        'nissan' => 'Nissan Genuine Parts',
        'mitsubishi' => 'Peças originais Mitsubishi',
        'peugeot' => 'Peças originais Peugeot',
        'citroen' => 'Peças originais Citroën',
        'bmw' => 'BMW Original Parts',
        'audi' => 'Audi Genuine Parts',
    ],

    /*
    | Marcas de reposição comuns no mercado brasileiro, por peça. São marcas
    | que fabricam esse tipo de peça, NÃO uma garantia de que servem no seu
    | carro: o código certo se confirma no catálogo do fabricante.
    | Cada grupo: nomes das peças (iguais aos de "pecas") => marcas.
    */
    'marcas' => [
        [['Amortecedor dianteiro', 'Amortecedor traseiro', 'Kit coifa e batente do amortecedor (dianteiro)', 'Kit coifa e batente do amortecedor (traseiro)'], ['Cofap', 'Monroe', 'Nakata', 'Sachs', 'KYB']],
        [['Coxim e rolamento do amortecedor', 'Coxim do motor', 'Coxim do câmbio'], ['Nakata', 'Sampel', 'Monroe']],
        [['Pastilhas de freio dianteiras', 'Pastilhas de freio traseiras'], ['Cobreq', 'Fras-le', 'Bosch', 'TRW', 'Ferodo']],
        [['Discos de freio dianteiros', 'Discos de freio traseiros', 'Tambor de freio traseiro'], ['Fremax', 'Hipper Freios', 'TRW', 'Bosch']],
        [['Lonas de freio traseiras', 'Cilindro de roda'], ['Fras-le', 'Cobreq', 'TRW']],
        [['Fluido de freio'], ['Bosch', 'Varga', 'Motul', 'Castrol']],
        [['Cilindro mestre', 'Pinça de freio', 'Kit de reparo da pinça', 'Servo-freio (hidrovácuo)'], ['TRW', 'Bosch', 'ATE']],
        [['Filtro de óleo', 'Filtro de ar do motor', 'Filtro de combustível', 'Filtro de cabine (ar-condicionado)'], ['Mann-Filter', 'Tecfil', 'Fram', 'Wega', 'Mahle']],
        [['Óleo do motor', 'Óleo do câmbio'], ['Mobil', 'Castrol', 'Shell', 'Petronas', 'Lubrax']],
        [['Velas de ignição', 'Bobina de ignição'], ['NGK', 'Bosch', 'Denso']],
        [['Sonda lambda', 'Sensor de rotação', 'Sensor ABS', 'Sensor de temperatura'], ['Bosch', 'NGK', 'Denso', 'Magneti Marelli']],
        [['Bico injetor', 'Bomba de combustível'], ['Bosch', 'Delphi', 'Magneti Marelli']],
        [['Kit da correia dentada', 'Correia do alternador (poli-V)', 'Tensor da correia poli-V'], ['Gates', 'Continental', 'Dayco', 'SKF']],
        [['Bomba d\'água', 'Válvula termostática'], ['Urba', 'Nakata', 'SKF', 'Valeo', 'Wahler']],
        [['Radiador', 'Eletroventilador'], ['Valeo', 'Visconde', 'Mahle']],
        [['Aditivo do radiador'], ['Paraflu', 'Radiex', 'Havoline', 'Shell']],
        [['Kit de embreagem', 'Cabo ou atuador da embreagem'], ['LUK', 'Sachs', 'Valeo']],
        [['Junta homocinética (lado roda)', 'Junta homocinética (lado câmbio)', 'Coifa da homocinética (lado roda)', 'Coifa da homocinética (lado câmbio)', 'Semieixo'], ['GKN', 'Nakata', 'Fric-Rot']],
        [['Pivô de suspensão', 'Bandeja (braço oscilante) dianteira', 'Buchas da bandeja', 'Bieleta da barra estabilizadora', 'Buchas da barra estabilizadora', 'Barra estabilizadora', 'Terminal de direção', 'Barra axial'], ['Nakata', 'Viemar', 'TRW', 'Fric-Rot']],
        [['Rolamento de roda dianteiro', 'Rolamento de roda traseiro', 'Cubo de roda'], ['SKF', 'FAG', 'Timken', 'NSK']],
        [['Caixa de direção', 'Coifa da caixa de direção', 'Bomba da direção hidráulica'], ['TRW', 'ZF', 'Nakata']],
        [['Fluido da direção hidráulica'], ['Bosch', 'Castrol', 'Mobil']],
        [['Bateria'], ['Moura', 'Heliar', 'Bosch', 'Jupiter']],
        [['Alternador', 'Motor de partida'], ['Bosch', 'Valeo', 'Denso']],
        [['Lâmpadas dos faróis', 'Lâmpadas de lanterna e freio'], ['Osram', 'Philips', 'Hella']],
        [['Palhetas do limpador (dianteiras)', 'Palheta do limpador traseiro'], ['Bosch', 'Dyna', 'Valeo', 'Trico']],
        [['Silencioso traseiro', 'Catalisador', 'Flexível do escapamento'], ['Mastra', 'Walker', 'Bosal']],
        [['Compressor do ar-condicionado', 'Condensador do ar-condicionado'], ['Denso', 'Valeo', 'Sanden', 'Mahle']],
        [['Pneus'], ['Pirelli', 'Goodyear', 'Michelin', 'Bridgestone', 'Continental', 'Firestone', 'Dunlop']],
    ],

    /*
    | Peças: [sistema, nome, termos de busca (sinônimos), posição, intervalo
    | típico em km (null = não é por km), observação, categorias (null = todas)].
    | Os intervalos são típicos e variam por modelo e uso: vale o manual.
    */
    'pecas' => [
        // ------------------------------------------------------------ Motor
        ['motor', 'Óleo do motor', 'lubrificante oleo 5w30 10w40 troca de oleo', null, 10000, 'Use a viscosidade e a especificação do manual.', null],
        ['motor', 'Filtro de óleo', 'filtro oleo', null, 10000, 'Troca junto com o óleo do motor.', null],
        ['motor', 'Velas de ignição', 'vela ignicao', null, 40000, 'O tipo depende do motor (iridium, níquel...).', null],
        ['motor', 'Bobina de ignição', 'bobina cabo de vela', null, null, 'Falha costuma aparecer como motor falhando.', null],
        ['motor', 'Kit da correia dentada', 'correia dentada kit distribuicao tensor', null, 60000, 'Alguns motores usam corrente de comando em vez de correia: confira no manual.', null],
        ['motor', 'Correia do alternador (poli-V)', 'correia poli v acessorios alternador', null, 50000, null, null],
        ['motor', 'Tensor da correia poli-V', 'tensor correia acessorios', null, 60000, null, null],
        ['motor', 'Junta da tampa de válvulas', 'junta tampa valvulas vazamento de oleo', null, null, 'Vazamento de óleo no alto do motor.', null],
        ['motor', 'Junta do cabeçote', 'junta cabecote', null, null, 'Troca em retífica ou superaquecimento.', null],
        ['motor', 'Retentor do virabrequim', 'retentor virabrequim vazamento', null, null, null, null],
        ['motor', 'Coxim do motor', 'coxim calco suporte motor', null, null, 'Vibração e batida ao acelerar ou frear.', null],
        ['motor', 'Sonda lambda', 'sensor oxigenio lambda', null, 60000, null, null],
        ['motor', 'Sensor de rotação', 'sensor rotacao virabrequim', null, null, null, null],
        ['motor', 'Bico injetor', 'injetor combustivel limpeza', null, null, 'Limpeza periódica costuma bastar.', null],
        ['motor', 'Corpo de borboleta (TBI)', 'corpo borboleta tbi limpeza', null, null, null, null],

        // ----------------------------------------------- Alimentação e filtros
        ['alimentacao', 'Filtro de ar do motor', 'filtro ar motor', null, 15000, null, null],
        ['alimentacao', 'Filtro de combustível', 'filtro combustivel gasolina diesel', null, 20000, 'Em diesel, vale também o filtro separador de água.', null],
        ['alimentacao', 'Bomba de combustível', 'bomba combustivel', null, null, 'Costuma ficar dentro do tanque.', null],
        ['alimentacao', 'Mangueiras de combustível', 'mangueira combustivel', null, null, null, null],

        // -------------------------------------------------------- Arrefecimento
        ['arrefecimento', 'Radiador', 'radiador', null, null, null, null],
        ['arrefecimento', 'Aditivo do radiador', 'aditivo agua radiador liquido arrefecimento', null, 40000, 'Troca por prazo (cerca de 2 anos) ou km.', null],
        ['arrefecimento', 'Bomba d\'água', 'bomba agua', null, 60000, 'Muitos mecânicos trocam junto com a correia dentada.', null],
        ['arrefecimento', 'Válvula termostática', 'termostatica valvula termostato', null, 60000, null, null],
        ['arrefecimento', 'Reservatório de expansão', 'reservatorio expansao agua tanque', null, null, null, null],
        ['arrefecimento', 'Tampa do radiador', 'tampa radiador reservatorio', null, null, null, null],
        ['arrefecimento', 'Eletroventilador', 'eletroventilador ventoinha ventilador radiador', null, null, null, null],
        ['arrefecimento', 'Mangueiras do radiador', 'mangueira radiador agua', null, null, null, null],
        ['arrefecimento', 'Sensor de temperatura', 'sensor temperatura agua cavalete', null, null, null, null],

        // ----------------------------------------------------- Transmissão
        ['transmissao', 'Kit de embreagem', 'embreagem disco platô plato rolamento kit', null, 60000, 'Disco, platô e rolamento, em geral trocados juntos.', null],
        ['transmissao', 'Cabo ou atuador da embreagem', 'cabo embreagem atuador cilindro', null, null, null, null],
        ['transmissao', 'Óleo do câmbio', 'oleo cambio transmissao fluido atf', null, 60000, 'Manual e automático usam fluidos diferentes.', null],
        ['transmissao', 'Junta homocinética (lado roda)', 'homocinetica junta semieixo', 'dianteiro', null, 'Estalo ao fazer curva é sintoma típico.', null],
        ['transmissao', 'Junta homocinética (lado câmbio)', 'homocinetica tulipa junta semieixo', 'dianteiro', null, null, null],
        ['transmissao', 'Coifa da homocinética (lado roda)', 'coifa homocinetica capa protetora borracha', 'dianteiro', null, 'Rasgou? Troque logo: a graxa vaza e a junta quebra.', null],
        ['transmissao', 'Coifa da homocinética (lado câmbio)', 'coifa homocinetica capa protetora borracha', 'dianteiro', null, null, null],
        ['transmissao', 'Semieixo', 'semi eixo semieixo', 'dianteiro', null, null, null],
        ['transmissao', 'Coxim do câmbio', 'coxim cambio calco suporte', null, null, null, null],
        ['transmissao', 'Cardã e cruzeta', 'cardan cardan cruzeta junta universal', 'traseiro', null, 'Em picapes e SUVs com tração traseira ou 4x4.', ['picape', 'suv', 'utilitario']],
        ['transmissao', 'Óleo do diferencial', 'oleo diferencial', 'traseiro', 60000, 'Em veículos com diferencial traseiro (picapes, SUVs 4x4).', ['picape', 'suv', 'utilitario']],

        // -------------------------------------------------------- Suspensão
        ['suspensao', 'Amortecedor dianteiro', 'amortecedor', 'dianteiro', 70000, 'Troque em par (esquerdo e direito).', null],
        ['suspensao', 'Amortecedor traseiro', 'amortecedor', 'traseiro', 70000, 'Troque em par (esquerdo e direito).', null],
        ['suspensao', 'Kit coifa e batente do amortecedor (dianteiro)', 'coifa amortecedor batente kit reparo guarda po', 'dianteiro', 70000, 'Vem junto na troca do amortecedor.', null],
        ['suspensao', 'Kit coifa e batente do amortecedor (traseiro)', 'coifa amortecedor batente kit reparo guarda po', 'traseiro', 70000, null, null],
        ['suspensao', 'Coxim e rolamento do amortecedor', 'coxim amortecedor rolamento axial batente topo', 'dianteiro', 70000, 'Barulho ao esterçar ou passar em buracos.', null],
        ['suspensao', 'Mola helicoidal dianteira', 'mola helicoidal espiral', 'dianteiro', null, 'Troque em par.', null],
        ['suspensao', 'Mola ou feixe de molas traseiro', 'mola helicoidal feixe molas espiral', 'traseiro', null, 'Varia conforme o modelo: helicoidal ou feixe de molas.', null],
        ['suspensao', 'Bandeja (braço oscilante) dianteira', 'bandeja braco oscilante balanca', 'dianteiro', null, null, null],
        ['suspensao', 'Pivô de suspensão', 'pivo suspensao rotula', 'dianteiro', null, 'Folga causa barulho e desgaste irregular do pneu.', null],
        ['suspensao', 'Buchas da bandeja', 'bucha bandeja braco', 'dianteiro', null, null, null],
        ['suspensao', 'Barra estabilizadora', 'barra estabilizadora', 'dianteiro', null, null, null],
        ['suspensao', 'Bieleta da barra estabilizadora', 'bieleta barra estabilizadora', 'dianteiro', null, 'Estalos em buracos são sintoma comum.', null],
        ['suspensao', 'Buchas da barra estabilizadora', 'bucha barra estabilizadora', 'dianteiro', null, null, null],
        ['suspensao', 'Rolamento de roda dianteiro', 'rolamento roda cubo dianteiro', 'dianteiro', null, 'Zumbido que aumenta com a velocidade.', null],
        ['suspensao', 'Rolamento de roda traseiro', 'rolamento roda cubo traseiro', 'traseiro', null, null, null],
        ['suspensao', 'Cubo de roda', 'cubo roda', null, null, null, null],

        // ----------------------------------------------------------- Direção
        ['direcao', 'Terminal de direção', 'terminal direcao ponteira barra', null, null, 'Folga no volante e desgaste dos pneus.', null],
        ['direcao', 'Barra axial', 'barra axial articulacao axial caixa direcao', null, null, null, null],
        ['direcao', 'Caixa de direção', 'caixa direcao setor', null, null, null, null],
        ['direcao', 'Coifa da caixa de direção', 'coifa caixa direcao capa borracha', null, null, 'Vazamento de óleo ou graxa na caixa de direção.', null],
        ['direcao', 'Bomba da direção hidráulica', 'bomba direcao hidraulica', null, null, 'Nem todo modelo tem: muitos usam direção elétrica.', null],
        ['direcao', 'Fluido da direção hidráulica', 'fluido oleo direcao hidraulica', null, 40000, 'Só em direção hidráulica.', null],
        ['direcao', 'Mangueiras da direção hidráulica', 'mangueira direcao hidraulica', null, null, null, null],

        // ------------------------------------------------------------ Freios
        ['freios', 'Pastilhas de freio dianteiras', 'pastilha freio', 'dianteiro', 30000, 'Troque o jogo completo do eixo.', null],
        ['freios', 'Pastilhas de freio traseiras', 'pastilha freio', 'traseiro', 40000, 'Só em carros com freio a disco atrás.', null],
        ['freios', 'Discos de freio dianteiros', 'disco freio', 'dianteiro', 50000, 'Troque em par.', null],
        ['freios', 'Discos de freio traseiros', 'disco freio', 'traseiro', 60000, null, null],
        ['freios', 'Lonas de freio traseiras', 'lona freio tambor sapata', 'traseiro', 50000, 'Em carros com freio a tambor atrás.', null],
        ['freios', 'Tambor de freio traseiro', 'tambor freio', 'traseiro', null, null, null],
        ['freios', 'Fluido de freio', 'fluido freio dot 4 oleo', null, 20000, 'Troca por prazo: a cada 1 a 2 anos.', null],
        ['freios', 'Cilindro mestre', 'cilindro mestre freio', null, null, null, null],
        ['freios', 'Cilindro de roda', 'cilindro roda freio tambor', 'traseiro', null, null, null],
        ['freios', 'Pinça de freio', 'pinca freio caliper', null, null, null, null],
        ['freios', 'Kit de reparo da pinça', 'reparo pinca freio kit coifa', null, null, null, null],
        ['freios', 'Flexível (mangueira) de freio', 'flexivel mangueira freio', null, null, null, null],
        ['freios', 'Cabo do freio de mão', 'cabo freio mao estacionamento', null, null, null, null],
        ['freios', 'Servo-freio (hidrovácuo)', 'servo freio hidrovacuo', null, null, null, null],
        ['freios', 'Sensor ABS', 'sensor abs roda', null, null, null, null],

        // --------------------------------------------------- Elétrica e luz
        ['eletrica', 'Bateria', 'bateria 12v', null, null, 'Dura em média de 2 a 4 anos.', null],
        ['eletrica', 'Alternador', 'alternador carga', null, null, null, null],
        ['eletrica', 'Motor de partida', 'motor partida arranque', null, null, null, null],
        ['eletrica', 'Lâmpadas dos faróis', 'lampada farol h4 h7 led xenon', null, null, 'O tipo (H4, H7, LED) está no manual.', null],
        ['eletrica', 'Lâmpadas de lanterna e freio', 'lampada lanterna freio luz re pisca', null, null, null, null],
        ['eletrica', 'Farol', 'farol dianteiro conjunto', null, null, null, null],
        ['eletrica', 'Lanterna traseira', 'lanterna traseira conjunto', null, null, null, null],
        ['eletrica', 'Interruptor da luz de freio', 'interruptor luz freio pedal', null, null, null, null],
        ['eletrica', 'Fusíveis e relés', 'fusivel rele caixa', null, null, null, null],
        ['eletrica', 'Motor do vidro elétrico', 'motor vidro eletrico maquina vidro', null, null, null, null],
        ['eletrica', 'Buzina', 'buzina', null, null, null, null],

        // ---------------------------------------------- Ar-condicionado
        ['climatizacao', 'Filtro de cabine (ar-condicionado)', 'filtro cabine ar condicionado antipolen', null, 15000, 'Troque junto com a revisão ou antes se houver mau cheiro.', null],
        ['climatizacao', 'Compressor do ar-condicionado', 'compressor ar condicionado', null, null, null, null],
        ['climatizacao', 'Condensador do ar-condicionado', 'condensador ar condicionado', null, null, null, null],
        ['climatizacao', 'Recarga de gás do ar-condicionado', 'gas ar condicionado recarga r134a', null, null, 'Perda de gás indica vazamento.', null],
        ['climatizacao', 'Motor da ventilação interna', 'motor ventilador interno ar condicionado caixa', null, null, null, null],

        // ------------------------------------------------------ Escapamento
        ['escapamento', 'Silencioso traseiro', 'silencioso escapamento abafador', 'traseiro', null, null, null],
        ['escapamento', 'Catalisador', 'catalisador escapamento', null, null, null, null],
        ['escapamento', 'Flexível do escapamento', 'flexivel escapamento', null, null, null, null],
        ['escapamento', 'Coxins (borrachas) do escapamento', 'coxim borracha suporte escapamento', null, null, null, null],
        ['escapamento', 'Juntas do escapamento', 'junta escapamento coletor', null, null, null, null],

        // --------------------------------------------- Carroceria e vidros
        ['carroceria', 'Palhetas do limpador (dianteiras)', 'palheta limpador parabrisa para brisa', 'dianteiro', 12000, 'Troque a cada ano ou ao notar riscos no vidro.', null],
        ['carroceria', 'Palheta do limpador traseiro', 'palheta limpador vidro traseiro', 'traseiro', 12000, null, ['hatch', 'suv', 'minivan', 'utilitario']],
        ['carroceria', 'Para-brisa', 'parabrisa para brisa vidro', 'dianteiro', null, null, null],
        ['carroceria', 'Amortecedor do porta-malas ou da tampa traseira', 'amortecedor porta malas tampa traseira mola a gas', 'traseiro', null, 'Tampa que não fica aberta.', null],
        ['carroceria', 'Amortecedor do capô', 'amortecedor capo mola a gas', 'dianteiro', null, null, null],
        ['carroceria', 'Retrovisor externo', 'retrovisor espelho', null, null, null, null],
        ['carroceria', 'Lente do retrovisor', 'lente retrovisor espelho vidro', null, null, null, null],
        ['carroceria', 'Maçaneta e fechadura', 'macaneta fechadura trinco', null, null, null, null],
        ['carroceria', 'Borrachas de vedação das portas', 'borracha vedacao porta', null, null, null, null],
        ['carroceria', 'Para-choque', 'parachoque para choque', null, null, null, null],

        // ---------------------------------------------------- Pneus e rodas
        ['pneus', 'Pneus', 'pneu', null, 45000, 'Confira a medida na lateral do pneu ou no manual. Cheque o desgaste e a data de fabricação.', null],
        ['pneus', 'Rodas', 'roda aro liga leve', null, null, null, null],
        ['pneus', 'Parafusos e porcas de roda', 'parafuso porca roda', null, null, null, null],
        ['pneus', 'Estepe', 'estepe pneu reserva', null, null, 'Cheque a calibragem do estepe de tempos em tempos.', null],
    ],
];
