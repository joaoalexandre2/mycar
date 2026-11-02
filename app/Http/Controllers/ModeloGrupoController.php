<?php

namespace App\Http\Controllers;

use App\Models\AcessoFrotaEvento;
use App\Models\ModeloGrupo;
use App\Support\PermissoesFrota;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Modelos de grupo salvos pela frota. Quem pode gerenciar grupos salva e remove modelos,
 * e só salva um modelo com permissões e menus que ele mesmo tem.
 */
class ModeloGrupoController extends Controller
{
    public function index()
    {
        return response()->json(
            ModeloGrupo::query()->orderBy('nome')->get()->map(fn (ModeloGrupo $m) => $this->formatar($m))->values(),
        );
    }

    public function store(Request $request)
    {
        $conta = app('conta.atual');

        $dados = $request->validate([
            'nome' => [
                'required',
                'string',
                'max:80',
                Rule::unique('modelos_grupo', 'nome')->where('conta_id', $conta),
            ],
            'descricao' => ['nullable', 'string', 'max:255'],
            'permissoes' => ['present', 'array'],
            'permissoes.*' => ['string', Rule::in(PermissoesFrota::chaves())],
            'menus_liberados' => ['present', 'array'],
            'menus_liberados.*' => ['string', Rule::in(PermissoesFrota::chavesDeMenu())],
        ], [
            'nome.unique' => 'Já existe um modelo com este nome.',
        ]);

        $permissoes = array_values(array_unique($dados['permissoes']));
        $menus = array_values(array_unique($dados['menus_liberados']));

        if (!PermissoesFrota::podeConceder($request->user(), $permissoes, $menus)) {
            return response()->json([
                'message' => 'Você só pode salvar um modelo com permissões e menus que você mesmo tem.',
            ], 403);
        }

        $modelo = ModeloGrupo::create([
            'nome' => $dados['nome'],
            'descricao' => $dados['descricao'] ?? null,
            'permissoes' => $permissoes,
            'menus_liberados' => $menus,
            'criado_por_user_id' => $request->user()->id,
        ]);

        AcessoFrotaEvento::registrar($request->user()->id, 'modelo', $modelo->id, 'salvou', [
            'nome' => $modelo->nome,
            'permissoes' => $permissoes,
            'menus_liberados' => $menus,
        ]);

        return response()->json($this->formatar($modelo), 201);
    }

    public function destroy(Request $request, $id)
    {
        $modelo = ModeloGrupo::find($id);

        if (!$modelo) {
            return response()->json(['message' => 'Modelo não encontrado.'], 404);
        }

        AcessoFrotaEvento::registrar($request->user()->id, 'modelo', $modelo->id, 'removeu', ['nome' => $modelo->nome]);

        $modelo->delete();

        return response()->json(['message' => 'Modelo removido.']);
    }

    /** @return array<string, mixed> */
    private function formatar(ModeloGrupo $modelo): array
    {
        return [
            'id' => $modelo->id,
            'nome' => $modelo->nome,
            'descricao' => $modelo->descricao,
            'permissoes' => $modelo->permissoes ?? [],
            'menus_liberados' => $modelo->menus_liberados ?? [],
        ];
    }
}
