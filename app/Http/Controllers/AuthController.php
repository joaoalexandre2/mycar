<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Autentica o usuário e emite um novo token de acesso.
     */
    public function login(Request $request)
    {
        $dados = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $dados['email'])->first();

        if (!$user || !Hash::check($dados['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Credenciais inválidas.'],
            ]);
        }

        if (!$user->email_verified_at) {
            throw ValidationException::withMessages([
                'email' => ['Confirme seu e-mail antes de entrar. Verifique sua caixa de entrada.'],
            ]);
        }

        // Um token por aparelho: entrar aqui não derruba a sessão aberta em outro.
        $token = $user->abrirSessao($request->userAgent());

        return response()->json([
            'user' => $user->dadosPublicos(),
            'token' => $token,
        ]);
    }

    /**
     * Encerra só a sessão deste aparelho; as outras continuam abertas.
     */
    public function logout(Request $request)
    {
        $hash = (string) $request->attributes->get('token_hash');
        $user = $request->user();

        $user->tokensAcesso()->where('token_hash', $hash)->delete();

        return response()->json([
            'message' => 'Logout realizado com sucesso.',
        ]);
    }

    /**
     * Retorna os dados do usuário autenticado.
     */
    public function me(Request $request)
    {
        return response()->json($request->user()->dadosPublicos());
    }
}
