<?php

namespace App\Http\Controllers;

use App\Models\Abastecimento;
use App\Models\FotoVeiculoConta;
use App\Models\OcorrenciaFoto;
use App\Models\ServicoAnexo;
use App\Models\SugestaoAnexo;
use App\Models\UsoVeiculo;
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

    /** Foto do hodômetro de um uso da frota (mesmo esquema: link assinado e temporário). */
    public function hodometro($uso, $momento)
    {
        abort_unless(in_array($momento, UsoVeiculo::MOMENTOS, true), 404);

        // Sem escopo de conta: quem chega aqui já provou ter o link assinado.
        $registro = UsoVeiculo::withoutGlobalScopes()->find($uso);
        $caminho = $registro?->caminhoDaFoto($momento);

        abort_unless($caminho && Storage::disk('local')->exists($caminho), 404);

        return Storage::disk('local')->response($caminho, null, [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Nota fiscal ou recibo de um serviço (mesmo esquema: link assinado e temporário). */
    public function servicoAnexo($anexo)
    {
        // Sem escopo de conta: quem chega aqui já provou ter o link assinado.
        $registro = ServicoAnexo::withoutGlobalScopes()->find($anexo);

        abort_unless($registro && Storage::disk('local')->exists($registro->caminho), 404);

        return Storage::disk('local')->response($registro->caminho, $registro->nome, [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Comprovante ou foto do hodômetro de um abastecimento (mesmo esquema: link assinado e temporário). */
    public function abastecimento($abastecimento, $tipo)
    {
        abort_unless(in_array($tipo, ['comprovante', 'hodometro'], true), 404);

        // Sem escopo de conta: quem chega aqui já provou ter o link assinado.
        $registro = Abastecimento::withoutGlobalScopes()->find($abastecimento);
        $caminho = $registro ? ($tipo === 'comprovante' ? $registro->comprovante_caminho : $registro->hodometro_caminho) : null;

        abort_unless($caminho && Storage::disk('local')->exists($caminho), 404);

        return Storage::disk('local')->response($caminho, null, [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Foto de uma ocorrência da frota (mesmo esquema: link assinado e temporário). */
    public function ocorrencia($foto)
    {
        // Sem escopo de conta: quem chega aqui já provou ter o link assinado.
        $registro = OcorrenciaFoto::withoutGlobalScopes()->find($foto);

        abort_unless($registro && Storage::disk('local')->exists($registro->caminho), 404);

        return Storage::disk('local')->response($registro->caminho, null, [
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
