<?php

namespace App\Http\Controllers;

use App\Models\AcessoFrotaEvento;
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

        if (!$this->podeConceder($request->user(), $dados['permissoes'], $dados['menus_liberados'] ?? [])) {
            return $this->semConcessao();
        }

        $grupo = GrupoUsuario::create(collect($dados)->only(['nome', 'descricao', 'permissoes', 'menus_liberados'])->all());
        $grupo->membros()->sync($dados['membros'] ?? []);

        AcessoFrotaEvento::registrar($request->user()->id, 'grupo', $grupo->id, 'criou', [
            'nome' => $grupo->nome,
            'permissoes' => $grupo->permissoes,
            'menus_liberados' => $grupo->menus_liberados ?? [],
            'membros' => $grupo->membros()->pluck('users.id')->all(),
        ]);

        return response()->json($this->formatar($grupo->load('membros')), 201);
    }

    public function update(Request $request, $id)
    {
        $grupo = GrupoUsuario::find($id);

        if (!$grupo) {
            return $this->naoEncontrado();
        }

        // Só mexe em grupo que concede no máximo o que quem edita já tem.
        if (!$this->podeConceder($request->user(), $grupo->permissoes, $grupo->menus_liberados ?? [])) {
            return $this->semConcessao();
        }

        $dados = $this->validar($request, $grupo->id);

        if (!$this->podeConceder($request->user(), $dados['permissoes'], $dados['menus_liberados'] ?? $grupo->menus_liberados ?? [])) {
            return $this->semConcessao();
        }

        $antes = $this->resumo($grupo);

        $grupo->update(collect($dados)->only(['nome', 'descricao', 'permissoes', 'menus_liberados'])->all());

        if (array_key_exists('membros', $dados)) {
            $grupo->membros()->sync($dados['membros']);
        }

        AcessoFrotaEvento::registrar($request->user()->id, 'grupo', $grupo->id, 'editou', [
            'antes' => $antes,
            'depois' => $this->resumo($grupo->fresh()),
        ]);

        return response()->json($this->formatar($grupo->load('membros')));
    }

    public function destroy(Request $request, $id)
    {
        $grupo = GrupoUsuario::find($id);

        if (!$grupo) {
            return $this->naoEncontrado();
        }

        if (!$this->podeConceder($request->user(), $grupo->permissoes, $grupo->menus_liberados ?? [])) {
            return $this->semConcessao();
        }

        AcessoFrotaEvento::registrar($request->user()->id, 'grupo', $grupo->id, 'removeu', [
            'antes' => $this->resumo($grupo),
        ]);

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
            'menus_liberados' => ['sometimes', 'array'],
            'menus_liberados.*' => ['string', Rule::in(PermissoesFrota::chavesDeMenu())],
            'membros' => ['sometimes', 'array'],
            'membros.*' => ['integer', 'distinct'],
        ], [
            'nome.unique' => 'Já existe um grupo com este nome.',
            'permissoes.*.in' => 'Permissão desconhecida.',
        ]);

        $dados['permissoes'] = array_values(array_unique($dados['permissoes']));

        // Sem o campo, o grupo não muda os menus que já libera.
        if (array_key_exists('menus_liberados', $dados)) {
            $dados['menus_liberados'] = array_values(array_unique($dados['menus_liberados']));
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
            'menus_liberados' => $grupo->menus_liberados ?? [],
            'membros' => $grupo->membros->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
            ])->values(),
        ];
    }

    /** @return array<string, mixed> o que o grupo concede e quem está nele, para a trilha de auditoria */
    private function resumo(GrupoUsuario $grupo): array
    {
        return [
            'nome' => $grupo->nome,
            'permissoes' => $grupo->permissoes ?? [],
            'menus_liberados' => $grupo->menus_liberados ?? [],
            'membros' => $grupo->membros()->pluck('users.id')->sort()->values()->all(),
        ];
    }

    /** Quem gerencia grupos só concede o que ele mesmo tem: permissões e menus. */
    private function podeConceder(User $ator, array $permissoes, array $menus): bool
    {
        return PermissoesFrota::podeConceder($ator, $permissoes, $menus);
    }

    private function semConcessao()
    {
        return response()->json([
            'message' => 'Você só pode criar ou editar grupos com permissões e menus que você mesmo tem.',
        ], 403);
    }

    private function naoEncontrado()
    {
        return response()->json(['message' => 'Grupo não encontrado.'], 404);
    }
}
