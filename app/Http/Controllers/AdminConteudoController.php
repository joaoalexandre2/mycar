<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Visão de suporte do operador: o que um usuário cadastrou, só para leitura.
 *
 * Cada abertura fica gravada em `acessos_admin` (quem viu, de quem, quando),
 * antes de qualquer dado ser devolvido. Os dados de oficina e de conta são
 * lidos com DB::table, sem os escopos de isolamento dos models: é de propósito
 * e só chega aqui quem passou por `super.admin`.
 */
class AdminConteudoController extends Controller
{
    /** Linhas devolvidas por seção; o total real vai junto. */
    private const LIMITE_POR_SECAO = 500;

    /** Colunas internas que não ajudam o suporte (ou apontam para arquivos). */
    private const COLUNAS_OCULTAS = [
        'oficina_id',
        'conta_id',
        'caminho',
        'caminho_miniatura',
        'ipva_alertado_ano',
        'licenciamento_alertado_ano',
        'revisao_alertada_em',
    ];

    /** Seções por tipo de usuário: chave => [título, tabela, coluna do dono]. */
    private const SECOES_OFICINA = [
        'oficina' => ['Dados da oficina', 'oficinas', 'id'],
        'clientes' => ['Clientes', 'clientes', 'oficina_id'],
        'veiculos' => ['Veículos', 'veiculos', 'oficina_id'],
        'ordens_servico' => ['Ordens de serviço', 'ordens_servico', 'oficina_id'],
        'manutencoes' => ['Manutenções', 'manutencoes', 'oficina_id'],
    ];

    private const SECOES_CONTA = [
        'veiculos' => ['Veículos', 'veiculos_conta', 'conta_id'],
        'servicos' => ['Serviços', 'servicos_conta', 'conta_id'],
        'abastecimentos' => ['Abastecimentos', 'abastecimentos', 'conta_id'],
        'seguros' => ['Seguros', 'seguros', 'conta_id'],
        'documentos' => ['Documentos', 'documentos_conta', 'conta_id'],
        'codigos_pecas' => ['Códigos de peças', 'codigos_pecas', 'conta_id'],
        'fichas_tecnicas' => ['Fichas técnicas', 'fichas_tecnicas_conta', 'conta_id'],
        'fotos' => ['Fotos (sem o arquivo)', 'fotos_veiculo_conta', 'conta_id'],
    ];

    public function mostrar(Request $request, int $id)
    {
        $usuario = DB::table('users')->where('id', $id)->first();

        if (!$usuario) {
            return response()->json(['message' => 'Usuário não encontrado.'], 404);
        }

        $perfil = $usuario->perfil ?? 'oficina';
        $ehOficina = $perfil === 'oficina';
        $dono = $ehOficina ? $usuario->oficina_id : $usuario->conta_id;

        // O registro vem antes da leitura: se a gravação falhar, nada é mostrado.
        DB::table('acessos_admin')->insert([
            'admin_id' => $request->user()->id,
            'usuario_id' => $usuario->id,
            'perfil' => $perfil,
            'ip' => $request->ip(),
            'created_at' => now(),
        ]);

        $secoes = [];

        foreach ($ehOficina ? self::SECOES_OFICINA : self::SECOES_CONTA as $chave => [$titulo, $tabela, $coluna]) {
            $consulta = DB::table($tabela)->where($coluna, $dono ?? 0);
            $total = (clone $consulta)->count();

            $linhas = $consulta
                ->orderByDesc('id')
                ->limit(self::LIMITE_POR_SECAO)
                ->get()
                ->map(fn (object $linha) => $this->limpar($linha))
                ->values();

            $secoes[] = [
                'chave' => $chave,
                'titulo' => $titulo,
                'total' => $total,
                'linhas' => $linhas,
            ];
        }

        return response()->json([
            'usuario' => [
                'id' => $usuario->id,
                'nome' => $usuario->name,
                'email' => $usuario->email,
                'perfil' => $perfil,
            ],
            'limite_por_secao' => self::LIMITE_POR_SECAO,
            'secoes' => $secoes,
        ]);
    }

    public function acessos(Request $request)
    {
        $porPaginaSolicitada = (int) $request->query('per_page', 30);
        $porPagina = $porPaginaSolicitada > 0 ? min($porPaginaSolicitada, 100) : 30;

        $paginador = DB::table('acessos_admin')
            ->join('users as admin', 'admin.id', '=', 'acessos_admin.admin_id')
            ->join('users as alvo', 'alvo.id', '=', 'acessos_admin.usuario_id')
            ->orderByDesc('acessos_admin.id')
            ->select([
                'acessos_admin.id',
                'acessos_admin.perfil',
                'acessos_admin.ip',
                'acessos_admin.created_at',
                'admin.name as admin_nome',
                'admin.email as admin_email',
                'alvo.id as usuario_id',
                'alvo.name as usuario_nome',
                'alvo.email as usuario_email',
            ])
            ->paginate($porPagina);

        return response()->json([
            'data' => collect($paginador->items())->map(fn ($linha) => [
                'id' => $linha->id,
                'em' => Carbon::parse($linha->created_at)->toIso8601String(),
                'ip' => $linha->ip,
                'perfil' => $linha->perfil,
                'admin' => ['nome' => $linha->admin_nome, 'email' => $linha->admin_email],
                'usuario' => ['id' => $linha->usuario_id, 'nome' => $linha->usuario_nome, 'email' => $linha->usuario_email],
            ])->values(),
            'meta' => [
                'current_page' => $paginador->currentPage(),
                'last_page' => $paginador->lastPage(),
                'per_page' => $paginador->perPage(),
                'total' => $paginador->total(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function limpar(object $linha): array
    {
        return collect((array) $linha)
            ->except(self::COLUNAS_OCULTAS)
            ->all();
    }
}
