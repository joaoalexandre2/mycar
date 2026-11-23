<?php

namespace App\Http\Controllers;

use App\Mail\ConviteMotoristaEmail;
use App\Mail\ConvitePessoaEmail;
use App\Models\AcessoFrotaEvento;
use App\Models\GrupoUsuario;
use App\Models\Motorista;
use App\Models\User;
use App\Support\PermissoesFrota;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
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

        // Cadastros de motorista ligados a essas pessoas, de uma vez (escopo da conta: nunca aparece cadastro de outra).
        $motoristas = Motorista::query()
            ->whereIn('user_id', $pessoas->pluck('id'))
            ->get(['id', 'nome', 'user_id'])
            ->keyBy('user_id');

        return response()->json([
            'pessoas' => $pessoas->map(fn (User $u) => $this->formatar($u, $grupos, $motoristas))->values(),
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

        // Mesmo token e intervalo do "esqueci minha senha" (o Password::sendResetLink de antes também
        // não reenviava dentro do intervalo), mas com texto de convite: quem recebe nunca pediu nada.
        $tokens = Password::broker()->getRepository();
        if (!$tokens->recentlyCreatedToken($usuario)) {
            Mail::to($usuario->email)->send(new ConvitePessoaEmail(
                $usuario,
                $usuario->urlParaDefinirSenha($tokens->create($usuario), convite: true),
                $request->user()->name,
                $dados['papel'],
                User::minutosParaExpirarLinkDeSenha(),
            ));
        }

        AcessoFrotaEvento::registrar($request->user()->id, 'pessoa', $usuario->id, 'convidou', [
            'nome' => $usuario->name,
            'papel' => $dados['papel'],
        ]);

        return response()->json($this->formatar($usuario, GrupoUsuario::with('membros:users.id')->get()), 201);
    }

    /**
     * Reenvia o convite a uma pessoa desta frota que ainda não entrou no sistema: link novo para definir
     * a senha (o anterior deixa de valer), com o texto de convite do papel dela. Mesmas regras do reenvio
     * do motorista: 429 dentro do intervalo do broker de senha (1 minuto), 422 para quem já entrou.
     * Pessoa de outra conta ou fora da frota: 404 (nem se confirma que existe).
     */
    public function reenviarConvite(Request $request, $id)
    {
        $usuario = User::query()
            ->where('conta_id', app('conta.atual'))
            ->where('perfil', 'frota')
            ->find($id);

        if (!$usuario) {
            return response()->json(['message' => 'Pessoa não encontrada na frota.'], 404);
        }

        if ($this->estadoDeAcesso($usuario, $request->user()) === Motorista::ACESSO_ATIVO) {
            return response()->json(['message' => 'Esta pessoa já entra no sistema. Se esqueceu a senha, ela pode usar "Esqueci minha senha".'], 422);
        }

        $tokens = Password::broker()->getRepository();

        // Mesmo intervalo do "esqueci minha senha": evita encher a caixa da pessoa com convites repetidos.
        if ($tokens->recentlyCreatedToken($usuario)) {
            return response()->json(['message' => 'Acabamos de enviar um convite para esta pessoa. Aguarde um minuto para reenviar.'], 429);
        }

        $papel = $usuario->papelNaConta();
        $url = $usuario->urlParaDefinirSenha($tokens->create($usuario), convite: true);
        $minutos = User::minutosParaExpirarLinkDeSenha();

        // O convite de gestor/administrador explica o papel; quem é motorista recebe o texto de motorista.
        Mail::to($usuario->email)->send($papel === 'motorista'
            ? new ConviteMotoristaEmail($usuario, $url, $request->user()->name, $minutos)
            : new ConvitePessoaEmail($usuario, $url, $request->user()->name, $papel, $minutos));

        AcessoFrotaEvento::registrar($request->user()->id, 'pessoa', $usuario->id, 'reenviou_convite', [
            'nome' => $usuario->name,
            'papel' => $papel,
        ]);

        return response()->json([
            'message' => "Convite reenviado para {$usuario->email}.",
            'id' => $usuario->id,
            'acesso' => Motorista::CONVITE_ENVIADO,
        ]);
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

    /**
     * Liga o login de uma pessoa (papel motorista, desta conta) a um cadastro de
     * motorista, para ela conseguir reservar. Duas formas, uma por vez:
     * - motorista_id: um cadastro existente desta conta que ainda não tem login;
     * - centro_custo_id (+ telefone/CNH opcionais): cria o cadastro com o nome da pessoa,
     *   com as mesmas regras de Cadastros > Motoristas e o centro de custo obrigatório.
     * Pessoa ou motorista de outra conta: 404 (nem se confirma que existem).
     */
    public function ligarMotorista(Request $request, $id)
    {
        $pessoaDaConta = fn () => User::query()
            ->where('conta_id', app('conta.atual'))
            ->where('perfil', 'frota');

        if (!$pessoaDaConta()->find($id)) {
            return response()->json(['message' => 'Pessoa não encontrada na frota.'], 404);
        }

        $dados = $request->validate([
            'motorista_id' => ['required_without:centro_custo_id', 'prohibits:centro_custo_id', 'integer'],
            'centro_custo_id' => ['required_without:motorista_id', 'integer'],
        ], [
            'motorista_id.required_without' => 'Escolha um motorista cadastrado ou informe o centro de custo para criar o cadastro.',
            'centro_custo_id.required_without' => 'Escolha um motorista cadastrado ou informe o centro de custo para criar o cadastro.',
            'motorista_id.prohibits' => 'Escolha só uma forma: ligar a um motorista cadastrado ou criar o cadastro.',
        ]);

        $criar = !isset($dados['motorista_id']);
        $dadosDoCadastro = [];

        if ($criar) {
            // Mesmas regras do cadastro de motorista (centro ativo e da própria conta), com o centro obrigatório.
            $dadosDoCadastro = validator(
                array_merge($request->only(['telefone', 'cnh_numero', 'cnh_validade']), [
                    'nome' => $pessoaDaConta()->find($id)->name,
                    'centro_custo_id' => $dados['centro_custo_id'],
                ]),
                array_merge(Motorista::regrasDeCadastro(), [
                    'centro_custo_id' => array_merge(['required'], Motorista::regrasDeCadastro()['centro_custo_id']),
                ]),
                ['nome.unique' => 'Já existe um motorista com o nome desta pessoa. Ligue a pessoa a esse cadastro em vez de criar outro.'],
            )->validate();
        }

        return DB::transaction(function () use ($request, $id, $pessoaDaConta, $criar, $dados, $dadosDoCadastro) {
            // A trava na linha da pessoa impede duas ligações simultâneas do mesmo login.
            $usuario = $pessoaDaConta()->lockForUpdate()->find($id);

            if (!$usuario) {
                return response()->json(['message' => 'Pessoa não encontrada na frota.'], 404);
            }

            if ($usuario->papelNaConta() !== 'motorista') {
                return response()->json(['message' => 'Só quem tem o papel de motorista pode ser ligado a um cadastro de motorista.'], 422);
            }

            // Procura em todas as contas (defesa): um login nunca fica ligado a dois cadastros.
            $jaLigado = Motorista::withoutGlobalScope('conta')->where('user_id', $usuario->id)->first();

            if ($jaLigado && ($criar || (int) $jaLigado->id !== (int) $dados['motorista_id'])) {
                return response()->json(['message' => 'Esta pessoa já está ligada a outro cadastro de motorista.'], 422);
            }

            if ($criar) {
                $motorista = Motorista::create($dadosDoCadastro);
            } else {
                // Escopo da conta: motorista de outra conta é 404, como se não existisse.
                $motorista = Motorista::query()->lockForUpdate()->find($dados['motorista_id']);

                if (!$motorista) {
                    return response()->json(['message' => 'Motorista não encontrado.'], 404);
                }

                if ($jaLigado) {
                    // Já estava ligado a este mesmo cadastro: nada a mudar.
                    return response()->json($this->formatar($usuario, GrupoUsuario::with('membros:users.id')->get()));
                }

                if ($motorista->user_id !== null) {
                    return response()->json(['message' => 'Este motorista já tem outro login ligado.'], 422);
                }
            }

            $motorista->forceFill(['user_id' => $usuario->id])->save();

            AcessoFrotaEvento::registrar($request->user()->id, 'pessoa', $usuario->id, 'ligou_motorista', [
                'nome' => $usuario->name,
                'motorista_id' => $motorista->id,
                'motorista' => $motorista->nome,
                'criou_cadastro' => $criar,
            ]);

            return response()->json(
                $this->formatar($usuario, GrupoUsuario::with('membros:users.id')->get()),
                $criar ? 201 : 200,
            );
        });
    }

    /** @return array<string, mixed> */
    /**
     * Uma pessoa com o que ela pode fazer hoje (papel + grupos). Espelha User::temPermissao e User::podeVerMenu,
     * mas com os grupos já carregados, para não consultar o banco a cada permissão.
     *
     * Também diz a que cadastro de motorista o login está ligado (motorista: {id, nome} ou null).
     *
     * @param  Collection<int, GrupoUsuario>  $grupos
     * @param  Collection<int, Motorista>|null  $motoristas  cadastros da conta já carregados, por user_id
     * @return array<string, mixed>
     */
    private function formatar(User $usuario, $grupos, ?Collection $motoristas = null): array
    {
        // Escopo da conta (PertenceAConta): um cadastro de outra conta nunca aparece aqui.
        $motorista = $motoristas !== null
            ? $motoristas->get($usuario->id)
            : Motorista::query()->where('user_id', $usuario->id)->first(['id', 'nome', 'user_id']);

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
            'motorista' => $motorista ? ['id' => $motorista->id, 'nome' => $motorista->nome] : null,
            'acesso' => $this->estadoDeAcesso($usuario, request()->user()),
        ];
    }

    /**
     * Mesmo critério de Motorista::estadoDeAcesso: "ativo" se a pessoa já entrou (ultimo_acesso_em),
     * senão "convite_enviado". Quem está fazendo a requisição está logado, então conta como ativo
     * mesmo que a sessão seja anterior ao registro do último acesso.
     */
    private function estadoDeAcesso(User $usuario, ?User $quemPede): string
    {
        $jaEntrou = $usuario->ultimo_acesso_em !== null || ($quemPede && $quemPede->id === $usuario->id);

        return $jaEntrou ? Motorista::ACESSO_ATIVO : Motorista::CONVITE_ENVIADO;
    }
}
