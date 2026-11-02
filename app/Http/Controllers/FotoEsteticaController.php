<?php

namespace App\Http\Controllers;

use App\Models\AtendimentoEstetica;
use App\Models\FotoEstetica;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Fotos de check-in e checkout do carro. Quem serve a foto é o link assinado, como nas fotos da frota. */
class FotoEsteticaController extends Controller
{
    private const MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function store(Request $request, $id)
    {
        $atendimento = AtendimentoEstetica::find($id);

        if (!$atendimento) {
            return response()->json(['message' => 'Atendimento não encontrado.'], 404);
        }

        $dados = $request->validate([
            'momento' => ['required', 'in:' . implode(',', FotoEstetica::MOMENTOS)],
            'foto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        $arquivo = $request->file('foto');
        $extensao = self::MIMES[$arquivo->getMimeType()] ?? 'jpg';
        $caminho = "estetica/{$atendimento->conta_id}/{$atendimento->id}/{$dados['momento']}-" . Str::uuid() . ".{$extensao}";

        Storage::disk('local')->put($caminho, $arquivo->get());

        $foto = FotoEstetica::create([
            'atendimento_id' => $atendimento->id,
            'momento' => $dados['momento'],
            'caminho' => $caminho,
            'user_id' => $request->user()->id,
        ]);

        return response()->json(['id' => $foto->id, 'momento' => $foto->momento, 'url' => $foto->url()], 201);
    }

    /** Entrega o arquivo. Fica fora do login: o <img> não manda o token; a proteção é o link assinado. */
    public function mostrar($foto)
    {
        // Sem escopo de conta: quem chega aqui já provou ter o link assinado.
        $registro = FotoEstetica::withoutGlobalScopes()->find($foto);

        abort_unless($registro && Storage::disk('local')->exists($registro->caminho), 404);

        return Storage::disk('local')->response($registro->caminho, null, [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
