<?php

namespace App\Http\Controllers;

use App\Models\TokenAcesso;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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

        $token = Str::random(60);

        // Um token por aparelho: entrar aqui não derruba a sessão aberta em outro.
        $this->descartarSessoesAntigas($user);

        $user->tokensAcesso()->create([
            'token_hash' => hash('sha256', $token),
            'dispositivo' => Str::limit((string) $request->userAgent(), 150, ''),
            'ultimo_uso_em' => now(),
        ]);

        $user->forceFill(['ultimo_acesso_em' => now()])->save();

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

        // Sessão aberta antes de existir um token por aparelho.
        if ($user->api_token === $hash) {
            $user->forceFill(['api_token' => null])->save();
        }

        return response()->json([
            'message' => 'Logout realizado com sucesso.',
        ]);
    }

    /**
     * Limpa sessões esquecidas (sem uso por muito tempo) e abre espaço se a
     * pessoa já tem o máximo de aparelhos: sai o menos usado.
     */
    private function descartarSessoesAntigas(User $user): void
    {
        $user->tokensAcesso()
            ->where('ultimo_uso_em', '<', now()->subDays(TokenAcesso::DIAS_SEM_USO))
            ->delete();

        $excedente = $user->tokensAcesso()->count() - (TokenAcesso::LIMITE_POR_USUARIO - 1);

        if ($excedente > 0) {
            $user->tokensAcesso()->orderBy('ultimo_uso_em')->orderBy('id')->limit($excedente)->get()->each->delete();
        }
    }

    /**
     * Retorna os dados do usuário autenticado.
     */
    public function me(Request $request)
    {
        return response()->json($request->user()->dadosPublicos());
    }
}
