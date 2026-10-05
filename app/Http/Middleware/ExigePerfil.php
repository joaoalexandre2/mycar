<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe a rota a um ou mais perfis (oficina, pessoa, frota):
 * `perfil:oficina` ou `perfil:pessoa,frota`. Roda depois de `auth.token`.
 *
 * É uma segunda camada: mesmo sem ela, os models já se isolam por oficina ou
 * por conta e falham fechado. Aqui o usuário recebe um 403 claro em vez de
 * uma lista vazia.
 */
class ExigePerfil
{
    public function handle(Request $request, Closure $next, string ...$perfis): Response
    {
        $perfil = $request->user()?->perfil ?? 'oficina';

        if (!in_array($perfil, $perfis, true)) {
            return response()->json([
                'message' => 'Este recurso não está disponível para o seu tipo de conta.',
            ], 403);
        }

        return $next($request);
    }
}
