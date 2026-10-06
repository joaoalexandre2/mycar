@component('mail::message')
# {{ $ehFrota ? 'Vencimentos da frota ' . $nomeConta : 'Lembrete para o seu carro' }}

@php
    $rotulos = ['ipva' => 'IPVA', 'licenciamento' => 'Licenciamento', 'revisao' => 'Revisão', 'seguro' => 'Fim do seguro'];

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
| {{ $rotulos[$linha['tipo']] }}@if ($linha['valor_estimado'] !== null) · R$ {{ number_format($linha['valor_estimado'], 2, ',', '.') }} (est.)@endif | {{ $linha['veiculo'] }} ({{ $linha['placa'] }}) | {{ \Illuminate\Support\Carbon::parse($linha['data'])->format('d/m/Y') }} ({{ $quando($linha['dias']) }}) |
@endforeach
@endcomponent

As datas e os valores de **IPVA e licenciamento são estimativas** pelo final da placa e pelo estado: confirme no site do Detran/Sefaz. A data da revisão e o fim do seguro são os que você informou; para o seguro, é um bom momento para comparar propostas.

@component('mail::button', ['url' => $urlSistema])
Abrir o MyCar
@endcomponent

Para deixar de receber estes lembretes, desative em **Configurações > Lembretes**.

Equipe MyCar
@endcomponent
