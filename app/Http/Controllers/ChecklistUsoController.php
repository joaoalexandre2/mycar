<?php

namespace App\Http\Controllers;

use App\Models\ChecklistModelo;
use App\Models\ChecklistUso;
use App\Models\UsoVeiculo;
use App\Models\UsoVeiculoEvento;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Checklist de saída e de devolução de uma reserva. O motorista só faz o da própria reserva;
 * administrador e gestor fazem o de qualquer uma.
 *
 * Os itens vêm do modelo de checklist da empresa (ver ChecklistModelo). Quem ainda manda o formato antigo
 * ({item, ok}) continua funcionando: sem "situacao", vale o comportamento de antes.
 */
class ChecklistUsoController extends Controller
{
    public function index(Request $request, $id)
    {
        $uso = $this->usoDaPessoa($request, $id);

        if (!$uso) {
            return response()->json(['message' => 'Reserva não encontrada.'], 404);
        }

        ChecklistModelo::garantirPadrao();

        return response()->json([
            // Itens fixos de antes, mantidos para quem ainda lê este campo.
            'itens' => ChecklistUso::ITENS,
            'modelos' => ChecklistModelo::with('itens')->where('ativo', true)->orderBy('nome')->get()
                ->map(fn (ChecklistModelo $m) => $this->formatarModelo($m))
                ->values(),
            'checklists' => ChecklistUso::where('uso_veiculo_id', $uso->id)->with('modelo:id,nome')->get()
                ->map(fn (ChecklistUso $c) => $this->formatar($c))
                ->values(),
        ]);
    }

    public function store(Request $request, $id)
    {
        $uso = $this->usoDaPessoa($request, $id);

        if (!$uso) {
            return response()->json(['message' => 'Reserva não encontrada.'], 404);
        }

        if ($uso->status === 'cancelado') {
            return response()->json(['message' => 'Uma reserva cancelada não tem checklist.'], 422);
        }

        $dados = $request->validate([
            'momento' => ['required', Rule::in(ChecklistUso::MOMENTOS)],
            'checklist_modelo_id' => ['nullable', 'integer', Rule::exists('checklist_modelos', 'id')->where('conta_id', app('conta.atual'))],
            'itens' => ['required', 'array'],
            'itens.*.item' => ['required', 'string', 'max:40'],
            'itens.*.ok' => ['sometimes', 'boolean'],
            'itens.*.situacao' => ['sometimes', Rule::in(['ok', 'problema', 'na'])],
            'itens.*.observacao' => ['nullable', 'string', 'max:255'],
            'observacoes' => ['nullable', 'string', 'max:1000'],
        ]);

        ChecklistModelo::garantirPadrao();
        $modelo = $this->modeloDoChecklist($dados['checklist_modelo_id'] ?? null, $dados['momento']);

        if (!$modelo) {
            return response()->json(['message' => 'Não há um modelo de checklist ativo para este momento.'], 422);
        }

        $this->validarRespostas($modelo, $dados['itens']);

        if (ChecklistUso::where('uso_veiculo_id', $uso->id)->where('momento', $dados['momento'])->exists()) {
            return response()->json(['message' => 'Este checklist já foi feito.'], 422);
        }

        $itens = collect($dados['itens'])->map(fn (array $r) => $this->itemGravado($r))->values()->all();

        // A unicidade (reserva + momento) é garantida pelo banco. Se duas requisições chegarem juntas,
        // a segunda perde aqui e recebe a mesma resposta de quem já foi registrado.
        try {
            $checklist = ChecklistUso::create([
                'uso_veiculo_id' => $uso->id,
                'momento' => $dados['momento'],
                'checklist_modelo_id' => $modelo->id,
                'itens' => $itens,
                'observacoes' => $dados['observacoes'] ?? null,
                'feito_por_user_id' => $request->user()->id,
            ]);
        } catch (QueryException $e) {
            // Só a duplicidade deste checklist vira 422. Outras violações de integridade (chave estrangeira,
            // por exemplo) continuam sendo erro, para não serem mascaradas como "já foi feito".
            if (!$this->ehDuplicidadeDoChecklist($e)) {
                throw $e;
            }

            return response()->json(['message' => 'Este checklist já foi feito.'], 422);
        }

        UsoVeiculoEvento::registrar($uso, 'checklist_' . $dados['momento'], $request->user()->id, [
            'problemas' => $checklist->problemas,
        ]);

        return response()->json($this->formatar($checklist->load('modelo:id,nome')), 201);
    }

    /** O modelo pedido (se servir ao momento) ou o ativo do momento, dando preferência ao que é só dele. */
    private function modeloDoChecklist(?int $modeloId, string $momento): ?ChecklistModelo
    {
        if ($modeloId !== null) {
            $modelo = ChecklistModelo::with('itens')->where('ativo', true)->find($modeloId);

            return $modelo && $modelo->serveAo($momento) ? $modelo : null;
        }

        return ChecklistModelo::with('itens')
            ->where('ativo', true)
            ->whereIn('momento', [$momento, ChecklistModelo::AMBOS])
            ->orderByRaw('momento = ? desc', [$momento])
            ->orderBy('id')
            ->first();
    }

    /**
     * Todo item obrigatório respondido, e só itens do modelo. Quem responde com "situacao" precisa
     * descrever o problema (o formato antigo, sem "situacao", continua aceito como antes).
     */
    private function validarRespostas(ChecklistModelo $modelo, array $respostas): void
    {
        $chavesDoModelo = $modelo->itens->pluck('chave')->all();
        $respondidas = collect($respostas)->pluck('item')->all();

        $desconhecido = collect($respondidas)->first(fn (string $chave) => !in_array($chave, $chavesDoModelo, true));

        if ($desconhecido !== null) {
            throw ValidationException::withMessages(['itens' => ["O item \"{$desconhecido}\" não faz parte deste checklist."]]);
        }

        $faltando = $modelo->itens->where('obrigatorio', true)->reject(fn ($i) => in_array($i->chave, $respondidas, true));

        if ($faltando->isNotEmpty()) {
            throw ValidationException::withMessages(['itens' => ['Responda todos os itens obrigatórios: ' . $faltando->pluck('rotulo')->implode(', ') . '.']]);
        }

        foreach ($respostas as $resposta) {
            if (($resposta['situacao'] ?? null) === 'problema' && trim((string) ($resposta['observacao'] ?? '')) === '') {
                $rotulo = $modelo->itens->firstWhere('chave', $resposta['item'])?->rotulo ?? $resposta['item'];

                throw ValidationException::withMessages(['itens' => ["Descreva o problema encontrado em \"{$rotulo}\"."]]);
            }
        }
    }

    /** O que é gravado: "ok" continua true para o alerta (só o problema conta), e a situação fica para a tela. */
    private function itemGravado(array $resposta): array
    {
        $situacao = $resposta['situacao'] ?? (($resposta['ok'] ?? true) ? 'ok' : 'problema');

        return [
            'item' => $resposta['item'],
            'ok' => $situacao !== 'problema',
            'situacao' => $situacao,
            'observacao' => $resposta['observacao'] ?? null,
        ];
    }

    /**
     * A violação é a da unicidade (reserva + momento) desta tabela? O SQLSTATE 23000 sozinho é genérico:
     * SQLite cita o campo ("checklists_uso.uso_veiculo_id") e MySQL cita o índice ("checklists_uso_..._unique").
     */
    private function ehDuplicidadeDoChecklist(QueryException $e): bool
    {
        if ((string) ($e->errorInfo[0] ?? $e->getCode()) !== '23000') {
            return false;
        }

        $mensagem = $e->getMessage();

        return str_contains($mensagem, 'checklists_uso')
            && (str_contains($mensagem, 'UNIQUE') || str_contains($mensagem, 'Duplicate entry'));
    }

    /** A reserva, se a pessoa pode vê-la: o motorista só as próprias. */
    private function usoDaPessoa(Request $request, $id): ?UsoVeiculo
    {
        $query = UsoVeiculo::query()->whereKey($id);

        if ($request->user()->papelNaConta() === 'motorista') {
            $query->where('motorista_id', $request->user()->motoristaVinculado()?->id ?? 0);
        }

        return $query->first();
    }

    /** @return array<string, mixed> */
    private function formatarModelo(ChecklistModelo $m): array
    {
        return [
            'id' => $m->id,
            'nome' => $m->nome,
            'momento' => $m->momento,
            'itens' => $m->itens->map(fn ($i) => [
                'chave' => $i->chave,
                'rotulo' => $i->rotulo,
                'obrigatorio' => $i->obrigatorio,
            ])->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function formatar(ChecklistUso $c): array
    {
        return [
            'id' => $c->id,
            'momento' => $c->momento,
            'modelo' => $c->modelo ? ['id' => $c->modelo->id, 'nome' => $c->modelo->nome] : null,
            'itens' => $c->itens,
            'problemas' => $c->problemas,
            'observacoes' => $c->observacoes,
            'quando' => $c->created_at?->toIso8601String(),
        ];
    }
}
