<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\Cookie;

/**
 * Cookie da sessão do navegador. O token fica só no cookie HttpOnly: o JavaScript
 * da página não consegue lê-lo (protege contra XSS). Clientes que não são navegador
 * continuam usando o cabeçalho Authorization: Bearer.
 */
class SessaoCookie
{
    public const NOME = 'mycar_sessao';

    /** Mesmo prazo da sessão sem uso: depois disso o token já não vale. */
    private const MINUTOS = 60 * 24 * 30;

    public static function criar(string $token): Cookie
    {
        return cookie(
            self::NOME,
            $token,
            self::MINUTOS,
            '/',
            null,
            // Fora do desenvolvimento local o cookie só circula por HTTPS.
            !app()->isLocal(),
            true,
            false,
            'lax',
        );
    }

    public static function esquecer(): Cookie
    {
        return cookie()->forget(self::NOME, '/');
    }
}
