<?php

namespace App\Http\Controllers;

use App\Models\MotivoTrocaCentro;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Motivos de troca de centro de custo, cadastrados pela frota. Isolados pela conta. */
class MotivoTrocaCentroController extends Controller
{
    public function index(Request $request)
    {
        $query = MotivoTrocaCentro::query()->orderBy('nome');

        // Por padrão só os ativos (é o que aparece no select de troca).
        if (!$request->boolean('todos')) {
            $query->where('ativo', true);
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $motivo = MotivoTrocaCentro::create($this->validar($request));

        return response()->json($motivo, 201);
    }

    public function update(Request $request, $id)
    {
        $motivo = MotivoTrocaCentro::find($id);

        if (!$motivo) {
            return response()->json(['message' => 'Motivo não encontrado.'], 404);
        }

        $motivo->update($this->validar($request, $motivo->id));

        return response()->json($motivo->fresh());
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?int $ignorarId = null): array
    {
        return $request->validate([
            'nome' => [
                'required',
                'string',
                'max:120',
                Rule::unique('motivos_troca_centro', 'nome')->where('conta_id', app('conta.atual'))->ignore($ignorarId),
            ],
            'ativo' => ['sometimes', 'boolean'],
        ], [
            'nome.unique' => 'Já existe um motivo com este nome.',
        ]);
    }
}
