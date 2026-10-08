<?php

namespace App\Http\Controllers;

use App\Models\FotoVeiculoConta;
use App\Models\SugestaoAnexo;
use Illuminate\Support\Facades\Storage;

/**
 * Entrega o arquivo de uma foto do álbum. Fica fora do login de propósito: o
 * <img> do navegador não manda o token. A proteção é o link assinado e
 * temporário (middleware `signed:relative`), gerado só para quem listou o álbum.
 */
class FotoServirController extends Controller
{
    public function mostrar($foto, $tipo)
    {
        abort_unless(in_array($tipo, ['foto', 'miniatura'], true), 404);

        // Sem escopo de conta: quem chega aqui já provou ter o link assinado.
        $registro = FotoVeiculoConta::withoutGlobalScopes()->find($foto);
        $caminho = $registro ? ($tipo === 'foto' ? $registro->caminho : $registro->caminho_miniatura) : null;

        abort_unless($caminho && Storage::disk('local')->exists($caminho), 404);

        return Storage::disk('local')->response($caminho, null, [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Imagem anexada a uma sugestão (mesmo esquema: link assinado e temporário). */
    public function anexoDeSugestao($anexo)
    {
        $registro = SugestaoAnexo::find($anexo);

        abort_unless($registro && Storage::disk('local')->exists($registro->caminho), 404);

        return Storage::disk('local')->response($registro->caminho, null, [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
