<?php

namespace App\Http\Controllers;

use App\Models\CentroCusto;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Centros de custo da frota. Isolados pela conta, via PertenceAConta. */
class CentroCustoController extends Controller
{
    public function index(Request $request)
    {
        $query = CentroCusto::query()->orderBy('nome');

        // Por padrão só os ativos (é o que a pessoa escolhe nos formulários).
        if (!$request->boolean('todos')) {
            $query->where('ativo', true);
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $centro = CentroCusto::create($this->validar($request));

        return response()->json($centro, 201);
    }

    public function update(Request $request, $id)
    {
        $centro = CentroCusto::find($id);

        if (!$centro) {
            return $this->naoEncontrado();
        }

        $centro->update($this->validar($request, $centro->id));

        return response()->json($centro->fresh());
    }

    public function destroy($id)
    {
        $centro = CentroCusto::find($id);

        if (!$centro) {
            return $this->naoEncontrado();
        }

        if ($centro->emUso()) {
            return response()->json([
                'message' => 'Este centro de custo tem veículos ou usos ligados a ele. Desative-o em vez de apagar, para manter o histórico.',
            ], 422);
        }

        $centro->delete();

        return response()->json(['message' => 'Centro de custo removido.']);
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?int $ignorarId = null): array
    {
        return $request->validate([
            'nome' => [
                'required',
                'string',
                'max:80',
                Rule::unique('centros_custo', 'nome')->where('conta_id', app('conta.atual'))->ignore($ignorarId),
            ],
            'codigo' => ['nullable', 'string', 'max:30'],
            'ativo' => ['sometimes', 'boolean'],
        ], [
            'nome.unique' => 'Já existe um centro de custo com este nome.',
        ]);
    }

    private function naoEncontrado()
    {
        return response()->json(['message' => 'Centro de custo não encontrado.'], 404);
    }
}
