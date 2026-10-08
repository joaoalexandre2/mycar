<?php

namespace App\Http\Controllers;

use App\Models\Sugestao;
use App\Models\SugestaoAnexo;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Sugestões dos usuários para a equipe do MyCar.
 *
 * Qualquer usuário logado envia e vê só as próprias. A equipe (operador da
 * plataforma, `super.admin`) vê todas e responde: é conteúdo que a pessoa
 * escolheu mandar para a equipe, diferente dos dados de oficina/conta.
 */
class SugestaoController extends Controller
{
    public function index(Request $request)
    {
        $sugestoes = Sugestao::with('anexos')->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return response()->json($sugestoes->map(fn (Sugestao $s) => $this->formatar($s))->values());
    }

    public function store(Request $request)
    {
        $dados = $request->validate([
            'categoria' => ['required', Rule::in(Sugestao::CATEGORIAS)],
            'titulo' => ['required', 'string', 'min:4', 'max:120'],
            'descricao' => ['required', 'string', 'min:10', 'max:2000'],
            'imagens' => ['nullable', 'array', 'max:'.SugestaoAnexo::LIMITE_POR_SUGESTAO],
            // Tipo conferido pelo conteúdo do arquivo, não pela extensão.
            'imagens.*' => ['file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:4096'],
        ], [
            'imagens.max' => 'Anexe no máximo '.SugestaoAnexo::LIMITE_POR_SUGESTAO.' imagens.',
            'imagens.*.mimetypes' => 'Anexe só imagens JPEG, PNG ou WebP.',
            'imagens.*.max' => 'Cada imagem pode ter até 4 MB.',
        ], [
            'titulo' => 'título',
            'descricao' => 'descrição',
        ]);

        $imagens = $dados['imagens'] ?? [];
        unset($dados['imagens']);

        $sugestao = Sugestao::create($dados + [
            'user_id' => $request->user()->id,
            'perfil' => $request->user()->perfil ?? 'oficina',
            'status' => 'nova',
        ]);

        foreach ($imagens as $imagem) {
            $this->anexar($sugestao, $imagem);
        }

        return response()->json($this->formatar($sugestao->load('anexos')), 201);
    }

    /** Equipe: todas as sugestões, com quem enviou. */
    public function todas(Request $request)
    {
        $dados = $request->validate([
            'status' => ['nullable', Rule::in(Sugestao::STATUS)],
        ]);

        $sugestoes = Sugestao::with(['user:id,name,email', 'anexos'])
            ->when($dados['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return response()->json($sugestoes->map(fn (Sugestao $s) => $this->formatar($s, true))->values());
    }

    /** Equipe: muda o andamento e/ou responde ao usuário. */
    public function atualizar(Request $request, $id)
    {
        $sugestao = Sugestao::with(['user:id,name,email', 'anexos'])->find($id);

        if (!$sugestao) {
            return response()->json(['message' => 'Sugestão não encontrada.'], 404);
        }

        $dados = $request->validate([
            'status' => ['required', Rule::in(Sugestao::STATUS)],
            'resposta' => ['nullable', 'string', 'max:1000'],
        ]);

        $sugestao->update($dados);

        return response()->json($this->formatar($sugestao, true));
    }

    /** O nome e a extensão do arquivo vêm do conteúdo detectado, nunca do que o cliente mandou. */
    private function anexar(Sugestao $sugestao, UploadedFile $arquivo): void
    {
        $extensao = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$arquivo->getMimeType()] ?? 'jpg';
        $caminho = "sugestoes/{$sugestao->user_id}/{$sugestao->id}/".Str::uuid().".{$extensao}";

        Storage::disk('local')->put($caminho, $arquivo->get());

        $sugestao->anexos()->create([
            'caminho' => $caminho,
            'mime' => $arquivo->getMimeType(),
            'tamanho' => $arquivo->getSize(),
        ]);
    }

    /** @return array<string, mixed> */
    private function formatar(Sugestao $s, bool $comAutor = false): array
    {
        $dados = [
            'id' => $s->id,
            'categoria' => $s->categoria,
            'titulo' => $s->titulo,
            'descricao' => $s->descricao,
            'status' => $s->status,
            'resposta' => $s->resposta,
            'criada_em' => $s->created_at?->toIso8601String(),
            'anexos' => $s->anexos->map(fn (SugestaoAnexo $a) => ['id' => $a->id, 'url' => $a->urlAssinada()])->values(),
        ];

        if ($comAutor) {
            $dados['autor'] = [
                'nome' => $s->user?->name,
                'email' => $s->user?->email,
                'perfil' => $s->perfil,
            ];
        }

        return $dados;
    }
}
