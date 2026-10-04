<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ConfiguracaoController extends Controller
{
    public function atualizarPerfil(Request $request)
    {
        $dados = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $user->update($dados);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'oficina' => $user->oficina?->nome,
        ]);
    }

    public function alterarSenha(Request $request)
    {
        $dados = $request->validate([
            'senha_atual' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = $request->user();

        if (!Hash::check($dados['senha_atual'], $user->password)) {
            throw ValidationException::withMessages([
                'senha_atual' => ['A senha atual está incorreta.'],
            ]);
        }

        $user->forceFill(['password' => $dados['password']])->save();

        return response()->json(['message' => 'Senha alterada com sucesso.']);
    }

    public function mostrarOficina(Request $request)
    {
        return response()->json($this->dadosOficina($request));
    }

    public function atualizarOficina(Request $request)
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:150'],
            'cnpj' => ['nullable', 'string', 'max:18'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'endereco' => ['nullable', 'string', 'max:200'],
        ]);

        $request->user()->oficina->update($dados);

        return response()->json($this->dadosOficina($request));
    }

    private function dadosOficina(Request $request): array
    {
        return $request->user()->oficina->only(['nome', 'cnpj', 'telefone', 'endereco']);
    }
}
