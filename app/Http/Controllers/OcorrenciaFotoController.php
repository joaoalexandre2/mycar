<?php

namespace App\Http\Controllers;

use App\Models\Ocorrencia;
use App\Models\OcorrenciaFoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Fotos de uma ocorrência: anexa quem vê a ocorrência, e só administrador e gestor apagam. */
class OcorrenciaFotoController extends Controller
{
    public function store(Request $request, int $id): JsonResponse
    {
        $ocorrencia = $this->visivel($request, $id);

        if (!$ocorrencia) {
            return response()->json(['message' => 'Ocorrência não encontrada.'], 404);
        }

        $dados = $request->validate([
            'momento' => ['required', Rule::in(OcorrenciaFoto::MOMENTOS)],
            'arquivo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        $arquivo = $request->file('arquivo');
        $caminho = "ocorrencias/{$ocorrencia->conta_id}/{$ocorrencia->id}/" . Str::uuid() . '.' . ($arquivo->extension() ?: 'jpg');

        Storage::disk('local')->put($caminho, $arquivo->get());

        $foto = OcorrenciaFoto::create([
            'ocorrencia_id' => $ocorrencia->id,
            'momento' => $dados['momento'],
            'caminho' => $caminho,
        ]);

        return response()->json(['id' => $foto->id, 'momento' => $foto->momento, 'url' => $foto->urlDaFoto()], 201);
    }

    public function destroy(int $foto): JsonResponse
    {
        $registro = OcorrenciaFoto::find($foto);

        if (!$registro) {
            return response()->json(['message' => 'Foto não encontrada.'], 404);
        }

        Storage::disk('local')->delete($registro->caminho);
        $registro->delete();

        return response()->json(null, 204);
    }

    private function visivel(Request $request, int $id): ?Ocorrencia
    {
        $consulta = Ocorrencia::query()->whereKey($id);

        if ($request->user()->papelNaConta() === 'motorista') {
            $consulta->where('motorista_id', $request->user()->motoristaVinculado()?->id ?? 0);
        }

        return $consulta->first();
    }
}
