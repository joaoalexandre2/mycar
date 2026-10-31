<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe a rota a quem tem a permissão da frota, pelo papel ou por algum
 * grupo de usuários: `permissao:centros_custo`. Roda depois de `auth.token`.
 */
class ExigePermissao
{
    public function handle(Request $request, Closure $next, string $permissao): Response
    {
        if (!$request->user()?->temPermissao($permissao)) {
            return response()->json([
                'message' => 'Seu acesso na frota não permite esta ação.',
            ], 403);
        }

        return $next($request);
    }
}
