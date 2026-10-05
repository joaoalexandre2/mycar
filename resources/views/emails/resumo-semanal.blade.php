@component('mail::message')
# Resumo da semana — {{ $nomeOficina }}

Veja o que precisa de atenção nos próximos 30 dias e o que já passou do prazo.

@php
    $rotuloDias = function (int $dias): string {
        if ($dias < 0) {
            return abs($dias) . ' dia(s) de atraso';
        }

        return $dias === 0 ? 'hoje' : "em {$dias} dia(s)";
    };

    $blocos = [
        ['chave' => 'manutencoes_atrasadas', 'titulo' => 'Manutenções atrasadas', 'comDescricao' => true],
        ['chave' => 'manutencoes_proximas', 'titulo' => 'Manutenções a vencer', 'comDescricao' => true],
        ['chave' => 'ipva', 'titulo' => 'IPVA a vencer (estimativa)', 'comDescricao' => false],
        ['chave' => 'licenciamento', 'titulo' => 'Licenciamento a vencer (estimativa)', 'comDescricao' => false],
    ];
@endphp

@foreach ($blocos as $bloco)
@php($secao = $secoes[$bloco['chave']])
@if ($secao['total'] > 0)
## {{ $bloco['titulo'] }} ({{ $secao['total'] }})

@component('mail::table')
| @if ($bloco['comDescricao']) Serviço @else Veículo @endif | @if ($bloco['comDescricao']) Veículo @else Placa @endif | Cliente | Quando |
|:--|:--|:--|:--|
@foreach ($secao['itens'] as $item)
| {{ $bloco['comDescricao'] ? $item['descricao'] : $item['veiculo'] }} | {{ $bloco['comDescricao'] ? $item['veiculo'] . ' (' . $item['placa'] . ')' : $item['placa'] }} | {{ $item['cliente_nome'] }}@if ($item['cliente_telefone']) · {{ $item['cliente_telefone'] }}@endif @unless ($item['cliente_tem_email']) · sem e-mail @endunless | {{ $item['data'] }} ({{ $rotuloDias($item['dias']) }}) |
@endforeach
@endcomponent

@if ($secao['restantes'] > 0)
…e mais {{ $secao['restantes'] }} no sistema.

@endif
@endif
@endforeach

@if ($secoes['sem_email'] > 0)
**{{ $secoes['sem_email'] }} cliente(s) acima não têm e-mail cadastrado**, então o aviso automático não foi enviado a eles. Vale ligar ou cadastrar o e-mail no sistema.
@endif

As datas de IPVA e licenciamento são **estimativas** pelo final da placa; confirme no site do Detran/Sefaz do estado.

@component('mail::button', ['url' => $urlSistema])
Abrir o MyCar
@endcomponent

Para deixar de receber este resumo, desative em **Configurações > Oficina**.

Equipe MyCar
@endcomponent
