<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
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

        return response()->json($pessoas->map(fn (User $u) => $this->formatar($u))->values());
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

        return response()->json($this->formatar($usuario), 201);
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
        $usuario->forceFill(['papel_frota' => $dados['papel']])->save();

        return response()->json($this->formatar($usuario));
    }

    /** @return array<string, mixed> */
    private function formatar(User $usuario): array
    {
        return [
            'id' => $usuario->id,
            'name' => $usuario->name,
            'email' => $usuario->email,
            'papel' => $usuario->papelNaConta(),
        ];
    }
}
