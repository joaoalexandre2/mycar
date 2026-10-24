<?php

namespace App\Http\Controllers;

use App\Models\Acesso;
use App\Models\Conta;
use App\Models\Oficina;
use App\Models\TokenAcesso;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Os MyCar de uma mesma pessoa: o mesmo e-mail pode ter uma oficina, um
 * "Cuidados com seu carro" e uma frota, e alternar entre eles sem sair.
 *
 * O MyCar principal fica nas colunas do usuário (id "principal"); os outros
 * ficam na tabela acessos. Qual está aberto é guardado por aparelho
 * (tokens_acesso.acesso_id), então o celular pode estar na frota enquanto o
 * computador está na oficina.
 */
class MeusMycarsController extends Controller
{
    private const PERFIS = ['oficina', 'pessoa', 'frota'];

    private const PRINCIPAL = 'principal';

    /** Lista os MyCar da pessoa e os tipos que ela ainda pode criar. */
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->resposta($request->user(), $request->attributes->get('acesso_id')));
    }

    /** Abre, neste aparelho, o MyCar escolhido ("principal" ou o id de um acesso). */
    public function ativar(Request $request, string $id): JsonResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();

        if ($id === self::PRINCIPAL) {
            $acesso = null;

            if (!$this->principalValido($usuario->principal())) {
                abort(404, 'MyCar não encontrado.');
            }
        } else {
            $acesso = Acesso::where('user_id', $usuario->id)->find($id);

            abort_if($acesso === null, 404, 'MyCar não encontrado.');
        }

        $this->guardarNaSessao($request, $acesso);
        $usuario->aplicarAcesso($acesso);

        return response()->json($this->resposta($usuario, $acesso?->id));
    }

    /** Cria outro MyCar para a pessoa e já o abre neste aparelho. */
    public function store(Request $request): JsonResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();

        $dados = $request->validate([
            'perfil' => ['required', Rule::in(self::PERFIS)],
            'nome' => ['required_if:perfil,oficina', 'required_if:perfil,frota', 'nullable', 'string', 'max:150'],
        ], [], ['nome' => 'nome']);

        if (in_array($dados['perfil'], $this->perfisDaPessoa($usuario), true)) {
            throw ValidationException::withMessages([
                'perfil' => ['Você já tem um MyCar desse tipo.'],
            ]);
        }

        $acesso = DB::transaction(function () use ($usuario, $dados) {
            if ($dados['perfil'] === 'oficina') {
                $oficina = Oficina::create(['nome' => $dados['nome']]);

                return Acesso::create([
                    'user_id' => $usuario->id,
                    'perfil' => 'oficina',
                    'oficina_id' => $oficina->id,
                ]);
            }

            $conta = Conta::create([
                'tipo' => $dados['perfil'],
                // "Cuidados com seu carro" é pessoal: leva o nome da própria pessoa.
                'nome' => $dados['perfil'] === Conta::TIPO_FROTA ? $dados['nome'] : $usuario->name,
            ]);

            return Acesso::create([
                'user_id' => $usuario->id,
                'perfil' => $dados['perfil'],
                'conta_id' => $conta->id,
                'papel_frota' => $dados['perfil'] === Conta::TIPO_FROTA ? 'administrador' : null,
            ]);
        });

        $this->guardarNaSessao($request, $acesso);
        $usuario->aplicarAcesso($acesso);

        return response()->json($this->resposta($usuario, $acesso->id), 201);
    }

    private function guardarNaSessao(Request $request, ?Acesso $acesso): void
    {
        TokenAcesso::where('token_hash', (string) $request->attributes->get('token_hash'))
            ->update(['acesso_id' => $acesso?->id]);
    }

    /**
     * @param  array{perfil: ?string, oficina_id: ?int, conta_id: ?int}  $principal
     */
    private function principalValido(array $principal): bool
    {
        return ($principal['perfil'] ?? 'oficina') === 'oficina'
            ? $principal['oficina_id'] !== null
            : $principal['conta_id'] !== null;
    }

    /** @return list<string> Tipos de MyCar que a pessoa já tem (principal e extras). */
    private function perfisDaPessoa(User $usuario): array
    {
        $principal = $usuario->principal();
        $perfis = $usuario->acessos()->pluck('perfil')->all();

        if ($this->principalValido($principal)) {
            $perfis[] = $principal['perfil'] ?? 'oficina';
        }

        return array_values(array_unique($perfis));
    }

    /**
     * @return array{user: array<string, mixed>, mycars: list<array<string, mixed>>, pode_criar: list<string>}
     */
    private function resposta(User $usuario, ?int $acessoAtivoId): array
    {
        $principal = $usuario->principal();
        $mycars = [];

        if ($this->principalValido($principal)) {
            $perfil = $principal['perfil'] ?? 'oficina';
            $nome = $perfil === 'oficina'
                ? Oficina::find($principal['oficina_id'])?->nome
                : Conta::find($principal['conta_id'])?->nome;

            $mycars[] = [
                'id' => self::PRINCIPAL,
                'perfil' => $perfil,
                'nome' => (string) $nome,
                'atual' => $acessoAtivoId === null,
            ];
        }

        foreach ($usuario->acessos()->with(['oficina', 'conta'])->orderBy('id')->get() as $acesso) {
            $mycars[] = [
                'id' => (string) $acesso->id,
                'perfil' => $acesso->perfil,
                'nome' => $acesso->nome(),
                'atual' => $acessoAtivoId === $acesso->id,
            ];
        }

        $tem = array_column($mycars, 'perfil');

        return [
            'user' => $usuario->dadosPublicos(),
            'mycars' => $mycars,
            'pode_criar' => array_values(array_diff(self::PERFIS, $tem)),
        ];
    }
}
