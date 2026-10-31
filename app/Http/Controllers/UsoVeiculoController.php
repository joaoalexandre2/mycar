<?php

namespace App\Http\Controllers;

use App\Models\Motorista;
use App\Models\UsoVeiculo;
use App\Models\UsoVeiculoEvento;
use App\Models\UsoVeiculoFoto;
use App\Models\VeiculoConta;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Agenda da frota: quem vai usar qual veículo e quando, com a saída e a chegada
 * (km do hodômetro e foto do painel). Só o perfil frota. Cada saída ou chegada
 * sobe o km atual do veículo, que nunca desce sozinho.
 *
 * Papéis: administrador e gestor enxergam e operam tudo; o motorista só vê e
 * opera as próprias reservas (as rotas de edição, exclusão e correção de km
 * ficam atrás de `papel:administrador,gestor`). Toda mudança grava um evento
 * em usos_veiculo_eventos.
 */
class UsoVeiculoController extends Controller
{
    private const MAX_DIAS_DE_RESERVA = 31;

    private const KM_MAXIMO = 9_999_999;

    private const MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    private const RELACOES = ['veiculo', 'motorista', 'centroCusto', 'saidaPor', 'chegadaPor'];

    /** SQLSTATE do PostgreSQL para violação de restrição de exclusão (reserva sobreposta). */
    private const SQLSTATE_EXCLUSAO = '23P01';

    // ------------------------------------------------------------------ agenda

    /** Usos que tocam o período pedido (padrão: o mês corrente). */
    public function index(Request $request)
    {
        $filtros = $request->validate([
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date', 'after_or_equal:de'],
            'veiculo_id' => ['nullable', 'integer'],
            'motorista_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(UsoVeiculo::STATUS)],
        ]);

        $de = isset($filtros['de']) ? Carbon::parse($filtros['de'])->startOfDay() : now()->startOfMonth();
        $ate = isset($filtros['ate']) ? Carbon::parse($filtros['ate'])->endOfDay() : now()->endOfMonth();

        $usos = UsoVeiculo::with(self::RELACOES)
            ->where('inicio_previsto', '<=', $ate)
            ->where('fim_previsto', '>=', $de)
            ->when($this->ehMotorista($request), fn ($q) => $q->where('motorista_id', $this->meuMotoristaId($request) ?? 0))
            ->when($filtros['veiculo_id'] ?? null, fn ($q, $v) => $q->where('veiculo_conta_id', $v))
            ->when($filtros['motorista_id'] ?? null, fn ($q, $m) => $q->where('motorista_id', $m))
            ->when($filtros['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->orderBy('inicio_previsto')
            ->get();

        return response()->json([
            'de' => $de->toDateString(),
            'ate' => $ate->toDateString(),
            'usos' => $usos->map(fn (UsoVeiculo $u) => $this->formatar($u))->values(),
        ]);
    }

    public function show(Request $request, $id)
    {
        $uso = $this->usoVisivel($request, $id);

        return $uso ? response()->json($this->formatar($uso->load(self::RELACOES))) : $this->naoEncontrado();
    }

    /** Trilha de auditoria de um uso (só administrador e gestor). */
    public function eventos($id)
    {
        $uso = UsoVeiculo::find($id);

        if (!$uso) {
            return $this->naoEncontrado();
        }

        return response()->json($uso->eventos()->with('usuario')->orderBy('id')->get()->map(fn (UsoVeiculoEvento $e) => [
            'id' => $e->id,
            'acao' => $e->acao,
            'usuario' => $e->usuario?->name,
            'dados' => $e->dados,
            'motivo' => $e->motivo,
            'em' => $e->created_at?->toIso8601String(),
        ])->values());
    }

    public function store(Request $request)
    {
        $dados = $this->validar($request);

        if ($this->ehMotorista($request)) {
            // O motorista só reserva para si mesmo.
            $meu = $this->meuMotoristaId($request);

            if (!$meu) {
                return response()->json(['message' => 'Seu login ainda não está ligado a um motorista cadastrado. Peça ao gestor.'], 422);
            }

            $dados['motorista_id'] = $meu;
        }

        $veiculo = VeiculoConta::find($dados['veiculo_conta_id']);

        try {
            return DB::transaction(function () use ($request, $dados, $veiculo) {
                $this->travar($dados['veiculo_conta_id'], $dados['motorista_id'] ?? null);

                if ($erro = $this->impedimentoDoMotorista($dados['motorista_id'] ?? null, $dados['fim_previsto'])) {
                    return $erro;
                }

                if ($erro = $this->conflito($dados['veiculo_conta_id'], $dados['motorista_id'] ?? null, $dados['inicio_previsto'], $dados['fim_previsto'])) {
                    return $erro;
                }

                $uso = UsoVeiculo::create($dados + [
                    // Sem centro informado, vale o padrão do veículo.
                    'centro_custo_id' => $dados['centro_custo_id'] ?? $veiculo->centro_custo_id,
                    'status' => 'agendado',
                    'origem' => 'agenda',
                ]);

                UsoVeiculoEvento::registrar($uso, 'reservou', $request->user()->id, [
                    'inicio_previsto' => $uso->inicio_previsto->toIso8601String(),
                    'fim_previsto' => $uso->fim_previsto->toIso8601String(),
                    'motorista_id' => $uso->motorista_id,
                ]);

                return response()->json($this->formatar($uso->load(self::RELACOES)), 201);
            });
        } catch (QueryException $e) {
            return $this->tratarConflitoDoBanco($e);
        }
    }

    public function update(Request $request, $id)
    {
        $uso = UsoVeiculo::find($id);

        if (!$uso) {
            return $this->naoEncontrado();
        }

        if (!in_array($uso->status, ['agendado', 'em_uso'], true)) {
            return response()->json(['message' => 'Só dá para editar um uso agendado ou em andamento.'], 422);
        }

        $dados = $this->validar($request);

        // Em andamento: o veículo e o início já aconteceram; só ajusta o resto.
        if ($uso->status === 'em_uso') {
            $dados = array_intersect_key($dados, array_flip(['fim_previsto', 'finalidade', 'observacoes', 'motorista_id', 'centro_custo_id']));
            $dados += ['inicio_previsto' => $uso->inicio_previsto, 'veiculo_conta_id' => $uso->veiculo_conta_id];
        }

        $motorista = array_key_exists('motorista_id', $dados) ? $dados['motorista_id'] : $uso->motorista_id;

        try {
            return DB::transaction(function () use ($request, $uso, $dados, $motorista) {
                $this->travar($dados['veiculo_conta_id'], $motorista);

                if ($erro = $this->impedimentoDoMotorista($motorista, $dados['fim_previsto'])) {
                    return $erro;
                }

                if ($erro = $this->conflito($dados['veiculo_conta_id'], $motorista, $dados['inicio_previsto'], $dados['fim_previsto'], $uso->id)) {
                    return $erro;
                }

                $antes = $this->instantaneo($uso);
                $uso->update($dados);
                $depois = $this->instantaneo($uso->fresh());

                $mudou = array_keys(array_filter($depois, fn ($v, $k) => $v !== $antes[$k], ARRAY_FILTER_USE_BOTH));

                if (in_array('centro_custo_id', $mudou, true)) {
                    $uso->registrarTrocaDeCentro($antes['centro_custo_id'], $request->user()->id);
                }

                if ($mudou) {
                    UsoVeiculoEvento::registrar($uso, 'editou', $request->user()->id, [
                        'antes' => array_intersect_key($antes, array_flip($mudou)),
                        'depois' => array_intersect_key($depois, array_flip($mudou)),
                    ]);
                }

                return response()->json($this->formatar($uso->fresh(self::RELACOES)));
            });
        } catch (QueryException $e) {
            return $this->tratarConflitoDoBanco($e);
        }
    }

    /**
     * Troca o centro de custo de um uso em qualquer situação (inclusive já
     * concluído), exigindo o motivo. O centro anterior fica no histórico.
     */
    public function trocarCentro(Request $request, $id)
    {
        $uso = UsoVeiculo::find($id);

        if (!$uso) {
            return $this->naoEncontrado();
        }

        $dados = $request->validate([
            'centro_custo_id' => ['required', 'integer', Rule::exists('centros_custo', 'id')->where('conta_id', app('conta.atual'))->where('ativo', true)],
            'motivo' => ['required', 'string', 'min:5', 'max:255'],
        ], [
            'centro_custo_id.exists' => 'Centro de custo não encontrado ou desativado.',
            'motivo.required' => 'Explique o motivo da troca do centro de custo.',
            'motivo.min' => 'Explique o motivo da troca do centro de custo (mínimo 5 letras).',
        ]);

        if ((int) $uso->centro_custo_id === (int) $dados['centro_custo_id']) {
            return response()->json(['message' => 'Este uso já está nesse centro de custo.'], 422);
        }

        DB::transaction(function () use ($request, $uso, $dados) {
            $anterior = $uso->centro_custo_id;
            $uso->update(['centro_custo_id' => $dados['centro_custo_id']]);
            $uso->registrarTrocaDeCentro($anterior, $request->user()->id, $dados['motivo']);
        });

        return response()->json($this->formatar($uso->fresh(self::RELACOES)));
    }

    /** Histórico de centros de custo de um uso, do mais antigo ao mais recente. */
    public function historicoDeCentros($id)
    {
        $uso = UsoVeiculo::find($id);

        if (!$uso) {
            return $this->naoEncontrado();
        }

        return response()->json(
            $uso->centrosHistorico()->with('usuario')->orderBy('id')->get()->map->formatar()->values()
        );
    }

    public function cancelar(Request $request, $id)
    {
        $uso = $this->usoVisivel($request, $id);

        if (!$uso) {
            return $this->naoEncontrado();
        }

        if ($uso->status !== 'agendado') {
            return response()->json(['message' => 'Só dá para cancelar um uso que ainda não saiu.'], 422);
        }

        $motivo = $request->validate(['motivo' => ['nullable', 'string', 'max:255']])['motivo'] ?? null;

        $uso->update(['status' => 'cancelado']);

        UsoVeiculoEvento::registrar($uso, 'cancelou', $request->user()->id, null, $motivo);

        return response()->json($this->formatar($uso->fresh(self::RELACOES)));
    }

    public function destroy(Request $request, $id)
    {
        $uso = UsoVeiculo::find($id);

        if (!$uso) {
            return $this->naoEncontrado();
        }

        // Uso que já saiu é histórico da frota: não se apaga (só o importado de planilha, para desfazer engano).
        if (!in_array($uso->status, ['agendado', 'cancelado'], true) && $uso->origem !== 'planilha') {
            return response()->json([
                'message' => 'Um uso que já saiu não pode ser apagado, porque faz parte do histórico. Corrija o km, se for o caso.',
            ], 422);
        }

        UsoVeiculoEvento::registrar($uso, 'apagou', $request->user()->id, ['uso_id' => $uso->id] + $this->instantaneo($uso));

        $uso->delete();

        return response()->json(['message' => 'Uso removido.']);
    }

    // ----------------------------------------------------------- saída e chegada

    public function saida(Request $request, $id)
    {
        $dados = $this->validarKm($request, 'km_saida');

        return DB::transaction(function () use ($request, $id, $dados) {
            $uso = $this->usoVisivel($request, $id, travar: true);

            if (!$uso) {
                return $this->naoEncontrado();
            }

            // Pedido repetido (clique duplo, rede instável): devolve o resultado já gravado.
            if ($uso->status === 'em_uso' && $uso->saida_por_user_id === $request->user()->id && $uso->km_saida === $dados['km_saida']) {
                return response()->json($this->formatar($uso->load(self::RELACOES)));
            }

            if ($uso->status !== 'agendado') {
                return response()->json(['message' => 'A saída só pode ser registrada em um uso agendado.'], 422);
            }

            $veiculo = VeiculoConta::whereKey($uso->veiculo_conta_id)->lockForUpdate()->first();

            if (!$uso->motorista) {
                return response()->json(['message' => 'Esta reserva não tem motorista. Edite a reserva e informe quem vai dirigir.'], 422);
            }

            if ($motivo = $uso->motorista->impedimentoParaDirigir(now())) {
                return response()->json(['message' => $motivo], 422);
            }

            $emUso = UsoVeiculo::with('motorista')
                ->where('veiculo_conta_id', $uso->veiculo_conta_id)
                ->where('status', 'em_uso')
                ->first();

            if ($emUso) {
                $quem = $emUso->motorista?->nome ?? 'outro uso';

                return response()->json([
                    'message' => "Este veículo ainda está em uso ({$quem}): a chegada dele não foi registrada.",
                ], 422);
            }

            if ($erro = $this->kmMenorQueOUltimo($veiculo, $dados['km_saida'])) {
                return $erro;
            }

            $origem = $request->hasFile('foto') ? 'foto' : 'manual';

            $uso->update([
                'status' => 'em_uso',
                'km_saida' => $dados['km_saida'],
                'km_saida_origem' => $origem,
                'saida_em' => now(),
                'saida_por_user_id' => $request->user()->id,
            ]);

            if ($request->hasFile('foto')) {
                $this->guardarFoto($uso, 'saida', $request->file('foto'), $request->user()->id);
            }

            $this->subirKm($veiculo, $dados['km_saida']);

            UsoVeiculoEvento::registrar($uso, 'saiu', $request->user()->id, ['km' => $dados['km_saida'], 'origem' => $origem]);

            return response()->json($this->formatar($uso->fresh(self::RELACOES)));
        });
    }

    public function chegada(Request $request, $id)
    {
        $dados = $this->validarKm($request, 'km_chegada');

        return DB::transaction(function () use ($request, $id, $dados) {
            $uso = $this->usoVisivel($request, $id, travar: true);

            if (!$uso) {
                return $this->naoEncontrado();
            }

            if ($uso->status === 'concluido' && $uso->chegada_por_user_id === $request->user()->id && $uso->km_chegada === $dados['km_chegada']) {
                return response()->json($this->formatar($uso->load(self::RELACOES)));
            }

            if ($uso->status !== 'em_uso') {
                return response()->json(['message' => 'A chegada só pode ser registrada em um uso em andamento.'], 422);
            }

            $veiculo = VeiculoConta::whereKey($uso->veiculo_conta_id)->lockForUpdate()->first();

            if ($dados['km_chegada'] < $uso->km_saida) {
                return response()->json([
                    'message' => "O km de chegada ({$dados['km_chegada']}) é menor que o de saída ({$uso->km_saida}). Confira o hodômetro.",
                ], 422);
            }

            $origem = $request->hasFile('foto') ? 'foto' : 'manual';

            $uso->update([
                'status' => 'concluido',
                'km_chegada' => $dados['km_chegada'],
                'km_chegada_origem' => $origem,
                'chegada_em' => now(),
                'chegada_por_user_id' => $request->user()->id,
            ]);

            if ($request->hasFile('foto')) {
                $this->guardarFoto($uso, 'chegada', $request->file('foto'), $request->user()->id);
            }

            $this->subirKm($veiculo, $dados['km_chegada']);

            UsoVeiculoEvento::registrar($uso, 'chegou', $request->user()->id, ['km' => $dados['km_chegada'], 'origem' => $origem]);

            return response()->json($this->formatar($uso->fresh(self::RELACOES)));
        });
    }

    /** Envia ou troca a foto do hodômetro de um momento que já foi registrado (a anterior é mantida). */
    public function foto(Request $request, $id, $momento)
    {
        $uso = $this->usoVisivel($request, $id);

        if (!$uso) {
            return $this->naoEncontrado();
        }

        abort_unless(in_array($momento, UsoVeiculo::MOMENTOS, true), 404);

        $km = $momento === 'saida' ? $uso->km_saida : $uso->km_chegada;

        if ($km === null) {
            return response()->json(['message' => 'Registre o km antes de anexar a foto do hodômetro.'], 422);
        }

        $request->validate(['foto' => $this->regraDaFoto(true)], $this->mensagensDaFoto());

        $this->guardarFoto($uso, $momento, $request->file('foto'), $request->user()->id);

        UsoVeiculoEvento::registrar($uso, 'anexou_foto', $request->user()->id, ['momento' => $momento]);

        return response()->json($this->formatar($uso->fresh(self::RELACOES)));
    }

    /** Corrige a quilometragem atual do veículo (único jeito de ela diminuir), sempre com motivo. */
    public function definirKm(Request $request, $id)
    {
        $veiculo = VeiculoConta::find($id);

        if (!$veiculo) {
            return response()->json(['message' => 'Veículo não encontrado.'], 404);
        }

        $dados = $request->validate([
            'km' => ['required', 'integer', 'min:0', 'max:' . self::KM_MAXIMO],
            'motivo' => ['required', 'string', 'min:5', 'max:255'],
        ], [
            'motivo.required' => 'Explique o motivo da correção do km.',
            'motivo.min' => 'Explique o motivo da correção do km (mínimo 5 letras).',
        ]);

        DB::transaction(function () use ($request, $veiculo, $dados) {
            $veiculo = VeiculoConta::whereKey($veiculo->id)->lockForUpdate()->first();
            $antes = $veiculo->km_atual;

            $veiculo->forceFill(['km_atual' => $dados['km'], 'km_atualizado_em' => now()])->save();

            UsoVeiculoEvento::registrar(null, 'corrigiu_km', $request->user()->id, [
                'antes' => $antes,
                'depois' => $dados['km'],
            ], $dados['motivo'], $veiculo->id);
        });

        $veiculo->refresh();

        return response()->json([
            'id' => $veiculo->id,
            'km_atual' => $veiculo->km_atual,
            'km_atualizado_em' => $veiculo->km_atualizado_em ? Carbon::parse($veiculo->km_atualizado_em)->toIso8601String() : null,
        ]);
    }

    // ---------------------------------------------------------------- internos

    private function ehMotorista(Request $request): bool
    {
        return $request->user()->papelNaConta() === 'motorista';
    }

    private function meuMotoristaId(Request $request): ?int
    {
        return $request->user()->motoristaVinculado()?->id;
    }

    /** O uso, respeitando o papel: o motorista só enxerga os próprios (os demais dão "não encontrado"). */
    private function usoVisivel(Request $request, $id, bool $travar = false): ?UsoVeiculo
    {
        $query = UsoVeiculo::with('motorista')->whereKey($id);

        if ($this->ehMotorista($request)) {
            $query->where('motorista_id', $this->meuMotoristaId($request) ?? 0);
        }

        return $travar ? $query->lockForUpdate()->first() : $query->first();
    }

    /** Trava o veículo e o motorista até o fim da transação, para duas requisições não se atropelarem. */
    private function travar(int $veiculoId, ?int $motoristaId): void
    {
        VeiculoConta::whereKey($veiculoId)->lockForUpdate()->first();

        if ($motoristaId) {
            Motorista::whereKey($motoristaId)->lockForUpdate()->first();
        }
    }

    /** CNH vencida (ou ausente) e motorista desativado bloqueiam a reserva e a saída. */
    private function impedimentoDoMotorista(?int $motoristaId, $fim): ?JsonResponse
    {
        if (!$motoristaId) {
            return null;
        }

        $motorista = Motorista::find($motoristaId);
        $motivo = $motorista?->impedimentoParaDirigir($fim instanceof \DateTimeInterface ? $fim : Carbon::parse($fim));

        return $motivo ? response()->json(['message' => $motivo], 422) : null;
    }

    /** @return array<string, mixed> */
    private function instantaneo(UsoVeiculo $uso): array
    {
        return [
            'veiculo_conta_id' => $uso->veiculo_conta_id,
            'motorista_id' => $uso->motorista_id,
            'centro_custo_id' => $uso->centro_custo_id,
            'inicio_previsto' => $uso->inicio_previsto->toIso8601String(),
            'fim_previsto' => $uso->fim_previsto->toIso8601String(),
            'finalidade' => $uso->finalidade,
            'observacoes' => $uso->observacoes,
            'status' => $uso->status,
        ];
    }

    /** Se o banco (PostgreSQL) recusou uma reserva sobreposta que passou na checagem do código. */
    private function tratarConflitoDoBanco(QueryException $e): JsonResponse
    {
        if ((string) $e->getCode() === self::SQLSTATE_EXCLUSAO) {
            return response()->json(['message' => 'O veículo ou o motorista já está reservado nesse horário.'], 422);
        }

        throw $e;
    }

    /** @return array<string, mixed> */
    private function validar(Request $request): array
    {
        $conta = app('conta.atual');

        $dados = $request->validate([
            'veiculo_conta_id' => ['required', 'integer', Rule::exists('veiculos_conta', 'id')->where('conta_id', $conta)],
            'motorista_id' => ['nullable', 'integer', Rule::exists('motoristas', 'id')->where('conta_id', $conta)->where('ativo', true)],
            'centro_custo_id' => ['nullable', 'integer', Rule::exists('centros_custo', 'id')->where('conta_id', $conta)->where('ativo', true)],
            'inicio_previsto' => ['required', 'date'],
            'fim_previsto' => ['required', 'date', 'after:inicio_previsto'],
            'finalidade' => ['nullable', 'string', 'max:160'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ], [
            'fim_previsto.after' => 'O fim da reserva precisa ser depois do início.',
            'veiculo_conta_id.exists' => 'Veículo não encontrado.',
            'motorista_id.exists' => 'Motorista não encontrado ou desativado.',
            'centro_custo_id.exists' => 'Centro de custo não encontrado ou desativado.',
        ]);

        $inicio = Carbon::parse($dados['inicio_previsto']);
        $fim = Carbon::parse($dados['fim_previsto']);

        if ($inicio->diffInDays($fim) > self::MAX_DIAS_DE_RESERVA) {
            abort(response()->json([
                'message' => 'Uma reserva pode ter no máximo ' . self::MAX_DIAS_DE_RESERVA . ' dias.',
                'errors' => ['fim_previsto' => ['A reserva passa de ' . self::MAX_DIAS_DE_RESERVA . ' dias.']],
            ], 422));
        }

        $dados['inicio_previsto'] = $inicio;
        $dados['fim_previsto'] = $fim;

        return $dados;
    }

    /** @return array<string, mixed> */
    private function validarKm(Request $request, string $campo): array
    {
        return $request->validate([
            $campo => ['required', 'integer', 'min:0', 'max:' . self::KM_MAXIMO],
            'foto' => $this->regraDaFoto(false),
        ], $this->mensagensDaFoto());
    }

    /** @return array<int, string> */
    private function regraDaFoto(bool $obrigatoria): array
    {
        // Tipo conferido pelo conteúdo do arquivo, não pela extensão.
        return [$obrigatoria ? 'required' : 'nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120'];
    }

    /** @return array<string, string> */
    private function mensagensDaFoto(): array
    {
        return [
            'foto.max' => 'A foto é grande demais (máximo 5 MB).',
            'foto.mimetypes' => 'Envie uma imagem JPEG, PNG ou WebP.',
        ];
    }

    /** O mesmo veículo ou o mesmo motorista não pode ter dois usos no mesmo horário. */
    private function conflito(int $veiculoId, ?int $motoristaId, $inicio, $fim, ?int $ignorarId = null): ?JsonResponse
    {
        $doVeiculo = UsoVeiculo::with(['motorista', 'veiculo', 'centroCusto'])
            ->sobrepostos($inicio, $fim)
            ->where('veiculo_conta_id', $veiculoId)
            ->when($ignorarId, fn ($q) => $q->where('id', '!=', $ignorarId))
            ->orderBy('inicio_previsto')
            ->first();

        if ($doVeiculo) {
            // Diz com quem o veículo está, para quem reserva saber a quem pedir ou que horário escolher.
            $situacao = $doVeiculo->status === 'em_uso' ? 'está em uso agora' : 'já está reservado nesse horário';

            return $this->conflitoResposta(
                "O veículo {$this->nomeDoVeiculo($doVeiculo)} {$situacao}: {$doVeiculo->quemUsa()}, {$doVeiculo->janelaEDetalhes()}.",
                $doVeiculo,
            );
        }

        if ($motoristaId) {
            $doMotorista = UsoVeiculo::with(['motorista', 'veiculo', 'centroCusto'])
                ->sobrepostos($inicio, $fim)
                ->where('motorista_id', $motoristaId)
                ->when($ignorarId, fn ($q) => $q->where('id', '!=', $ignorarId))
                ->orderBy('inicio_previsto')
                ->first();

            if ($doMotorista) {
                return $this->conflitoResposta(
                    "O motorista {$doMotorista->quemUsa()} já tem outro uso nesse horário: veículo {$this->nomeDoVeiculo($doMotorista)}, {$doMotorista->janelaEDetalhes()}.",
                    $doMotorista,
                );
            }
        }

        return null;
    }

    /** "UAT0D84 (Strada)": a placa e o nome do veículo, quando se sabe. */
    private function nomeDoVeiculo(UsoVeiculo $uso): string
    {
        $veiculo = $uso->veiculo;

        if (!$veiculo) {
            return 'escolhido';
        }

        $nome = trim($veiculo->apelido ?: "{$veiculo->marca} {$veiculo->modelo}");

        return $nome !== '' ? "{$veiculo->placa} ({$nome})" : $veiculo->placa;
    }

    private function conflitoResposta(string $mensagem, UsoVeiculo $outro): JsonResponse
    {
        return response()->json([
            'message' => $mensagem,
            'conflito' => [
                'uso_id' => $outro->id,
                'inicio_previsto' => $outro->inicio_previsto->toIso8601String(),
                'fim_previsto' => $outro->fim_previsto->toIso8601String(),
                'status' => $outro->status,
                'veiculo' => $outro->veiculo?->placa,
                'motorista' => $outro->motorista?->nome,
                'finalidade' => $outro->finalidade,
                'centro_custo' => $outro->centroCusto?->nome,
            ],
        ], 422);
    }

    private function kmMenorQueOUltimo(VeiculoConta $veiculo, int $km): ?JsonResponse
    {
        if ($veiculo->km_atual !== null && $km < $veiculo->km_atual) {
            return response()->json([
                'message' => "O hodômetro informado ({$km} km) é menor que o último registrado ({$veiculo->km_atual} km). Confira o número ou peça ao gestor para corrigir o km do veículo.",
            ], 422);
        }

        return null;
    }

    /** O km atual só sobe: registrar uma viagem antiga não o derruba. */
    private function subirKm(VeiculoConta $veiculo, int $km): void
    {
        if ($veiculo->km_atual === null || $km > $veiculo->km_atual) {
            $veiculo->forceFill(['km_atual' => $km, 'km_atualizado_em' => now()])->save();
        }
    }

    /** Guarda a foto como uma nova versão: a anterior continua no disco (some só pela retenção de 90 dias). */
    private function guardarFoto(UsoVeiculo $uso, string $momento, UploadedFile $arquivo, ?int $userId): void
    {
        $extensao = self::MIMES[$arquivo->getMimeType()] ?? 'jpg';
        $caminho = "hodometros/{$uso->conta_id}/{$uso->veiculo_conta_id}/{$uso->id}-{$momento}-" . Str::uuid() . ".{$extensao}";
        $campo = $momento === 'saida' ? 'foto_saida' : 'foto_chegada';

        Storage::disk('local')->put($caminho, $arquivo->get());

        UsoVeiculoFoto::where('uso_veiculo_id', $uso->id)
            ->where('momento', $momento)
            ->whereNull('substituida_em')
            ->update(['substituida_em' => now()]);

        UsoVeiculoFoto::create([
            'uso_veiculo_id' => $uso->id,
            'momento' => $momento,
            'caminho' => $caminho,
            'user_id' => $userId,
        ]);

        $uso->forceFill([$campo => $caminho])->save();
    }

    /** @return array<string, mixed> */
    private function formatar(UsoVeiculo $uso): array
    {
        $veiculo = $uso->veiculo;

        return [
            'id' => $uso->id,
            'veiculo_conta_id' => $uso->veiculo_conta_id,
            'veiculo' => $veiculo ? [
                'id' => $veiculo->id,
                'nome' => trim($veiculo->apelido ?: "{$veiculo->marca} {$veiculo->modelo}"),
                'placa' => $veiculo->placa,
                'km_atual' => $veiculo->km_atual,
            ] : null,
            'motorista_id' => $uso->motorista_id,
            'motorista' => $uso->motorista?->nome,
            'centro_custo_id' => $uso->centro_custo_id,
            'centro_custo' => $uso->centroCusto?->nome,
            'inicio_previsto' => $uso->inicio_previsto->toIso8601String(),
            'fim_previsto' => $uso->fim_previsto->toIso8601String(),
            'finalidade' => $uso->finalidade,
            'status' => $uso->status,
            'km_saida' => $uso->km_saida,
            'km_chegada' => $uso->km_chegada,
            'km_saida_origem' => $uso->km_saida_origem,
            'km_chegada_origem' => $uso->km_chegada_origem,
            'km_rodados' => $uso->kmRodados(),
            'saida_em' => $uso->saida_em?->toIso8601String(),
            'chegada_em' => $uso->chegada_em?->toIso8601String(),
            'saida_por' => $uso->saidaPor?->name,
            'chegada_por' => $uso->chegadaPor?->name,
            'url_foto_saida' => $uso->urlDaFoto('saida'),
            'url_foto_chegada' => $uso->urlDaFoto('chegada'),
            'origem' => $uso->origem,
            'observacoes' => $uso->observacoes,
        ];
    }

    private function naoEncontrado()
    {
        return response()->json(['message' => 'Uso de veículo não encontrado.'], 404);
    }
}
