@component('mail::message')
# Olá, {{ $nome }}!

Recebemos um pedido para redefinir a senha da sua conta no MyCar. Clique no botão abaixo para escolher uma nova senha:

@component('mail::button', ['url' => $url])
Redefinir senha
@endcomponent

Se você não pediu isso, pode ignorar este e-mail — sua senha continua a mesma.

Este link expira em 60 minutos.

Obrigado,<br>
Equipe MyCar
@endcomponent
