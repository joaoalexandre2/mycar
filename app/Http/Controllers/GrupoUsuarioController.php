<?php

namespace App\Http\Controllers;

use App\Models\GrupoUsuario;
use App\Models\User;
use App\Support\PermissoesFrota;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Grupos de usuários da frota: quem pertence a cada um e o que ele concede. */
class GrupoUsuarioController extends Controller
{
    public function index()
    {
        $grupos = GrupoUsuario::query()
            ->with('membros:users.id,users.name,users.email')
            ->orderBy('nome')
            ->get()
            ->map(fn (GrupoUsuario $g) => $this->formatar($g));

        $conta = app('conta.atual');

        // Pessoas que podem entrar num grupo: o login principal da frota e quem tem acesso a ela por outro MyCar.
        $usuarios = User::query()
            ->where(fn ($q) => $q
                ->where('conta_id', $conta)
                ->orWhereHas('acessos', fn ($a) => $a->where('conta_id', $conta)))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return response()->json([
            'grupos' => $grupos,
            'permissoes' => PermissoesFrota::catalogo(),
            'menus' => PermissoesFrota::menus(),
            'usuarios' => $usuarios,
        ]);
    }

    public function store(Request $request)
    {
        $dados = $this->validar($request);

        $grupo = GrupoUsuario::create(collect($dados)->only(['nome', 'descricao', 'permissoes', 'menus_ocultos'])->all());
        $grupo->membros()->sync($dados['membros'] ?? []);

        return response()->json($this->formatar($grupo->load('membros')), 201);
    }

    public function update(Request $request, $id)
    {
        $grupo = GrupoUsuario::find($id);

        if (!$grupo) {
            return $this->naoEncontrado();
        }

        $dados = $this->validar($request, $grupo->id);

        $grupo->update(collect($dados)->only(['nome', 'descricao', 'permissoes', 'menus_ocultos'])->all());

        if (array_key_exists('membros', $dados)) {
            $grupo->membros()->sync($dados['membros']);
        }

        return response()->json($this->formatar($grupo->load('membros')));
    }

    public function destroy($id)
    {
        $grupo = GrupoUsuario::find($id);

        if (!$grupo) {
            return $this->naoEncontrado();
        }

        $grupo->delete();

        return response()->json(['message' => 'Grupo removido.']);
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?int $ignorarId = null): array
    {
        $conta = app('conta.atual');

        $dados = $request->validate([
            'nome' => [
                'required',
                'string',
                'max:80',
                Rule::unique('grupos_usuarios', 'nome')->where('conta_id', $conta)->ignore($ignorarId),
            ],
            'descricao' => ['nullable', 'string', 'max:255'],
            'permissoes' => ['present', 'array'],
            'permissoes.*' => ['string', Rule::in(PermissoesFrota::chaves())],
            'menus_ocultos' => ['sometimes', 'array'],
            'menus_ocultos.*' => ['string', Rule::in(PermissoesFrota::chavesDeMenu())],
            'membros' => ['sometimes', 'array'],
            'membros.*' => ['integer', 'distinct'],
        ], [
            'nome.unique' => 'Já existe um grupo com este nome.',
            'permissoes.*.in' => 'Permissão desconhecida.',
        ]);

        $dados['permissoes'] = array_values(array_unique($dados['permissoes']));

        // Sem o campo, o grupo não muda os menus que já esconde.
        if (array_key_exists('menus_ocultos', $dados)) {
            $dados['menus_ocultos'] = array_values(array_unique($dados['menus_ocultos']));
        }

        // Só entram pessoas desta conta (login principal ou outro MyCar da mesma pessoa).
        if (!empty($dados['membros'])) {
            $validos = User::query()
                ->whereIn('id', $dados['membros'])
                ->where(fn ($q) => $q
                    ->where('conta_id', $conta)
                    ->orWhereHas('acessos', fn ($a) => $a->where('conta_id', $conta)))
                ->pluck('id')
                ->all();

            if (count($validos) !== count($dados['membros'])) {
                abort(response()->json([
                    'message' => 'Há usuários que não pertencem a esta frota.',
                    'errors' => ['membros' => ['Há usuários que não pertencem a esta frota.']],
                ], 422));
            }
        }

        return $dados;
    }

    /** @return array<string, mixed> */
    private function formatar(GrupoUsuario $grupo): array
    {
        return [
            'id' => $grupo->id,
            'nome' => $grupo->nome,
            'descricao' => $grupo->descricao,
            'permissoes' => $grupo->permissoes,
            'menus_ocultos' => $grupo->menus_ocultos ?? [],
            'membros' => $grupo->membros->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
            ])->values(),
        ];
    }

    private function naoEncontrado()
    {
        return response()->json(['message' => 'Grupo não encontrado.'], 404);
    }
}
