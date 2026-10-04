<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SomenteSuperAdmin
{
    /**
     * Restringe a rota ao operador da plataforma. Deve rodar depois de
     * `auth.token`, que é quem resolve o usuário da requisição.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()?->is_super_admin) {
            return response()->json([
                'message' => 'Acesso restrito ao administrador da plataforma.',
            ], 403);
        }

        return $next($request);
    }
}
