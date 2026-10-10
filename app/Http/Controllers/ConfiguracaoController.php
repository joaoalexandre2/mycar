<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
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

        return response()->json($user->dadosPublicos());
    }

    /** Grava o tema e a cor principal escolhidos, para valerem em qualquer navegador. */
    public function atualizarAparencia(Request $request)
    {
        $dados = $request->validate([
            'tema' => ['required', Rule::in(['claro', 'escuro'])],
            'cor' => ['required', Rule::in(['blue', 'green', 'purple', 'orange', 'red'])],
        ]);

        $user = $request->user();
        $user->update($dados);

        return response()->json($user->dadosPublicos());
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

        // A senha mudou: as sessões dos outros aparelhos caem; esta continua.
        $user->revogarSessoes($request->attributes->get('token_hash'));

        return response()->json(['message' => 'Senha alterada com sucesso.']);
    }

    /**
     * Foto de perfil: o navegador manda a imagem já reduzida (data URI, até 120 KB).
     * Só aceita JPEG, PNG ou WebP em base64.
     */
    public function atualizarFoto(Request $request)
    {
        $dados = $request->validate([
            'foto' => ['required', 'string', 'max:160000', 'regex:/^data:image\/(jpeg|png|webp);base64,[A-Za-z0-9+\/=]+$/'],
        ], [
            'foto.regex' => 'A foto precisa ser uma imagem JPEG, PNG ou WebP.',
            'foto.max' => 'A foto está grande demais. Escolha outra.',
        ]);

        $user = $request->user();
        $user->update($dados);

        return response()->json($user->dadosPublicos());
    }

    public function removerFoto(Request $request)
    {
        $user = $request->user();
        $user->update(['foto' => null]);

        return response()->json($user->dadosPublicos());
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
            // Ausente = não altera (clientes antigos não conhecem o campo).
            'resumo_semanal' => ['sometimes', 'boolean'],
        ]);

        $request->user()->oficina->update($dados);

        return response()->json($this->dadosOficina($request));
    }

    private function dadosOficina(Request $request): array
    {
        return $request->user()->oficina->fresh()->only([
            'nome',
            'cnpj',
            'telefone',
            'endereco',
            'resumo_semanal',
        ]);
    }
}
