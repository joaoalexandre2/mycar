<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    /**
     * Confirma o e-mail a partir do link assinado (enviado por e-mail) e
     * redireciona para o frontend. A assinatura já garante que a URL não
     * foi adulterada e não expirou (middleware "signed").
     */
    public function confirmar(Request $request, int $id, string $hash)
    {
        $frontend = rtrim(config('app.frontend_url'), '/');
        $user = User::find($id);

        if (!$user || !hash_equals($hash, sha1($user->email))) {
            return redirect("$frontend/email-confirmado?status=invalido");
        }

        if (!$user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return redirect("$frontend/email-confirmado?status=ok");
    }
}
