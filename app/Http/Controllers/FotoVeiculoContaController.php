<?php

namespace App\Http\Controllers;

use App\Models\FotoVeiculoConta;
use App\Models\VeiculoConta;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Álbum de fotos dos veículos da conta (pessoa e frota). O navegador manda duas
 * versões já reduzidas da mesma foto: a "foto" (para ver) e a "miniatura" (para a
 * grade). Os arquivos ficam no disco privado, isolados por conta, e são vistos por
 * links assinados e temporários (ver FotoServirController).
 */
class FotoVeiculoContaController extends Controller
{
    private const MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function index($id)
    {
        $veiculo = VeiculoConta::find($id);

        if (!$veiculo) {
            return $this->naoEncontrado('Veículo');
        }

        return response()->json($this->montar($veiculo));
    }

    public function store(Request $request, $id)
    {
        $veiculo = VeiculoConta::find($id);

        if (!$veiculo) {
            return $this->naoEncontrado('Veículo');
        }

        $dados = $request->validate([
            // Tipo conferido pelo conteúdo do arquivo, não pela extensão.
            'foto' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120'],
            'miniatura' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:512'],
            'legenda' => ['nullable', 'string', 'max:120'],
        ], [
            'foto.max' => 'A foto é grande demais (máximo 5 MB).',
            'foto.mimetypes' => 'Envie uma imagem JPEG, PNG ou WebP.',
            'miniatura.mimetypes' => 'Envie uma imagem JPEG, PNG ou WebP.',
        ]);

        if ($veiculo->fotos()->count() >= FotoVeiculoConta::LIMITE_POR_VEICULO) {
            return response()->json([
                'message' => 'Este veículo já tem o máximo de '.FotoVeiculoConta::LIMITE_POR_VEICULO.' fotos. Remova alguma para adicionar outra.',
            ], 422);
        }

        $pasta = "fotos/{$veiculo->conta_id}/{$veiculo->id}";
        $nome = (string) Str::uuid();

        $caminho = $this->guardar($request->file('foto'), $pasta, $nome);
        $miniatura = $this->guardar($request->file('miniatura'), $pasta, "{$nome}-mini");

        $veiculo->fotos()->create([
            'caminho' => $caminho,
            'caminho_miniatura' => $miniatura,
            'mime' => $request->file('foto')->getMimeType(),
            'tamanho' => $request->file('foto')->getSize(),
            'legenda' => $dados['legenda'] ?? null,
        ]);

        return response()->json($this->montar($veiculo), 201);
    }

    public function destroy($id, $fotoId)
    {
        $veiculo = VeiculoConta::find($id);

        if (!$veiculo) {
            return $this->naoEncontrado('Veículo');
        }

        $foto = $veiculo->fotos()->find($fotoId);

        if (!$foto) {
            return $this->naoEncontrado('Foto');
        }

        $foto->delete(); // apaga os arquivos também (evento deleting do model)

        return response()->json($this->montar($veiculo));
    }

    /** O nome do arquivo e a extensão vêm do conteúdo detectado, nunca do que o cliente mandou. */
    private function guardar(UploadedFile $arquivo, string $pasta, string $nome): string
    {
        $extensao = self::MIMES[$arquivo->getMimeType()] ?? 'jpg';
        $caminho = "{$pasta}/{$nome}.{$extensao}";

        Storage::disk('local')->put($caminho, $arquivo->get());

        return $caminho;
    }

    /** @return array<string, mixed> */
    private function montar(VeiculoConta $veiculo): array
    {
        return [
            'limite' => FotoVeiculoConta::LIMITE_POR_VEICULO,
            'fotos' => $veiculo->fotos()->orderByDesc('created_at')->orderByDesc('id')->get()
                ->map(fn (FotoVeiculoConta $f) => [
                    'id' => $f->id,
                    'legenda' => $f->legenda,
                    'criada_em' => $f->created_at?->toIso8601String(),
                    'url' => $f->urlAssinada('foto'),
                    'url_miniatura' => $f->urlAssinada('miniatura'),
                ])->values(),
        ];
    }

    private function naoEncontrado(string $o_que)
    {
        return response()->json(['message' => "{$o_que} não encontrado."], 404);
    }
}
