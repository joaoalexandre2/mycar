@component('mail::message')
# Olá, {{ $nome }}!

{{ $quemConvidou }} convidou você para usar o MyCar Frota {{ $comoPapel }}.

Para entrar, crie a sua senha clicando no botão abaixo:

@component('mail::button', ['url' => $url])
Criar minha senha
@endcomponent

Este link expira em {{ $minutos }} minutos. Se ele expirar, use "Esqueci minha senha" na tela de entrada do MyCar, com este mesmo e-mail, para receber um link novo.

Obrigado,<br>
Equipe MyCar
@endcomponent
