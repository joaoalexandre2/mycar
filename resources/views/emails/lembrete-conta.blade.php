@component('mail::message')
# {{ $ehFrota ? 'Vencimentos da frota ' . $nomeConta : 'Lembrete para o seu carro' }}

@php
    $rotulos = ['ipva' => 'IPVA', 'licenciamento' => 'Licenciamento', 'revisao' => 'Revisão', 'seguro' => 'Fim do seguro'];

    $kmTexto = function (int $faltam, int $proxima): string {
        $alvo = number_format($proxima, 0, ',', '.');

        return $faltam <= 0
            ? 'passou dos ' . $alvo . ' km'
            : 'faltam ' . number_format($faltam, 0, ',', '.') . ' km (aos ' . $alvo . ' km)';
    };

    $quando = function (int $dias): string {
        if ($dias < 0) {
            return abs($dias) . ' dia(s) de atraso';
        }

        return $dias === 0 ? 'hoje' : "em {$dias} dia(s)";
    };
@endphp

@component('mail::table')
| O quê | Veículo | Quando |
|:--|:--|:--|
@foreach ($linhas as $linha)
| {{ $linha['rotulo'] ?? $rotulos[$linha['tipo']] }}@if ($linha['valor_estimado'] !== null) · R$ {{ number_format($linha['valor_estimado'], 2, ',', '.') }} (est.)@endif | {{ $linha['veiculo'] }} ({{ $linha['placa'] }}) | @if (isset($linha['km_restante'])){{ $kmTexto($linha['km_restante'], $linha['proxima_km']) }}@else{{ \Illuminate\Support\Carbon::parse($linha['data'])->format('d/m/Y') }} ({{ $quando($linha['dias']) }})@endif |
@endforeach
@endcomponent

As datas e os valores de **IPVA e licenciamento são estimativas** pelo final da placa e pelo estado: confirme no site do Detran/Sefaz. Os avisos de serviços (óleo, bateria...) seguem o prazo e o km que você escolheu; o km atual vem dos abastecimentos e serviços que você registrou. A data da revisão, dos documentos (como o CRLV) e o fim do seguro são as que você informou; para o seguro, é um bom momento para comparar propostas.

@component('mail::button', ['url' => $urlSistema])
Abrir o MyCar
@endcomponent

Para deixar de receber estes lembretes, desative em **Configurações > Lembretes**.

Equipe MyCar
@endcomponent
