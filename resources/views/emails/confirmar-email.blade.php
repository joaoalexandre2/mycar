@component('mail::message')
# Olá, {{ $nome }}!

Falta pouco para começar a usar o MyCar. Confirme seu e-mail clicando no botão abaixo:

@component('mail::button', ['url' => $url])
Confirmar e-mail
@endcomponent

Se você não criou uma conta no MyCar, pode ignorar este e-mail.

Este link expira em 24 horas.

Obrigado,<br>
Equipe MyCar
@endcomponent
