@component('mail::message')
# Olá, {{ $nome }}!

@if ($atrasada)
A manutenção **{{ $tipo }}** do seu veículo **{{ $veiculo }}** (placa {{ $placa }}) está atrasada desde **{{ $proximaData }}**.
@else
A manutenção **{{ $tipo }}** do seu veículo **{{ $veiculo }}** (placa {{ $placa }}) está prevista para **{{ $proximaData }}**.
@endif

Entre em contato com a oficina para agendar.

Obrigado,<br>
Equipe MyCar
@endcomponent
