<?php

namespace App\Http\Controllers;

use App\Models\Oficina;
use App\Models\User;
use App\Services\ConfirmacaoEmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class RegisterController extends Controller
{
    /**
     * Cria a oficina e o usuário administrador dela, e envia o e-mail
     * de confirmação. O login só funciona depois que o e-mail é confirmado.
     */
    public function store(Request $request)
    {
        $dados = $request->validate([
            'nome_oficina' => ['required', 'string', 'max:150'],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [], [
            'nome_oficina' => 'nome da oficina',
            'name' => 'nome',
            'email' => 'e-mail',
            'password' => 'senha',
        ]);

        $user = DB::transaction(function () use ($dados) {
            $oficina = Oficina::create([
                'nome' => $dados['nome_oficina'],
            ]);

            return User::create([
                'oficina_id' => $oficina->id,
                'name' => $dados['name'],
                'email' => $dados['email'],
                'password' => Hash::make($dados['password']),
            ]);
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
