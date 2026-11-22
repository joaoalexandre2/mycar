<?php

namespace App\Http\Controllers;

use App\Mail\ConviteMotoristaEmail;
use App\Models\Motorista;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/** Motoristas da frota. Isolados pela conta, via PertenceAConta. */
class MotoristaController extends Controller
{
    public function index(Request $request)
    {
        $query = Motorista::query()->orderBy('nome');

        if (!$request->boolean('todos')) {
            $query->where('ativo', true);
        }

        // O login vem junto (só as colunas do estado de acesso) para não consultar um por motorista.
        $motoristas = $query->with('usuario:id,email,conta_id,ultimo_acesso_em')->get();

        return response()->json($motoristas->map(fn (Motorista $m) => $m->paraLista())->values());
    }

    public function store(Request $request)
    {
        $motorista = Motorista::create($this->validar($request));

        return response()->json($motorista->fresh()->paraLista(), 201);
    }

    public function update(Request $request, $id)
    {
        $motorista = Motorista::find($id);

        if (!$motorista) {
            return $this->naoEncontrado();
        }

        $motorista->update($this->validar($request, $motorista->id));

        return response()->json($motorista->fresh()->paraLista());
    }

    /**
     * Cria o login do motorista (papel motorista, e-mail já aceito pelo gestor)
     * e envia o convite com o link para ele definir a própria senha. O link usa
     * o mesmo token da redefinição de senha, mas o e-mail tem texto de convite
     * (quem recebe nunca pediu nada). Repetir o convite só reenvia o link.
     */
    public function convidar(Request $request, $id)
    {
        $motorista = Motorista::find($id);

        if (!$motorista) {
            return $this->naoEncontrado();
        }

        $dados = $request->validate(['email' => ['required', 'email', 'max:255']]);

        $usuario = $motorista->loginDaConta();

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
        } elseif ($usuario->ultimo_acesso_em) {
            return response()->json(['message' => 'Este motorista já entra no sistema. Se esqueceu a senha, ele pode usar "Esqueci minha senha".'], 422);
        }

        $tokens = Password::broker()->getRepository();

        // Mesmo intervalo do "esqueci minha senha": evita encher a caixa da pessoa com convites repetidos.
        if ($tokens->recentlyCreatedToken($usuario)) {
            return response()->json(['message' => 'Acabamos de enviar um convite para este motorista. Aguarde um minuto para reenviar.'], 429);
        }

        Mail::to($usuario->email)->send(new ConviteMotoristaEmail(
            $usuario,
            $usuario->urlParaDefinirSenha($tokens->create($usuario)),
            $request->user()->name,
            (int) config('auth.passwords.' . config('auth.defaults.passwords') . '.expire', 60),
        ));

        return response()->json([
            'message' => "Convite enviado para {$usuario->email}.",
            'user_id' => $usuario->id,
            'acesso' => Motorista::CONVITE_ENVIADO,
        ], 201);
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
        return $request->validate(Motorista::regrasDeCadastro($ignorarId), Motorista::MENSAGENS_DE_CADASTRO);
    }

    private function naoEncontrado()
    {
        return response()->json(['message' => 'Motorista não encontrado.'], 404);
    }
}
