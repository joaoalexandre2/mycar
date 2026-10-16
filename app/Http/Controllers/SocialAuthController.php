<?php

namespace App\Http\Controllers;

use App\Models\Conta;
use App\Models\ContaSocial;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class SocialAuthController extends Controller
{
    private const SEGUNDOS_DO_CODIGO = 60;

    /**
     * Devolve a URL de autorização do provedor (o frontend navega até ela).
     */
    public function redirecionar(string $provedor)
    {
        if (!$this->configurado($provedor)) {
            return response()->json(['message' => 'Login com este provedor não está configurado.'], 503);
        }

        $url = Socialite::driver($provedor)->stateless()->redirect()->getTargetUrl();

        return response()->json(['url' => $url]);
    }

    /**
     * Volta do provedor: acha ou cria o usuário e manda o navegador de volta
     * ao frontend com um código de uso único (o token não passa pela URL).
     */
    public function callback(Request $request, string $provedor)
    {
        $frontend = rtrim(config('app.frontend_url'), '/');
        $falha = fn (string $motivo) => redirect("$frontend/login/social?erro=" . urlencode($motivo));

        if ($request->has('error') || !$this->configurado($provedor)) {
            return $falha('Login cancelado ou indisponível.');
        }

        try {
            $perfilSocial = Socialite::driver($provedor)->stateless()->user();
        } catch (Throwable) {
            return $falha('Não foi possível entrar com este provedor. Tente de novo.');
        }

        $email = $perfilSocial->getEmail();

        if (!$email) {
            return $falha('O provedor não informou seu e-mail. Use outro método de entrada.');
        }

        $user = $this->encontrarOuCriar(
            $provedor,
            (string) $perfilSocial->getId(),
            $email,
            $perfilSocial->getName() ?: $email,
        );

        $codigo = Str::random(48);
        Cache::put("login_social:$codigo", $user->id, self::SEGUNDOS_DO_CODIGO);

        return redirect("$frontend/login/social?codigo=$codigo");
    }

    /**
     * Troca o código de uso único por token de sessão.
     */
    public function trocar(Request $request)
    {
        $dados = $request->validate(['codigo' => ['required', 'string', 'max:100']]);

        $userId = Cache::pull("login_social:{$dados['codigo']}");
        $user = $userId ? User::find($userId) : null;

        if (!$user) {
            return response()->json(['message' => 'Código inválido ou expirado. Entre novamente.'], 422);
        }

        return response()->json([
            'user' => $user->dadosPublicos(),
            'token' => $user->abrirSessao($request->userAgent()),
        ]);
    }

    private function configurado(string $provedor): bool
    {
        return in_array($provedor, ['google', 'facebook'], true)
            && config("services.$provedor.client_id")
            && config("services.$provedor.client_secret");
    }

    private function encontrarOuCriar(string $provedor, string $provedorId, string $email, string $nome): User
    {
        $vinculo = ContaSocial::with('user')
            ->where('provedor', $provedor)
            ->where('provedor_id', $provedorId)
            ->first();

        if ($vinculo) {
            return $vinculo->user;
        }

        return DB::transaction(function () use ($provedor, $provedorId, $email, $nome) {
            $user = User::where('email', $email)->first();

            if (!$user) {
                // Conta nova pelo login social entra como pessoa (o perfil mais
                // simples); a senha aleatória nunca é usada.
                $conta = Conta::create(['tipo' => Conta::TIPO_PESSOA, 'nome' => $nome]);

                $user = User::create([
                    'perfil' => 'pessoa',
                    'name' => $nome,
                    'email' => $email,
                    'password' => Hash::make(Str::random(40)),
                    'conta_id' => $conta->id,
                ]);
            }

            // O provedor já confirmou o e-mail.
            if (!$user->email_verified_at) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            $user->contasSociais()->create(['provedor' => $provedor, 'provedor_id' => $provedorId]);

            return $user;
        });
    }
}
