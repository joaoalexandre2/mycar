@component('mail::message')
# Olá, {{ $nome }}!

@if ($atrasado)
O licenciamento (CRLV) do seu veículo **{{ $veiculoNome }}** (placa {{ $placa }}) está atrasado — a estimativa de vencimento era **{{ $vencimentoFormatado }}**.
@else
O licenciamento (CRLV) do seu veículo **{{ $veiculoNome }}** (placa {{ $placa }}) vence em breve — estimativa: **{{ $vencimentoFormatado }}**.
@endif

@if ($valorLicenciamento !== null)
Valor estimado da taxa de licenciamento: **R$ {{ number_format($valorLicenciamento, 2, ',', '.') }}**.
@endif

Esta data é uma estimativa com base no final da placa. Confirme a data exata e o valor no site do Detran do seu estado.

Obrigado,<br>
Equipe MyCar
@endcomponent
