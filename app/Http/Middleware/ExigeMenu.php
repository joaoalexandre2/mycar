<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe a rota a quem vê o menu da frota (um grupo pode tê-lo escondido):
 * `menu:cadastros`. Roda depois de `auth.token` e `perfil`.
 */
class ExigeMenu
{
    public function handle(Request $request, Closure $next, string $menu): Response
    {
        if (!$request->user()?->podeVerMenu($menu)) {
            return response()->json([
                'message' => 'Este menu está escondido para o seu acesso na frota.',
            ], 403);
        }

        return $next($request);
    }
}
