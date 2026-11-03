<?php

namespace App\Http\Controllers;

use App\Models\Despesa;
use App\Models\Ocorrencia;
use App\Models\OcorrenciaFoto;
use App\Support\FusoDaFrota;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Ocorrências da frota: acidente, avaria, dano ou problema de um veículo.
 *
 * Quem faz a frota registra e acompanha as próprias ocorrências. O motorista registra e vê só as dele, e não vê valores.
 * Administrador e gestor veem todas, mudam o status, informam valores e apagam.
 */
class OcorrenciaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'tipo' => ['nullable', Rule::in(array_keys(Ocorrencia::TIPOS))],
            'status' => ['nullable', Rule::in(array_keys(Ocorrencia::STATUS))],
            'veiculo_id' => ['nullable', 'integer'],
            'motorista_id' => ['nullable', 'integer'],
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date', 'after_or_equal:de'],
        ]);

        $consulta = $this->visiveis($request)
            ->when(!empty($filtros['tipo']), fn ($q) => $q->where('tipo', $filtros['tipo']))
            ->when(!empty($filtros['status']), fn ($q) => $q->where('status', $filtros['status']))
            ->when(!empty($filtros['veiculo_id']), fn ($q) => $q->where('veiculo_conta_id', $filtros['veiculo_id']))
            ->when(!empty($filtros['motorista_id']) && !$this->ehMotorista($request), fn ($q) => $q->where('motorista_id', $filtros['motorista_id']))
            ->when(!empty($filtros['de']), fn ($q) => $q->where('data_hora', '>=', FusoDaFrota::dia($filtros['de'])->format('Y-m-d H:i:s')))
            ->when(!empty($filtros['ate']), fn ($q) => $q->where('data_hora', '<=', FusoDaFrota::dia($filtros['ate'], true)->format('Y-m-d H:i:s')));

        $paginador = (clone $consulta)
            ->with(['veiculo', 'motorista', 'fotos'])
            ->orderByDesc('data_hora')
            ->orderByDesc('id')
            ->paginate(25);

        return response()->json([
            'data' => collect($paginador->items())->map(fn (Ocorrencia $o) => $this->formatar($o, $request))->values(),
            'meta' => [
                'current_page' => $paginador->currentPage(),
                'last_page' => $paginador->lastPage(),
                'per_page' => $paginador->perPage(),
                'total' => $paginador->total(),
            ],
            'resumo' => $this->resumo(clone $consulta, $request),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $ehMotorista = $this->ehMotorista($request);
        $motorista = $request->user()->motoristaVinculado();

        if ($ehMotorista && !$motorista) {
            return response()->json(['message' => 'Seu usuário não está ligado a um motorista da frota.'], 403);
        }

        if ($ehMotorista) {
            // O motorista registra sempre em nome próprio: o que ele mandar para motorista_id é ignorado.
            $request->request->remove('motorista_id');
        }

        $dados = $this->validar($request);
        $dados['data_hora'] = FusoDaFrota::entrada($dados['data_hora']);
        $dados['status'] = 'aberta';
        $dados['registrado_por_user_id'] = $request->user()->id;

        if ($ehMotorista) {
            // O motorista registra em nome próprio e não informa valores.
            $dados['motorista_id'] = $motorista->id;
            $dados['prejuizo_estimado'] = null;
            $dados['valor_gasto'] = null;
        }

        $ocorrencia = DB::transaction(function () use ($dados) {
            $ocorrencia = Ocorrencia::create($dados);
            $ocorrencia->sincronizarDespesa();

            return $ocorrencia;
        });

        return response()->json($this->formatar($ocorrencia->load(['veiculo', 'motorista', 'fotos']), $request), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $ocorrencia = Ocorrencia::find($id);

        if (!$ocorrencia) {
            return response()->json(['message' => 'Ocorrência não encontrada.'], 404);
        }

        $dados = $this->validar($request);
        $dados['data_hora'] = FusoDaFrota::entrada($dados['data_hora']);

        DB::transaction(function () use ($ocorrencia, $dados) {
            $ocorrencia->update($dados);
            $ocorrencia->sincronizarDespesa();
        });

        return response()->json($this->formatar($ocorrencia->fresh(['veiculo', 'motorista', 'fotos']), $request));
    }

    public function destroy(int $id): JsonResponse
    {
        $ocorrencia = Ocorrencia::with('fotos')->find($id);

        if (!$ocorrencia) {
            return response()->json(['message' => 'Ocorrência não encontrada.'], 404);
        }

        DB::transaction(function () use ($ocorrencia) {
            $ocorrencia->forceFill(['despesa_id' => null])->saveQuietly();
            $ocorrencia->load('fotos');

            foreach ($ocorrencia->fotos as $foto) {
                Storage::disk('local')->delete($foto->caminho);
            }

            $despesaId = $ocorrencia->getOriginal('despesa_id');
            $ocorrencia->delete();

            if ($despesaId) {
                Despesa::whereKey($despesaId)->delete();
            }
        });

        return response()->json(null, 204);
    }

    /** Ocorrências que o usuário pode ver: o motorista só as dele. */
    private function visiveis(Request $request): Builder
    {
        $consulta = Ocorrencia::query();

        if ($this->ehMotorista($request)) {
            $consulta->where('motorista_id', $request->user()->motoristaVinculado()?->id ?? 0);
        }

        return $consulta;
    }

    private function ehMotorista(Request $request): bool
    {
        return $request->user()->papelNaConta() === 'motorista';
    }

    private function validar(Request $request): array
    {
        $conta = app('conta.atual');

        return $request->validate([
            'veiculo_conta_id' => ['required', 'integer', Rule::exists('veiculos_conta', 'id')->where('conta_id', $conta)],
            'tipo' => ['required', Rule::in(array_keys(Ocorrencia::TIPOS))],
            'data_hora' => ['required', 'date'],
            'local' => ['nullable', 'string', 'max:160'],
            'descricao' => ['required', 'string', 'min:3', 'max:2000'],
            'uso_veiculo_id' => ['nullable', 'integer', Rule::exists('usos_veiculo', 'id')->where('conta_id', $conta)],
            'motorista_id' => ['nullable', 'integer', Rule::exists('motoristas', 'id')->where('conta_id', $conta)],
            'prejuizo_estimado' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'valor_gasto' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'status' => ['sometimes', 'required', Rule::in(array_keys(Ocorrencia::STATUS))],
        ]);
    }

    /** Totais do que está filtrado: para o relatório de avarias. Valores só para quem gerencia. */
    private function resumo(Builder $consulta, Request $request): array
    {
        $porStatus = (clone $consulta)->selectRaw('status, count(*) as quantidade')->groupBy('status')->pluck('quantidade', 'status');

        $resumo = [
            'total' => (clone $consulta)->count(),
            'abertas' => (clone $consulta)->whereIn('status', Ocorrencia::ABERTAS)->count(),
            'por_status' => collect(Ocorrencia::STATUS)->map(fn ($rotulo, $chave) => (int) ($porStatus[$chave] ?? 0))->all(),
        ];

        if (!$this->ehMotorista($request)) {
            $valores = (clone $consulta)->selectRaw('coalesce(sum(prejuizo_estimado), 0) as prejuizo, coalesce(sum(valor_gasto), 0) as gasto')->first();
            $resumo['prejuizo_total'] = round((float) $valores->prejuizo, 2);
            $resumo['gasto_total'] = round((float) $valores->gasto, 2);
        }

        return $resumo;
    }

    private function formatar(Ocorrencia $o, Request $request): array
    {
        $dados = [
            'id' => $o->id,
            'tipo' => $o->tipo,
            'tipo_rotulo' => Ocorrencia::TIPOS[$o->tipo] ?? $o->tipo,
            'status' => $o->status,
            'status_rotulo' => Ocorrencia::STATUS[$o->status] ?? $o->status,
            'data_hora' => FusoDaFrota::saida($o->data_hora),
            'local' => $o->local,
            'descricao' => $o->descricao,
            'uso_veiculo_id' => $o->uso_veiculo_id,
            'veiculo' => $o->veiculo ? ['id' => $o->veiculo->id, 'placa' => $o->veiculo->placa] : null,
            'motorista' => $o->motorista ? ['id' => $o->motorista->id, 'nome' => $o->motorista->nome] : null,
            'fotos' => $o->fotos->map(fn (OcorrenciaFoto $f) => ['id' => $f->id, 'momento' => $f->momento, 'url' => $f->urlDaFoto()])->values(),
        ];

        if (!$this->ehMotorista($request)) {
            $dados['prejuizo_estimado'] = $o->prejuizo_estimado !== null ? (float) $o->prejuizo_estimado : null;
            $dados['valor_gasto'] = $o->valor_gasto !== null ? (float) $o->valor_gasto : null;
            $dados['despesa_id'] = $o->despesa_id;
        }

        return $dados;
    }
}
