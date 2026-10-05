<?php

namespace App\Http\Controllers;

use App\Models\Conta;
use App\Models\Oficina;
use App\Models\User;
use App\Services\ConfirmacaoEmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterController extends Controller
{
    private const PERFIS = ['oficina', 'pessoa', 'frota'];

    /**
     * Cria a conta e o usuário dela, e envia o e-mail de confirmação. O login
     * só funciona depois que o e-mail é confirmado.
     *
     * Três perfis: "oficina" (cria a oficina, é o padrão e o que o cadastro
     * sempre fez), "pessoa" (Cuidados com seu carro) e "frota" (cria uma conta
     * com o nome da empresa).
     */
    public function store(Request $request)
    {
        $dados = $request->validate([
            'perfil' => ['sometimes', Rule::in(self::PERFIS)],
            'nome_oficina' => ['required_if:perfil,oficina', 'required_without:perfil', 'nullable', 'string', 'max:150'],
            'nome_frota' => ['required_if:perfil,frota', 'nullable', 'string', 'max:150'],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [], [
            'nome_oficina' => 'nome da oficina',
            'nome_frota' => 'nome da frota',
            'name' => 'nome',
            'email' => 'e-mail',
            'password' => 'senha',
        ]);

        $perfil = $dados['perfil'] ?? 'oficina';

        $user = DB::transaction(function () use ($dados, $perfil) {
            $base = [
                'perfil' => $perfil,
                'name' => $dados['name'],
                'email' => $dados['email'],
                'password' => Hash::make($dados['password']),
            ];

            if ($perfil === 'oficina') {
                $oficina = Oficina::create(['nome' => $dados['nome_oficina']]);

                return User::create($base + ['oficina_id' => $oficina->id]);
            }

            $conta = Conta::create([
                'tipo' => $perfil,
                'nome' => $perfil === Conta::TIPO_FROTA ? $dados['nome_frota'] : $dados['name'],
            ]);

            return User::create($base + ['conta_id' => $conta->id]);
        });

        $this->enviarEmailConfirmacao($user);

        return response()->json([
            'message' => 'Conta criada. Confira seu e-mail para confirmar o cadastro.',
        ], 201);
    }

    /**
     * Reenvia o e-mail de confirmação, caso o usuário não tenha recebido
     * ou o link tenha expirado. Não revela se o e-mail existe ou não.
     */
    public function reenviar(Request $request)
    {
        $dados = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::whereNull('email_verified_at')
            ->where('email', $dados['email'])
            ->first();

        if ($user) {
            $this->enviarEmailConfirmacao($user);
        }

        return response()->json([
            'message' => 'Se o e-mail existir e ainda não tiver sido confirmado, reenviamos o link.',
        ]);
    }

    private function enviarEmailConfirmacao(User $user): void
    {
        app(ConfirmacaoEmailService::class)->enviar($user);
    }
}
