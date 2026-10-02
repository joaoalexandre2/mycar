@component('mail::message')
# Olá, {{ $nome }}!

@if ($atrasado)
O IPVA do seu veículo **{{ $veiculoNome }}** (placa {{ $placa }}) está atrasado — a estimativa de vencimento era **{{ $vencimentoFormatado }}**.
@else
O IPVA do seu veículo **{{ $veiculoNome }}** (placa {{ $placa }}) vence em breve — estimativa: **{{ $vencimentoFormatado }}**.
@endif

@if ($valorIpva !== null)
Valor estimado do IPVA: **R$ {{ number_format($valorIpva, 2, ',', '.') }}**.
@endif

Esta data e este valor são estimativas (o IPVA varia por estado, parcelamento e descontos). Confirme no site do Detran/Sefaz do seu estado.

Obrigado,<br>
Equipe MyCar
@endcomponent
