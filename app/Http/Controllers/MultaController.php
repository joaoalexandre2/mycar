<?php

namespace App\Http\Controllers;

use App\Models\Multa;
use App\Models\UsoVeiculo;
use App\Models\VeiculoConta;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Multas da frota. Cada multa aponta o uso (reserva) e o motorista que estavam com o veículo
 * na data e hora da infração. Só administrador e gestor mexem aqui.
 */
class MultaController extends Controller
{
    public function index(Request $request)
    {
        $multas = Multa::query()
            ->with(['veiculo:id,placa,marca,modelo,apelido', 'motorista:id,nome'])
            ->when($request->filled('veiculo_conta_id'), fn ($q) => $q->where('veiculo_conta_id', $request->integer('veiculo_conta_id')))
            ->when($request->filled('situacao'), fn ($q) => $q->where('situacao', $request->string('situacao')))
            ->orderByDesc('data_hora')
            ->limit(300)
            ->get();

        return response()->json($multas->map(fn (Multa $m) => $this->formatar($m))->values());
    }

    /** Quem estava com o veículo nessa data e hora: é o que a tela sugere como responsável. */
    public function sugerir(Request $request)
    {
        $dados = $request->validate([
            'veiculo_conta_id' => ['required', 'integer'],
            'data_hora' => ['required', 'date'],
        ]);

        $uso = Multa::usoNaData((int) $dados['veiculo_conta_id'], Carbon::parse($dados['data_hora']));

        if (!$uso) {
            return response()->json(['uso' => null, 'motorista' => null]);
        }

        return response()->json([
            'uso' => ['id' => $uso->id, 'inicio' => $uso->inicio_previsto?->toIso8601String(), 'fim' => $uso->fim_previsto?->toIso8601String(), 'finalidade' => $uso->finalidade],
            'motorista' => $uso->motorista ? ['id' => $uso->motorista->id, 'nome' => $uso->motorista->nome] : null,
        ]);
    }

    public function store(Request $request)
    {
        $dados = $this->validar($request);

        if ($recusa = $this->recusaSeInconsistente($dados, null)) {
            return $recusa;
        }

        $veiculo = VeiculoConta::find($dados['veiculo_conta_id']);

        if (!$veiculo) {
            return response()->json(['message' => 'Veículo não encontrado.'], 422);
        }

        // Sem responsável informado, a multa fica com quem estava com o veículo naquele momento.
        $uso = Multa::usoNaData($veiculo->id, Carbon::parse($dados['data_hora']));

        $multa = Multa::create([
            ...$dados,
            'uso_veiculo_id' => $dados['uso_veiculo_id'] ?? $uso?->id,
            'motorista_id' => $dados['motorista_id'] ?? $uso?->motorista_id,
            'situacao' => Multa::ABERTA,
        ]);

        return response()->json($this->formatar($multa->load(['veiculo', 'motorista'])), 201);
    }

    public function update(Request $request, $id)
    {
        $multa = Multa::find($id);

        if (!$multa) {
            return response()->json(['message' => 'Multa não encontrada.'], 404);
        }

        $dados = $this->validar($request, parcial: true);

        if ($recusa = $this->recusaSeInconsistente($dados, $multa)) {
            return $recusa;
        }

        $multa->update($dados);

        return response()->json($this->formatar($multa->fresh()->load(['veiculo', 'motorista'])));
    }

    public function destroy($id)
    {
        $multa = Multa::find($id);

        if (!$multa) {
            return response()->json(['message' => 'Multa não encontrada.'], 404);
        }

        $multa->delete();

        return response()->json(['message' => 'Multa removida.']);
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, bool $parcial = false): array
    {
        $obrigatorio = $parcial ? 'sometimes' : 'required';

        return $request->validate([
            // Cada referência precisa ser de ESTA conta: IDs de outra empresa são recusados com 422.
            'veiculo_conta_id' => [$obrigatorio, 'integer', Rule::exists('veiculos_conta', 'id')->where('conta_id', app('conta.atual'))],
            'data_hora' => [$obrigatorio, 'date'],
            'descricao' => [$obrigatorio, 'string', 'max:160'],
            'valor' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'pontos' => ['nullable', 'integer', 'min:0', 'max:40'],
            'situacao' => ['sometimes', Rule::in(Multa::SITUACOES)],
            'motorista_id' => ['nullable', 'integer', Rule::exists('motoristas', 'id')->where('conta_id', app('conta.atual'))],
            'uso_veiculo_id' => ['nullable', 'integer', Rule::exists('usos_veiculo', 'id')->where('conta_id', app('conta.atual'))],
            'observacoes' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    /**
     * A reserva precisa ser deste veículo, e o motorista precisa ser o da reserva. Se a multa já existe,
     * completa com o que ela já tem para conferir o conjunto final.
     *
     * @param  array<string, mixed>  $dados
     */
    private function recusaSeInconsistente(array $dados, ?Multa $atual): ?JsonResponse
    {
        $veiculoId = $dados['veiculo_conta_id'] ?? $atual?->veiculo_conta_id;
        $usoId = array_key_exists('uso_veiculo_id', $dados) ? $dados['uso_veiculo_id'] : $atual?->uso_veiculo_id;
        $motoristaId = array_key_exists('motorista_id', $dados) ? $dados['motorista_id'] : $atual?->motorista_id;

        if (!$usoId) {
            return null;
        }

        // O escopo da conta já barra reservas alheias: aqui só se confere que a reserva bate com o veículo e o motorista.
        $uso = UsoVeiculo::find($usoId);

        if (!$uso) {
            return response()->json(['message' => 'Reserva não encontrada nesta frota.'], 422);
        }

        if ($veiculoId && (int) $uso->veiculo_conta_id !== (int) $veiculoId) {
            return response()->json(['message' => 'A reserva informada não é deste veículo.'], 422);
        }

        if ($motoristaId && $uso->motorista_id && (int) $uso->motorista_id !== (int) $motoristaId) {
            return response()->json(['message' => 'O motorista informado não é o da reserva.'], 422);
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function formatar(Multa $m): array
    {
        return [
            'id' => $m->id,
            'data_hora' => $m->data_hora?->toIso8601String(),
            'descricao' => $m->descricao,
            'valor' => $m->valor,
            'pontos' => $m->pontos,
            'situacao' => $m->situacao,
            'observacoes' => $m->observacoes,
            'veiculo' => $m->veiculo ? ['id' => $m->veiculo->id, 'placa' => $m->veiculo->placa, 'nome' => trim(($m->veiculo->apelido ? $m->veiculo->apelido . ' · ' : '') . $m->veiculo->marca . ' ' . $m->veiculo->modelo)] : null,
            'uso_veiculo_id' => $m->uso_veiculo_id,
            'motorista' => $m->motorista ? ['id' => $m->motorista->id, 'nome' => $m->motorista->nome] : null,
        ];
    }
}
