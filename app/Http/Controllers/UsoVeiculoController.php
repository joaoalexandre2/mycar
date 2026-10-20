<?php

namespace App\Http\Controllers;

use App\Models\UsoVeiculo;
use App\Models\VeiculoConta;
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
 */
class UsoVeiculoController extends Controller
{
    private const MAX_DIAS_DE_RESERVA = 31;

    private const KM_MAXIMO = 9_999_999;

    private const MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

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

        $usos = UsoVeiculo::with(['veiculo', 'motorista', 'centroCusto'])
            ->where('inicio_previsto', '<=', $ate)
            ->where('fim_previsto', '>=', $de)
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

    public function show($id)
    {
        $uso = UsoVeiculo::with(['veiculo', 'motorista', 'centroCusto'])->find($id);

        return $uso ? response()->json($this->formatar($uso)) : $this->naoEncontrado();
    }

    public function store(Request $request)
    {
        $dados = $this->validar($request);
        $veiculo = VeiculoConta::find($dados['veiculo_conta_id']);

        if ($erro = $this->conflito($dados['veiculo_conta_id'], $dados['motorista_id'] ?? null, $dados['inicio_previsto'], $dados['fim_previsto'])) {
            return $erro;
        }

        $uso = UsoVeiculo::create($dados + [
            // Sem centro informado, vale o padrão do veículo.
            'centro_custo_id' => $dados['centro_custo_id'] ?? $veiculo->centro_custo_id,
            'status' => 'agendado',
            'origem' => 'agenda',
        ]);

        return response()->json($this->formatar($uso->load(['veiculo', 'motorista', 'centroCusto'])), 201);
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

        if ($erro = $this->conflito($dados['veiculo_conta_id'], $motorista, $dados['inicio_previsto'], $dados['fim_previsto'], $uso->id)) {
            return $erro;
        }

        $uso->update($dados);

        return response()->json($this->formatar($uso->fresh(['veiculo', 'motorista', 'centroCusto'])));
    }

    public function cancelar($id)
    {
        $uso = UsoVeiculo::find($id);

        if (!$uso) {
            return $this->naoEncontrado();
        }

        if ($uso->status !== 'agendado') {
            return response()->json(['message' => 'Só dá para cancelar um uso que ainda não saiu.'], 422);
        }

        $uso->update(['status' => 'cancelado']);

        return response()->json($this->formatar($uso->fresh(['veiculo', 'motorista', 'centroCusto'])));
    }

    public function destroy($id)
    {
        $uso = UsoVeiculo::find($id);

        if (!$uso) {
            return $this->naoEncontrado();
        }

        $uso->delete(); // apaga as fotos também; o km atual do veículo não volta atrás

        return response()->json(['message' => 'Uso removido.']);
    }

    // ----------------------------------------------------------- saída e chegada

    public function saida(Request $request, $id)
    {
        $uso = UsoVeiculo::with('veiculo')->find($id);

        if (!$uso) {
            return $this->naoEncontrado();
        }

        if ($uso->status !== 'agendado') {
            return response()->json(['message' => 'A saída só pode ser registrada em um uso agendado.'], 422);
        }

        $dados = $this->validarKm($request, 'km_saida');

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

        if ($erro = $this->kmMenorQueOUltimo($uso->veiculo, $dados['km_saida'])) {
            return $erro;
        }

        DB::transaction(function () use ($uso, $dados, $request) {
            $uso->update(['status' => 'em_uso', 'km_saida' => $dados['km_saida'], 'saida_em' => now()]);

            if ($request->hasFile('foto')) {
                $this->guardarFoto($uso, 'saida', $request->file('foto'));
            }

            $this->subirKm($uso->veiculo, $dados['km_saida']);
        });

        return response()->json($this->formatar($uso->fresh(['veiculo', 'motorista', 'centroCusto'])));
    }

    public function chegada(Request $request, $id)
    {
        $uso = UsoVeiculo::with('veiculo')->find($id);

        if (!$uso) {
            return $this->naoEncontrado();
        }

        if ($uso->status !== 'em_uso') {
            return response()->json(['message' => 'A chegada só pode ser registrada em um uso em andamento.'], 422);
        }

        $dados = $this->validarKm($request, 'km_chegada');

        if ($dados['km_chegada'] < $uso->km_saida) {
            return response()->json([
                'message' => "O km de chegada ({$dados['km_chegada']}) é menor que o de saída ({$uso->km_saida}). Confira o hodômetro.",
            ], 422);
        }

        DB::transaction(function () use ($uso, $dados, $request) {
            $uso->update(['status' => 'concluido', 'km_chegada' => $dados['km_chegada'], 'chegada_em' => now()]);

            if ($request->hasFile('foto')) {
                $this->guardarFoto($uso, 'chegada', $request->file('foto'));
            }

            $this->subirKm($uso->veiculo, $dados['km_chegada']);
        });

        return response()->json($this->formatar($uso->fresh(['veiculo', 'motorista', 'centroCusto'])));
    }

    /** Envia ou troca a foto do hodômetro de um momento que já foi registrado. */
    public function foto(Request $request, $id, $momento)
    {
        $uso = UsoVeiculo::find($id);

        if (!$uso) {
            return $this->naoEncontrado();
        }

        abort_unless(in_array($momento, UsoVeiculo::MOMENTOS, true), 404);

        $km = $momento === 'saida' ? $uso->km_saida : $uso->km_chegada;

        if ($km === null) {
            return response()->json(['message' => 'Registre o km antes de anexar a foto do hodômetro.'], 422);
        }

        $request->validate(['foto' => $this->regraDaFoto(true)], $this->mensagensDaFoto());

        $this->guardarFoto($uso, $momento, $request->file('foto'));

        return response()->json($this->formatar($uso->fresh(['veiculo', 'motorista', 'centroCusto'])));
    }

    /** Corrige a quilometragem atual do veículo (único jeito de ela diminuir). */
    public function definirKm(Request $request, $id)
    {
        $veiculo = VeiculoConta::find($id);

        if (!$veiculo) {
            return response()->json(['message' => 'Veículo não encontrado.'], 404);
        }

        $dados = $request->validate(['km' => ['required', 'integer', 'min:0', 'max:' . self::KM_MAXIMO]]);

        $veiculo->forceFill(['km_atual' => $dados['km'], 'km_atualizado_em' => now()])->save();

        return response()->json([
            'id' => $veiculo->id,
            'km_atual' => $veiculo->km_atual,
            'km_atualizado_em' => $veiculo->km_atualizado_em?->toIso8601String(),
        ]);
    }

    // ---------------------------------------------------------------- internos

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
        $doVeiculo = UsoVeiculo::with('motorista')
            ->sobrepostos($inicio, $fim)
            ->where('veiculo_conta_id', $veiculoId)
            ->when($ignorarId, fn ($q) => $q->where('id', '!=', $ignorarId))
            ->orderBy('inicio_previsto')
            ->first();

        if ($doVeiculo) {
            return $this->conflitoResposta('O veículo já está reservado nesse horário', $doVeiculo);
        }

        if ($motoristaId) {
            $doMotorista = UsoVeiculo::with('veiculo')
                ->sobrepostos($inicio, $fim)
                ->where('motorista_id', $motoristaId)
                ->when($ignorarId, fn ($q) => $q->where('id', '!=', $ignorarId))
                ->orderBy('inicio_previsto')
                ->first();

            if ($doMotorista) {
                return $this->conflitoResposta('O motorista já tem outro uso nesse horário', $doMotorista);
            }
        }

        return null;
    }

    private function conflitoResposta(string $motivo, UsoVeiculo $outro): JsonResponse
    {
        $janela = $outro->inicio_previsto->format('d/m H:i') . ' a ' . $outro->fim_previsto->format('d/m H:i');

        return response()->json([
            'message' => "{$motivo} ({$janela}).",
            'conflito' => ['uso_id' => $outro->id, 'inicio_previsto' => $outro->inicio_previsto->toIso8601String(), 'fim_previsto' => $outro->fim_previsto->toIso8601String()],
        ], 422);
    }

    private function kmMenorQueOUltimo(VeiculoConta $veiculo, int $km): ?JsonResponse
    {
        if ($veiculo->km_atual !== null && $km < $veiculo->km_atual) {
            return response()->json([
                'message' => "O hodômetro informado ({$km} km) é menor que o último registrado ({$veiculo->km_atual} km). Confira o número ou corrija o km do veículo.",
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

    private function guardarFoto(UsoVeiculo $uso, string $momento, UploadedFile $arquivo): void
    {
        $extensao = self::MIMES[$arquivo->getMimeType()] ?? 'jpg';
        $caminho = "hodometros/{$uso->conta_id}/{$uso->veiculo_conta_id}/{$uso->id}-{$momento}-" . Str::uuid() . ".{$extensao}";
        $campo = $momento === 'saida' ? 'foto_saida' : 'foto_chegada';

        Storage::disk('local')->put($caminho, $arquivo->get());

        // Troca de foto: o arquivo anterior sai do disco.
        if ($anterior = $uso->{$campo}) {
            Storage::disk('local')->delete($anterior);
        }

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
            'km_rodados' => $uso->kmRodados(),
            'saida_em' => $uso->saida_em?->toIso8601String(),
            'chegada_em' => $uso->chegada_em?->toIso8601String(),
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
