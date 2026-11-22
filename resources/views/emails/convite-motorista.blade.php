@component('mail::message')
# Olá, {{ $nome }}!

{{ $quemConvidou }} convidou você para usar o MyCar Frota como motorista. Com o seu acesso, você reserva os veículos da frota e registra a saída e a chegada de cada uso direto pelo celular.

Para entrar, crie a sua senha clicando no botão abaixo:

@component('mail::button', ['url' => $url])
Criar minha senha
@endcomponent

Este link expira em {{ $minutos }} minutos. Se ele expirar, peça a {{ $quemConvidou }} para reenviar o convite.

Obrigado,<br>
Equipe MyCar
@endcomponent
