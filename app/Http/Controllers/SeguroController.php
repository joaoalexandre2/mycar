<?php

namespace App\Http\Controllers;

use App\Enums\TipoVeiculo;
use App\Models\Seguro;
use App\Models\VeiculoConta;
use App\Services\ComparadorSeguro;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Seguro dos veículos da conta (pessoa e frota): apólice atual, propostas
 * recebidas e comparação com a faixa de referência pelo valor FIPE.
 */
class SeguroController extends Controller
{
    public function index($id, ComparadorSeguro $comparador)
    {
        $veiculo = VeiculoConta::find($id);

        if (!$veiculo) {
            return $this->naoEncontrado('Veículo');
        }

        return response()->json($this->montar($veiculo, $comparador));
    }

    public function store(Request $request, $id, ComparadorSeguro $comparador)
    {
        $veiculo = VeiculoConta::find($id);

        if (!$veiculo) {
            return $this->naoEncontrado('Veículo');
        }

        $dados = $request->validate([
            'tipo' => ['required', Rule::in(Seguro::TIPOS)],
            'seguradora' => ['required', 'string', 'max:60'],
            'valor_anual' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'franquia' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            // O fim da vigência é o que gera o lembrete de renovação: obrigatório na apólice.
            'vigencia_fim' => ['required_if:tipo,apolice', 'nullable', 'date'],
            'observacoes' => ['nullable', 'string', 'max:255'],
        ], [], [
            'vigencia_fim' => 'fim da vigência',
            'valor_anual' => 'valor anual',
        ]);

        // Proposta não tem vigência.
        if ($dados['tipo'] === Seguro::TIPO_PROPOSTA) {
            $dados['vigencia_fim'] = null;
        }

        $veiculo->seguros()->create($dados);

        // Cadastrar o seguro atual já responde "tem seguro".
        if ($dados['tipo'] === Seguro::TIPO_APOLICE && $veiculo->possui_seguro !== true) {
            $veiculo->forceFill(['possui_seguro' => true])->save();
        }

        return response()->json($this->montar($veiculo, $comparador), 201);
    }

    /**
     * "Este veículo já tem seguro?" Sim abre o cadastro do seguro atual (com o
     * vencimento, que gera o lembrete de renovação). Não deixa só as propostas.
     */
    public function possui(Request $request, $id, ComparadorSeguro $comparador)
    {
        $veiculo = VeiculoConta::find($id);

        if (!$veiculo) {
            return $this->naoEncontrado('Veículo');
        }

        $dados = $request->validate([
            'possui' => ['required', 'boolean'],
        ], [], ['possui' => 'resposta']);

        if (!$dados['possui'] && $veiculo->seguros()->where('tipo', Seguro::TIPO_APOLICE)->exists()) {
            throw ValidationException::withMessages([
                'possui' => ['Este veículo tem um seguro cadastrado. Remova-o antes de marcar que não tem seguro.'],
            ]);
        }

        $veiculo->forceFill(['possui_seguro' => (bool) $dados['possui']])->save();

        return response()->json($this->montar($veiculo, $comparador));
    }

    public function destroy($id, $seguroId, ComparadorSeguro $comparador)
    {
        $veiculo = VeiculoConta::find($id);

        if (!$veiculo) {
            return $this->naoEncontrado('Veículo');
        }

        $seguro = $veiculo->seguros()->find($seguroId);

        if (!$seguro) {
            return $this->naoEncontrado('Seguro');
        }

        $seguro->delete();

        return response()->json($this->montar($veiculo, $comparador));
    }

    /**
     * @return array<string, mixed>
     */
    private function montar(VeiculoConta $veiculo, ComparadorSeguro $comparador): array
    {
        $seguros = $veiculo->seguros()->orderBy('tipo')->orderBy('valor_anual')->get();

        $comparacao = $comparador->comparar(
            $seguros->map(fn (Seguro $s) => [
                'id' => $s->id,
                'tipo' => $s->tipo,
                'valor_anual' => (float) $s->valor_anual,
                'vigencia_fim' => $s->vigencia_fim?->toDateString(),
            ]),
            $veiculo->fipe_valor !== null ? (float) $veiculo->fipe_valor : null,
            // Cada tipo tem a sua faixa (caminhão não tem: config/seguro.php).
            $veiculo->tipo_veiculo ?? TipoVeiculo::Carro
        );

        $extras = collect($comparacao['itens'])->keyBy('id');

        $temApolice = $seguros->contains(fn (Seguro $s) => $s->tipo === Seguro::TIPO_APOLICE);

        return [
            // true = tem seguro (resposta ou apólice cadastrada); false = não tem; null = não respondeu.
            'possui_seguro' => $temApolice ? true : ($veiculo->possui_seguro === null ? null : (bool) $veiculo->possui_seguro),
            'seguros' => $seguros->map(fn (Seguro $s) => $s->toArray() + [
                'valor_mensal' => $extras[$s->id]['valor_mensal'],
                'vs_referencia_pct' => $extras[$s->id]['vs_referencia_pct'],
                'economia_vs_apolice' => $extras[$s->id]['economia_vs_apolice'],
            ])->values(),
            'referencia' => $comparacao['referencia'],
            'apolice_atual_id' => $comparacao['apolice_atual_id'],
            'melhor_proposta_id' => $comparacao['melhor_proposta_id'],
            'aviso' => 'Referência pelo valor FIPE, não é cotação: o preço real depende do CEP, do condutor, do bônus e das coberturas.',
        ];
    }

    private function naoEncontrado(string $o_que)
    {
        return response()->json(['message' => "{$o_que} não encontrado."], 404);
    }
}
