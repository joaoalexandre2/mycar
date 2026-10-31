<?php

namespace App\Http\Controllers;

use App\Models\Motorista;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
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

    /**
     * Cria o login do motorista (papel motorista, e-mail já aceito pelo gestor)
     * e envia o link para ele definir a própria senha, pelo mesmo fluxo de
     * "esqueci minha senha". Repetir o convite só reenvia o link.
     */
    public function convidar(Request $request, $id)
    {
        $motorista = Motorista::find($id);

        if (!$motorista) {
            return $this->naoEncontrado();
        }

        $dados = $request->validate(['email' => ['required', 'email', 'max:255']]);

        $usuario = $motorista->user_id ? User::find($motorista->user_id) : null;

        if (!$usuario) {
            if (User::where('email', $dados['email'])->exists()) {
                return response()->json(['message' => 'Já existe um usuário com este e-mail.'], 422);
            }

            $usuario = User::create([
                'name' => $motorista->nome,
                'email' => $dados['email'],
                'password' => Hash::make(Str::random(40)),
                'perfil' => 'frota',
                'papel_frota' => 'motorista',
                'conta_id' => $motorista->conta_id,
            ]);

            // O gestor informou o e-mail e o link abaixo só chega a quem o recebe.
            $usuario->forceFill(['email_verified_at' => now()])->save();
            $motorista->forceFill(['user_id' => $usuario->id])->save();
        } elseif ($usuario->email !== $dados['email']) {
            return response()->json(['message' => 'Este motorista já tem login com outro e-mail.'], 422);
        }

        Password::sendResetLink(['email' => $usuario->email]);

        return response()->json(['message' => "Convite enviado para {$usuario->email}.", 'user_id' => $usuario->id], 201);
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
            'centro_custo_id' => ['nullable', 'integer', Rule::exists('centros_custo', 'id')->where('conta_id', app('conta.atual'))->where('ativo', true)],
        ], [
            'nome.unique' => 'Já existe um motorista com este nome.',
        ]);
    }

    private function naoEncontrado()
    {
        return response()->json(['message' => 'Motorista não encontrado.'], 404);
    }
}
