<?php

namespace App\Http\Middleware;

use App\Models\TokenAcesso;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateToken
{
    /** Só regrava "último uso" se passou deste tempo, para não escrever a cada requisição. */
    private const MINUTOS_ENTRE_REGISTROS_DE_USO = 5;

    /**
     * Autentica a requisição a partir de um Bearer token emitido pelo endpoint
     * de login. Cada aparelho tem o seu token (tokens_acesso), guardado só como
     * hash e com prazo de validade por falta de uso.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'message' => 'Não autenticado.',
            ], 401);
        }

        $hash = hash('sha256', $token);

        $acesso = TokenAcesso::with('user')->where('token_hash', $hash)->first();
        // Sessão esquecida há mais de DIAS_SEM_USO dias não vale mais, mesmo que
        // a pessoa não tenha feito login de novo (o login só limpa as dela).
        if ($acesso && $this->expirou($acesso)) {
            $acesso->delete();
            $acesso = null;
        }

        $user = $acesso?->user;

        if (!$user) {
            return response()->json([
                'message' => 'Token inválido ou expirado.',
            ], 401);
        }

        if ($acesso && (!$acesso->ultimo_uso_em || $acesso->ultimo_uso_em->lt(now()->subMinutes(self::MINUTOS_ENTRE_REGISTROS_DE_USO)))) {
            $acesso->forceFill(['ultimo_uso_em' => now()])->saveQuietly();
        }

        $request->setUserResolver(fn () => $user);

        // Qual sessão é esta (o logout e a troca de senha precisam saber).
        $request->attributes->set('token_hash', $hash);

        // Toda leitura/escrita feita pelos models com a trait PertenceAOficina
        // passa a ser isolada automaticamente para a oficina deste usuário.
        // 0 é o sentinela de "sem oficina" (nunca usar null: ver AppServiceProvider).
        app()->instance('oficina.atual', $user->oficina_id ?? 0);

        // Idem para os perfis pessoa e frota (models com PertenceAConta).
        app()->instance('conta.atual', $user->conta_id ?? 0);

        return $next($request);
    }

    private function expirou(TokenAcesso $acesso): bool
    {
        $ultimoUso = $acesso->ultimo_uso_em ?? $acesso->created_at;

        return $ultimoUso !== null
            && $ultimoUso->lt(now()->subDays(TokenAcesso::DIAS_SEM_USO));
    }
}
