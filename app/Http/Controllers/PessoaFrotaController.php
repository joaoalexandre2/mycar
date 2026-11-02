<?php

namespace App\Http\Controllers;

use App\Models\AcessoFrotaEvento;
use App\Models\GrupoUsuario;
use App\Models\User;
use App\Support\PermissoesFrota;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Pessoas da frota e seus papéis (administrador, gestor, motorista). Só o
 * administrador mexe aqui. Motoristas com cadastro continuam em Motoristas.
 */
class PessoaFrotaController extends Controller
{
    public function index()
    {
        $pessoas = User::query()
            ->where('conta_id', app('conta.atual'))
            ->where('perfil', 'frota')
            ->orderBy('name')
            ->get();

        // Os grupos são lidos uma vez: cada pessoa calcula o que pode a partir deles, sem consulta por pessoa.
        $grupos = GrupoUsuario::with('membros:users.id')->get();

        return response()->json([
            'pessoas' => $pessoas->map(fn (User $u) => $this->formatar($u, $grupos))->values(),
            'catalogo' => PermissoesFrota::catalogo(),
            'menus' => PermissoesFrota::menus(),
        ]);
    }

    /** Convida uma pessoa nova para a frota: cria o login e manda o link para definir a senha. */
    public function store(Request $request)
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'papel' => ['required', Rule::in(['administrador', 'gestor'])],
        ]);

        if (User::where('email', $dados['email'])->exists()) {
            return response()->json(['message' => 'Já existe um usuário com este e-mail.'], 422);
        }

        $usuario = User::create([
            'name' => $dados['nome'],
            'email' => $dados['email'],
            'password' => Hash::make(Str::random(40)),
            'perfil' => 'frota',
            'papel_frota' => $dados['papel'],
            'conta_id' => app('conta.atual'),
        ]);

        // Quem convida informou o e-mail; o link de senha só chega a quem o recebe.
        $usuario->forceFill(['email_verified_at' => now()])->save();
        Password::sendResetLink(['email' => $usuario->email]);

        AcessoFrotaEvento::registrar($request->user()->id, 'pessoa', $usuario->id, 'convidou', [
            'nome' => $usuario->name,
            'papel' => $dados['papel'],
        ]);

        return response()->json($this->formatar($usuario, GrupoUsuario::with('membros:users.id')->get()), 201);
    }

    public function papel(Request $request, $id)
    {
        $usuario = User::query()
            ->where('conta_id', app('conta.atual'))
            ->where('perfil', 'frota')
            ->find($id);

        if (!$usuario) {
            return response()->json(['message' => 'Pessoa não encontrada na frota.'], 404);
        }

        if ($usuario->id === $request->user()->id) {
            return response()->json(['message' => 'Você não pode trocar o seu próprio papel.'], 422);
        }

        $dados = $request->validate([
            'papel' => ['required', Rule::in(['administrador', 'gestor', 'motorista'])],
        ]);

        // Quem troca o papel é administrador e não pode trocar o próprio, então sempre sobra um administrador.
        $papelAnterior = $usuario->papelNaConta();
        $usuario->forceFill(['papel_frota' => $dados['papel']])->save();

        AcessoFrotaEvento::registrar($request->user()->id, 'pessoa', $usuario->id, 'mudou_papel', [
            'nome' => $usuario->name,
            'de' => $papelAnterior,
            'para' => $dados['papel'],
        ]);

        return response()->json($this->formatar($usuario, GrupoUsuario::with('membros:users.id')->get()));
    }

    /** @return array<string, mixed> */
    /**
     * Uma pessoa com o que ela pode fazer hoje (papel + grupos). Espelha User::temPermissao e User::podeVerMenu,
     * mas com os grupos já carregados, para não consultar o banco a cada permissão.
     *
     * @param  Collection<int, GrupoUsuario>  $grupos
     * @return array<string, mixed>
     */
    private function formatar(User $usuario, $grupos): array
    {
        $papel = $usuario->papelNaConta();
        $dosGrupos = $grupos->filter(fn (GrupoUsuario $g) => $g->membros->contains('id', $usuario->id));

        $permissoesDoPapel = PermissoesFrota::doPapel($papel);
        $permissoesDosGrupos = $dosGrupos->flatMap(fn (GrupoUsuario $g) => $g->permissoes ?? [])->all();

        $menusDosGrupos = $dosGrupos->flatMap(fn (GrupoUsuario $g) => $g->menus_liberados ?? [])->all();
        $veTodosOsMenus = in_array($papel, ['administrador', 'gestor'], true);

        return [
            'id' => $usuario->id,
            'name' => $usuario->name,
            'email' => $usuario->email,
            'papel' => $papel,
            'permissoes' => array_values(array_filter(
                PermissoesFrota::chaves(),
                fn (string $p) => in_array($p, $permissoesDoPapel, true) || in_array($p, $permissoesDosGrupos, true),
            )),
            'menus' => array_values(array_filter(
                PermissoesFrota::chavesDeMenu(),
                fn (string $m) => $veTodosOsMenus || in_array($m, $menusDosGrupos, true),
            )),
        ];
    }
}
