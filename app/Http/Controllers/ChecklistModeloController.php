<?php

namespace App\Http\Controllers;

use App\Models\ChecklistModelo;
use App\Models\ChecklistModeloItem;
use App\Models\ChecklistUso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Modelos de checklist da empresa: administrador e gestor montam os itens que o motorista confere.
 * Um modelo já usado em algum checklist não é apagado: fica desativado, para o histórico continuar legível.
 */
class ChecklistModeloController extends Controller
{
    public function index(): JsonResponse
    {
        ChecklistModelo::garantirPadrao();

        return response()->json(ChecklistModelo::with('itens')->orderBy('nome')->get()
            ->map(fn (ChecklistModelo $m) => $this->formatar($m))
            ->values());
    }

    public function store(Request $request): JsonResponse
    {
        $dados = $this->validar($request, null);

        $modelo = DB::transaction(function () use ($dados) {
            $modelo = ChecklistModelo::create([
                'nome' => trim($dados['nome']),
                'momento' => $dados['momento'],
                'ativo' => $dados['ativo'] ?? true,
            ]);

            $this->gravarItens($modelo, $dados['itens']);

            return $modelo;
        });

        return response()->json($this->formatar($modelo->load('itens')), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $modelo = ChecklistModelo::find($id);

        if (!$modelo) {
            return response()->json(['message' => 'Modelo não encontrado.'], 404);
        }

        $dados = $this->validar($request, $modelo);

        DB::transaction(function () use ($modelo, $dados) {
            $modelo->update([
                'nome' => trim($dados['nome']),
                'momento' => $dados['momento'],
                'ativo' => $dados['ativo'] ?? $modelo->ativo,
            ]);

            $modelo->itens()->delete();
            $this->gravarItens($modelo, $dados['itens']);
        });

        return response()->json($this->formatar($modelo->fresh('itens')));
    }

    public function destroy(int $id): JsonResponse
    {
        $modelo = ChecklistModelo::find($id);

        if (!$modelo) {
            return response()->json(['message' => 'Modelo não encontrado.'], 404);
        }

        if (ChecklistUso::where('checklist_modelo_id', $modelo->id)->exists()) {
            return response()->json(['message' => 'Este modelo já foi usado em checklists. Desative-o em vez de apagar.'], 422);
        }

        $modelo->delete();

        return response()->json(null, 204);
    }

    private function validar(Request $request, ?ChecklistModelo $atual): array
    {
        return $request->validate([
            'nome' => ['required', 'string', 'max:80', Rule::unique('checklist_modelos', 'nome')->where('conta_id', app('conta.atual'))->ignore($atual?->id)],
            'momento' => ['required', Rule::in(ChecklistModelo::MOMENTOS)],
            'ativo' => ['sometimes', 'boolean'],
            'itens' => ['required', 'array', 'min:1', 'max:60'],
            'itens.*.rotulo' => ['required', 'string', 'max:80'],
            'itens.*.obrigatorio' => ['sometimes', 'boolean'],
            // A chave antiga é mantida quando o item continua o mesmo: o histórico segue ligado a ela.
            'itens.*.chave' => ['sometimes', 'nullable', 'string', 'max:40', 'regex:/^[a-z0-9-]+$/'],
        ]);
    }

    /** @param  array<int, array{rotulo: string, obrigatorio?: bool, chave?: ?string}>  $itens */
    private function gravarItens(ChecklistModelo $modelo, array $itens): void
    {
        $usadas = [];

        foreach (array_values($itens) as $ordem => $item) {
            $chave = $item['chave'] ?? null;

            if ($chave === null || in_array($chave, $usadas, true)) {
                $chave = ChecklistModelo::chaveDoRotulo($item['rotulo'], $usadas);
            }

            $usadas[] = $chave;

            ChecklistModeloItem::create([
                'checklist_modelo_id' => $modelo->id,
                'chave' => $chave,
                'rotulo' => trim($item['rotulo']),
                'obrigatorio' => $item['obrigatorio'] ?? true,
                'ordem' => $ordem,
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function formatar(ChecklistModelo $m): array
    {
        return [
            'id' => $m->id,
            'nome' => $m->nome,
            'momento' => $m->momento,
            'ativo' => $m->ativo,
            'itens' => $m->itens->map(fn (ChecklistModeloItem $i) => [
                'chave' => $i->chave,
                'rotulo' => $i->rotulo,
                'obrigatorio' => $i->obrigatorio,
            ])->values(),
        ];
    }
}
