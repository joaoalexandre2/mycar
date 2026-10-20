<?php

namespace App\Http\Controllers;

use App\Models\Motorista;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Motoristas da frota. Isolados pela conta, via PertenceAConta. */
class MotoristaController extends Controller
{
    public function index(Request $request)
    {
        $query = Motorista::query()->orderBy('nome');

        if (!$request->boolean('todos')) {
            $query->where('ativo', true);
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $motorista = Motorista::create($this->validar($request));

        return response()->json($motorista, 201);
    }

    public function update(Request $request, $id)
    {
        $motorista = Motorista::find($id);

        if (!$motorista) {
            return $this->naoEncontrado();
        }

        $motorista->update($this->validar($request, $motorista->id));

        return response()->json($motorista->fresh());
    }

    public function destroy($id)
    {
        $motorista = Motorista::find($id);

        if (!$motorista) {
            return $this->naoEncontrado();
        }

        if ($motorista->emUso()) {
            return response()->json([
                'message' => 'Este motorista tem usos de veículo no histórico. Desative-o em vez de apagar.',
            ], 422);
        }

        $motorista->delete();

        return response()->json(['message' => 'Motorista removido.']);
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?int $ignorarId = null): array
    {
        return $request->validate([
            'nome' => [
                'required',
                'string',
                'max:120',
                Rule::unique('motoristas', 'nome')->where('conta_id', app('conta.atual'))->ignore($ignorarId),
            ],
            'telefone' => ['nullable', 'string', 'max:30'],
            'cnh_numero' => ['nullable', 'string', 'max:20'],
            'cnh_validade' => ['nullable', 'date'],
            'ativo' => ['sometimes', 'boolean'],
        ], [
            'nome.unique' => 'Já existe um motorista com este nome.',
        ]);
    }

    private function naoEncontrado()
    {
        return response()->json(['message' => 'Motorista não encontrado.'], 404);
    }
}
