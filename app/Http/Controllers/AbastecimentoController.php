<?php

namespace App\Http\Controllers;

use App\Models\Abastecimento;
use App\Models\VeiculoConta;
use App\Services\CalculoConsumo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Abastecimentos dos veículos da conta (perfis pessoa e frota), com consumo
 * médio e custo por km. Isolados pela conta via PertenceAConta.
 */
class AbastecimentoController extends Controller
{
    public function index($id, CalculoConsumo $calculo)
    {
        $veiculo = VeiculoConta::find($id);

        if (!$veiculo) {
            return $this->veiculoNaoEncontrado();
        }

        return response()->json($this->montar($veiculo, $calculo));
    }

    public function store(Request $request, $id, CalculoConsumo $calculo)
    {
        $veiculo = VeiculoConta::find($id);

        if (!$veiculo) {
            return $this->veiculoNaoEncontrado();
        }

        $dados = $request->validate([
            'data' => ['required', 'date', 'before_or_equal:today'],
            'km' => ['required', 'integer', 'min:0', 'max:9999999'],
            'litros' => ['required', 'numeric', 'gt:0', 'max:9999'],
            'valor_total' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'tanque_cheio' => ['sometimes', 'boolean'],
            'combustivel' => ['nullable', Rule::in(Abastecimento::COMBUSTIVEIS)],
            'posto' => ['nullable', 'string', 'max:80'],
        ]);

        $this->validarSequenciaDeKm($veiculo, $dados['data'], (int) $dados['km']);

        $veiculo->abastecimentos()->create($dados);

        return response()->json($this->montar($veiculo, $calculo), 201);
    }

    public function destroy($id, $abastecimentoId, CalculoConsumo $calculo)
    {
        $veiculo = VeiculoConta::find($id);

        if (!$veiculo) {
            return $this->veiculoNaoEncontrado();
        }

        $abastecimento = $veiculo->abastecimentos()->find($abastecimentoId);

        if (!$abastecimento) {
            return response()->json(['message' => 'Abastecimento não encontrado.'], 404);
        }

        $abastecimento->delete();

        return response()->json($this->montar($veiculo, $calculo));
    }

    /**
     * O hodômetro só sobe: o km não pode ser menor que o de um abastecimento
     * anterior, nem maior que o de um posterior.
     */
    private function validarSequenciaDeKm(VeiculoConta $veiculo, string $data, int $km): void
    {
        $anterior = $veiculo->abastecimentos()->where('data', '<=', $data)->orderByDesc('data')->orderByDesc('km')->first();
        $posterior = $veiculo->abastecimentos()->where('data', '>', $data)->orderBy('data')->orderBy('km')->first();

        if ($anterior && $km < $anterior->km) {
            throw ValidationException::withMessages([
                'km' => ["O km não pode ser menor que o do abastecimento anterior ({$anterior->km} km)."],
            ]);
        }

        if ($posterior && $km > $posterior->km) {
            throw ValidationException::withMessages([
                'km' => ["O km não pode ser maior que o do abastecimento seguinte ({$posterior->km} km)."],
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function montar(VeiculoConta $veiculo, CalculoConsumo $calculo): array
    {
        $registros = $veiculo->abastecimentos()->get();

        $resultado = $calculo->calcular($registros->map(fn (Abastecimento $a) => [
            'id' => $a->id,
            'data' => $a->data->toDateString(),
            'km' => $a->km,
            'litros' => (float) $a->litros,
            'valor_total' => (float) $a->valor_total,
            'tanque_cheio' => $a->tanque_cheio,
        ]));

        $calculados = collect($resultado['registros'])->keyBy('id');

        return [
            'abastecimentos' => $registros
                ->sortByDesc(fn (Abastecimento $a) => $a->data->toDateString() . str_pad((string) $a->km, 8, '0', STR_PAD_LEFT) . str_pad((string) $a->id, 8, '0', STR_PAD_LEFT))
                ->map(fn (Abastecimento $a) => $a->toArray() + [
                    'preco_litro' => $calculados[$a->id]['preco_litro'],
                    'consumo_km_l' => $calculados[$a->id]['consumo_km_l'],
                    'custo_por_km' => $calculados[$a->id]['custo_por_km'],
                ])
                ->values(),
            'resumo' => [
                'consumo_medio_km_l' => $resultado['consumo_medio_km_l'],
                'custo_por_km' => $resultado['custo_por_km'],
                'total_gasto' => $resultado['total_gasto'],
                'total_litros' => $resultado['total_litros'],
                'preco_medio_litro' => $resultado['preco_medio_litro'],
                'km_atual' => $resultado['km_atual'],
                'quantidade' => $registros->count(),
            ],
        ];
    }

    private function veiculoNaoEncontrado()
    {
        return response()->json(['message' => 'Veículo não encontrado.'], 404);
    }
}
