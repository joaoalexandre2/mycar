<?php

namespace App\Services;

use App\Mail\ConfirmeSeuEmail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class ConfirmacaoEmailService
{
    /**
     * Envia o link de confirmação de e-mail (assinado, válido por 24h).
     */
    public function enviar(User $user): void
    {
        $url = URL::temporarySignedRoute(
            'verificacao.confirmar',
            now()->addHours(24),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        Mail::to($user->email)->send(new ConfirmeSeuEmail($user, $url));
    }
}
