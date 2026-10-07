<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PasswordResetController extends Controller
{
    /**
     * Envia o e-mail de redefinição, se a conta existir. Não revela se o
     * e-mail existe ou não (mesma resposta nos dois casos).
     */
    public function enviar(Request $request)
    {
        $dados = $request->validate([
            'email' => ['required', 'email'],
        ]);

        Password::sendResetLink($dados);

        return response()->json([
            'message' => 'Se o e-mail existir, enviamos um link para redefinir a senha.',
        ]);
    }

    /**
     * Redefine a senha a partir do token recebido por e-mail.
     */
    public function redefinir(Request $request)
    {
        $dados = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset($dados, function ($user) use ($dados) {
            $user->forceFill([
                'password' => $dados['password'],
            ])->save();

            // Quem esqueceu a senha pode ter perdido o aparelho: derruba todas as sessões.
            $user->revogarSessoes();
        });

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Link inválido ou expirado. Solicite um novo.',
            ], 422);
        }

        return response()->json([
            'message' => 'Senha redefinida com sucesso.',
        ]);
    }
}
