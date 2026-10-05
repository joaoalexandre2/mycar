<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ConfirmacaoEmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Painel do operador da plataforma.
 *
 * Mostra só números e dados de conta (quem se cadastrou, se confirmou o
 * e-mail, quanto usa). Nunca devolve o conteúdo que as oficinas cadastram
 * (clientes, CPFs, telefones, veículos), que é dado pessoal de terceiros e
 * continua isolado por oficina.
 *
 * As consultas usam DB::table de propósito: os models de domínio têm o
 * escopo PertenceAOficina, que falha fechado sem uma oficina vinculada.
 */
class AdminController extends Controller
{
    private const TABELAS_DE_USO = [
        'clientes' => 'clientes',
        'veiculos' => 'veiculos',
        'ordens_servico' => 'ordens_servico',
        'manutencoes' => 'manutencoes',
    ];

    public function resumo()
    {
        $agora = now();

        return response()->json([
            // Só as oficinas em funcionamento: as arquivadas (dono migrou de perfil)
            // não contam, nem o que elas guardam.
            'oficinas' => DB::table('oficinas')->whereNull('arquivada_em')->count(),
            'contas' => [
                'pessoa' => DB::table('contas')->where('tipo', 'pessoa')->count(),
                'frota' => DB::table('contas')->where('tipo', 'frota')->count(),
            ],
            'usuarios' => DB::table('users')->count(),
            'email_pendente' => DB::table('users')->whereNull('email_verified_at')->count(),
            'cadastros_7_dias' => DB::table('users')->where('created_at', '>=', $agora->copy()->subDays(7))->count(),
            'cadastros_30_dias' => DB::table('users')->where('created_at', '>=', $agora->copy()->subDays(30))->count(),
            'ativos_7_dias' => DB::table('users')->where('ultimo_acesso_em', '>=', $agora->copy()->subDays(7))->count(),
            'totais' => collect(self::TABELAS_DE_USO)
                ->map(fn (string $tabela) => DB::table($tabela)
                    ->whereIn('oficina_id', DB::table('oficinas')->whereNull('arquivada_em')->select('id'))
                    ->count())
                ->all(),
        ]);
    }

    public function contas(Request $request)
    {
        $busca = trim((string) $request->query('busca', ''));
        $porPaginaSolicitada = (int) $request->query('per_page', 15);
        $porPagina = $porPaginaSolicitada > 0 ? min($porPaginaSolicitada, 100) : 15;

        $consulta = DB::table('users')
            ->leftJoin('oficinas', 'oficinas.id', '=', 'users.oficina_id')
            ->leftJoin('contas', 'contas.id', '=', 'users.conta_id')
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.perfil',
                'users.email_verified_at',
                'users.created_at',
                'users.ultimo_acesso_em',
                'users.is_super_admin',
                'oficinas.id as oficina_id',
                'oficinas.nome as oficina_nome',
                'contas.nome as conta_nome',
            ]);

        // Veículos cadastrados pelas contas pessoa e frota (os das oficinas
        // entram em total_veiculos).
        $consulta->selectSub(
            DB::table('veiculos_conta')
                ->selectRaw('count(*)')
                ->whereColumn('veiculos_conta.conta_id', 'users.conta_id'),
            'total_veiculos_conta'
        );

        foreach (self::TABELAS_DE_USO as $chave => $tabela) {
            $consulta->selectSub(
                DB::table($tabela)
                    ->selectRaw('count(*)')
                    ->whereColumn("{$tabela}.oficina_id", 'users.oficina_id'),
                "total_{$chave}"
            );
        }

        if ($busca !== '') {
            $consulta->where(function ($query) use ($busca) {
                $query->where('users.name', 'like', "%{$busca}%")
                    ->orWhere('users.email', 'like', "%{$busca}%")
                    ->orWhere('oficinas.nome', 'like', "%{$busca}%")
                    ->orWhere('contas.nome', 'like', "%{$busca}%");
            });
        }

        $paginador = $consulta
            ->orderByDesc('users.created_at')
            ->orderByDesc('users.id')
            ->paginate($porPagina);

        return response()->json([
            'data' => collect($paginador->items())->map(fn ($linha) => [
                'id' => $linha->id,
                'nome' => $linha->name,
                'email' => $linha->email,
                'email_confirmado' => $linha->email_verified_at !== null,
                'email_confirmado_em' => $this->iso($linha->email_verified_at),
                'cadastro_em' => $this->iso($linha->created_at),
                'ultimo_acesso_em' => $this->iso($linha->ultimo_acesso_em),
                'admin' => (bool) $linha->is_super_admin,
                'perfil' => $this->perfilDe($linha),
                'conta' => $this->perfilDe($linha) === 'oficina' ? null : $linha->conta_nome,
                'oficina' => $this->perfilDe($linha) !== 'oficina' || $linha->oficina_id === null ? null : [
                    'id' => $linha->oficina_id,
                    'nome' => $linha->oficina_nome,
                ],
                // Quem migrou de perfil tem a oficina antiga arquivada: o uso
                // mostrado é o do perfil atual.
                'totais' => $this->perfilDe($linha) === 'oficina'
                    ? [
                        'clientes' => (int) $linha->total_clientes,
                        'veiculos' => (int) $linha->total_veiculos,
                        'ordens_servico' => (int) $linha->total_ordens_servico,
                        'manutencoes' => (int) $linha->total_manutencoes,
                    ]
                    : [
                        'clientes' => 0,
                        'veiculos' => (int) $linha->total_veiculos_conta,
                        'ordens_servico' => 0,
                        'manutencoes' => 0,
                    ],
            ])->values(),
            'meta' => [
                'current_page' => $paginador->currentPage(),
                'last_page' => $paginador->lastPage(),
                'per_page' => $paginador->perPage(),
                'total' => $paginador->total(),
            ],
        ]);
    }

    private function perfilDe(object $linha): string
    {
        return $linha->perfil ?? 'oficina';
    }

    /**
     * DB::table devolve datas cruas, sem fuso ("2026-10-04 22:59:00"), e o
     * navegador as leria como horário local. Em ISO 8601 com o fuso do app
     * elas chegam corretas em qualquer lugar.
     */
    private function iso(?string $valor): ?string
    {
        return $valor === null ? null : Carbon::parse($valor)->toIso8601String();
    }

    public function reenviarConfirmacao(int $id, ConfirmacaoEmailService $confirmacao)
    {
        $usuario = User::find($id);

        if (!$usuario) {
            return response()->json(['message' => 'Usuário não encontrado.'], 404);
        }

        if ($usuario->email_verified_at) {
            return response()->json(['message' => 'Este e-mail já foi confirmado.'], 422);
        }

        $confirmacao->enviar($usuario);

        return response()->json([
            'message' => "Link de confirmação reenviado para {$usuario->email}.",
        ]);
    }
}
