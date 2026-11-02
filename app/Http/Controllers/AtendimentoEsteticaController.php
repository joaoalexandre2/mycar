<?php

namespace App\Http\Controllers;

use App\Models\AtendimentoEstetica;
use App\Models\AtendimentoServicoEstetica;
use App\Models\FotoEstetica;
use App\Models\PacoteEstetica;
use App\Models\ServicoEstetica;
use App\Models\VeiculoConta;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Pátio da estética: cada carro do check-in à entrega, em etapas. */
class AtendimentoEsteticaController extends Controller
{
    public function index(Request $request)
    {
        $atendimentos = AtendimentoEstetica::query()
            ->with(['servicos', 'fotos'])
            ->when($request->filled('etapa'), fn ($q) => $q->where('etapa', $request->string('etapa')))
            ->orderBy('entrada_em')
            ->limit(300)
            ->get();

        return response()->json($atendimentos->map(fn (AtendimentoEstetica $a) => $this->formatar($a))->values());
    }

    public function show($id)
    {
        $atendimento = AtendimentoEstetica::with(['servicos', 'fotos'])->find($id);

        if (!$atendimento) {
            return $this->naoEncontrado();
        }

        return response()->json($this->formatar($atendimento, comFotos: true));
    }

    public function store(Request $request)
    {
        $dados = $request->validate([
            'placa' => ['required', 'string', 'max:10'],
            'modelo' => ['nullable', 'string', 'max:120'],
            'cliente_nome' => ['nullable', 'string', 'max:120'],
            'cliente_telefone' => ['nullable', 'string', 'max:30'],
            'servicos' => ['nullable', 'array'],
            'servicos.*' => ['integer'],
            'pacote_id' => ['nullable', 'integer'],
            'previsao_entrega' => ['nullable', 'date'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ]);

        $placa = AtendimentoEstetica::normalizarPlaca($dados['placa']);

        if (strlen($placa) < 6) {
            return response()->json(['message' => 'Informe uma placa válida.'], 422);
        }

        $pacote = isset($dados['pacote_id']) ? PacoteEstetica::with('servicos')->find($dados['pacote_id']) : null;

        if (isset($dados['pacote_id']) && !$pacote) {
            return response()->json(['message' => 'Pacote não encontrado.'], 422);
        }

        $servicos = $pacote
            ? $pacote->servicos
            : ServicoEstetica::query()->whereIn('id', $dados['servicos'] ?? [])->where('ativo', true)->get();

        if (empty($dados['servicos']) && !$pacote) {
            return response()->json(['message' => 'Escolha pelo menos um serviço ou um pacote.'], 422);
        }

        $veiculo = VeiculoConta::query()->where('placa', $placa)->first();
        $entrada = now();
        $diasDeServico = (int) $servicos->max('dias_previstos');

        $atendimento = AtendimentoEstetica::create([
            'veiculo_conta_id' => $veiculo?->id,
            'pacote_id' => $pacote?->id,
            'placa' => $placa,
            'modelo' => $dados['modelo'] ?? $veiculo?->modelo,
            'cliente_nome' => $dados['cliente_nome'] ?? null,
            'cliente_telefone' => $dados['cliente_telefone'] ?? null,
            'etapa' => AtendimentoEstetica::ENTRADA,
            'valor' => $pacote ? $pacote->preco : $servicos->sum('preco'),
            'pago' => false,
            'entrada_em' => $entrada,
            // Serviço longo: a previsão é a entrada mais os dias do serviço mais demorado.
            'previsao_entrega' => $dados['previsao_entrega'] ?? ($diasDeServico > 0 ? $entrada->copy()->addDays($diasDeServico) : null),
            'observacoes' => $dados['observacoes'] ?? null,
        ]);

        if ($pacote) {
            AtendimentoServicoEstetica::create(['atendimento_id' => $atendimento->id, 'servico_id' => null, 'nome' => 'Pacote: ' . $pacote->nome, 'preco' => $pacote->preco]);
        } else {
            foreach ($servicos as $servico) {
                AtendimentoServicoEstetica::create([
                    'atendimento_id' => $atendimento->id,
                    'servico_id' => $servico->id,
                    'nome' => $servico->nome,
                    'preco' => $servico->preco,
                ]);
            }
        }

        return response()->json($this->formatar($atendimento->fresh(['servicos', 'fotos'])), 201);
    }

    public function atualizar(Request $request, $id)
    {
        $atendimento = AtendimentoEstetica::find($id);

        if (!$atendimento) {
            return $this->naoEncontrado();
        }

        $dados = $request->validate([
            'valor' => ['sometimes', 'numeric', 'min:0', 'max:99999999'],
            'forma_pagamento' => ['sometimes', 'nullable', Rule::in(AtendimentoEstetica::FORMAS)],
            'pago' => ['sometimes', 'boolean'],
            'previsao_entrega' => ['sometimes', 'nullable', 'date'],
            'cliente_nome' => ['sometimes', 'nullable', 'string', 'max:120'],
            'cliente_telefone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'observacoes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $atendimento->update($dados);

        return response()->json($this->formatar($atendimento->fresh(['servicos', 'fotos']), comFotos: true));
    }

    /** Leva o carro para a próxima etapa. A entrega só fecha com foto de checkout. */
    public function avancar($id)
    {
        $atendimento = AtendimentoEstetica::with('fotos')->find($id);

        if (!$atendimento) {
            return $this->naoEncontrado();
        }

        $proxima = $atendimento->etapaSeguinte();

        if ($proxima === null) {
            return response()->json(['message' => 'Este carro já foi entregue.'], 422);
        }

        if ($proxima === AtendimentoEstetica::ENTREGUE && !$atendimento->fotos->contains('momento', FotoEstetica::CHECKOUT)) {
            return response()->json(['message' => 'Antes de entregar, tire a foto de checkout do carro.'], 422);
        }

        $atendimento->forceFill([
            'etapa' => $proxima,
            'entregue_em' => $proxima === AtendimentoEstetica::ENTREGUE ? now() : $atendimento->entregue_em,
        ])->save();

        return response()->json($this->formatar($atendimento->fresh(['servicos', 'fotos']), comFotos: true));
    }

    public function destroy($id)
    {
        $atendimento = AtendimentoEstetica::find($id);

        if (!$atendimento) {
            return $this->naoEncontrado();
        }

        $atendimento->delete();

        return response()->json(['message' => 'Atendimento removido.']);
    }

    private function naoEncontrado()
    {
        return response()->json(['message' => 'Atendimento não encontrado.'], 404);
    }

    /** @return array<string, mixed> */
    private function formatar(AtendimentoEstetica $a, bool $comFotos = false): array
    {
        $dados = [
            'id' => $a->id,
            'placa' => $a->placa,
            'modelo' => $a->modelo,
            'cliente_nome' => $a->cliente_nome,
            'cliente_telefone' => $a->cliente_telefone,
            'etapa' => $a->etapa,
            'etapa_seguinte' => $a->etapaSeguinte(),
            'valor' => (float) $a->valor,
            'total_servicos' => $a->totalDosServicos(),
            'forma_pagamento' => $a->forma_pagamento,
            'pago' => (bool) $a->pago,
            'dias_em_aberto' => $a->diasEmAberto(),
            'entrada_em' => $a->entrada_em?->toIso8601String(),
            'previsao_entrega' => $a->previsao_entrega?->toIso8601String(),
            'entregue_em' => $a->entregue_em?->toIso8601String(),
            'observacoes' => $a->observacoes,
            'servicos' => $a->servicos->map(fn (AtendimentoServicoEstetica $s) => ['nome' => $s->nome, 'preco' => (float) $s->preco])->values(),
            'fotos_checkin' => $a->fotos->where('momento', FotoEstetica::CHECKIN)->count(),
            'fotos_checkout' => $a->fotos->where('momento', FotoEstetica::CHECKOUT)->count(),
        ];

        if ($comFotos) {
            $dados['fotos'] = $a->fotos->sortBy('id')->map(fn (FotoEstetica $f) => [
                'id' => $f->id,
                'momento' => $f->momento,
                'url' => $f->url(),
            ])->values();
        }

        return $dados;
    }
}
