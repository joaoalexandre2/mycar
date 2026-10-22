<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe a rota a um ou mais papéis dentro da conta (administrador, gestor,
 * motorista): `papel:administrador,gestor`. Roda depois de `auth.token`.
 * Quem não é do perfil frota conta como administrador da própria conta.
 */
class ExigePapel
{
    public function handle(Request $request, Closure $next, string ...$papeis): Response
    {
        $papel = $request->user()?->papelNaConta();

        if (!in_array($papel, $papeis, true)) {
            return response()->json([
                'message' => 'Seu papel na frota não permite esta ação.',
            ], 403);
        }

        return $next($request);
    }
}
