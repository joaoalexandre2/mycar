<?php

namespace App\Http\Controllers;

use App\Services\ImagensModelos;

/**
 * Entrega a foto de um modelo (do catálogo). É pública de propósito: são
 * fotos livres da Wikimedia, iguais para todos, sem dado de usuário. O slug só
 * vale se estiver na lista; o nome do arquivo nunca vem da requisição.
 */
class ImagemModeloController extends Controller
{
    public function mostrar(string $slug, ImagensModelos $imagens)
    {
        $caminho = $imagens->caminhoDoSlug($slug);

        abort_unless($caminho !== null, 404);

        return response()->file($caminho, [
            'Cache-Control' => 'public, max-age=604800',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
