<?php

namespace App\Http\Controllers;

use App\Services\Hodometro\LeitorDeHodometro;
use Illuminate\Http\Request;
use Throwable;

/**
 * Lê o km da foto do hodômetro com IA. Só sugere o número: quem registra a
 * saída ou a chegada confere e confirma antes de gravar.
 */
class LeituraHodometroController extends Controller
{
    public function ler(Request $request, LeitorDeHodometro $leitor)
    {
        $request->validate(
            ['foto' => ['required', 'image', 'max:5120']],
            [],
            ['foto' => 'foto do hodômetro'],
        );

        if (!$leitor->disponivel()) {
            return response()->json([
                'message' => 'A leitura automática do hodômetro não está ativada. Digite o km.',
            ], 503);
        }

        try {
            $km = $leitor->ler($request->file('foto'));
        } catch (Throwable $erro) {
            report($erro);

            return response()->json([
                'message' => 'Não foi possível ler a foto agora. Digite o km.',
            ], 502);
        }

        return response()->json(['km' => $km]);
    }
}
