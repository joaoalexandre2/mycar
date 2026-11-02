<?php

namespace App\Http\Controllers;

use App\Models\Abastecimento;
use App\Models\VeiculoConta;
use App\Services\CalculoConsumo;
use App\Services\FichasCatalogo;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Abastecimentos dos veículos da conta (perfis pessoa e frota), com consumo médio e custo por km.
 * Isolados pela conta via PertenceAConta.
 *
 * Dados fora do padrão não bloqueiam quem registra sem explicação: um conflito (km que volta, possível duplicado)
 * exige justificativa; preço por litro fora da faixa e consumo fora da média viram alerta, sem bloquear.
 */
class AbastecimentoController extends Controller
{
    public function __construct(private readonly FichasCatalogo $fichasCatalogo) {}

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
            'hora' => ['nullable', 'date_format:H:i'],
            'km' => ['required', 'integer', 'min:0', 'max:9999999'],
            'litros' => ['required', 'numeric', 'gt:0', 'max:9999'],
            'valor_total' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'tanque_cheio' => ['sometimes', 'boolean'],
            'combustivel' => ['nullable', Rule::in(Abastecimento::COMBUSTIVEIS)],
            'posto' => ['nullable', 'string', 'max:80'],
            'motorista_id' => ['nullable', 'integer', Rule::exists('motoristas', 'id')->where('conta_id', app('conta.atual'))],
            'justificativa' => ['nullable', 'string', 'min:10', 'max:255'],
            'comprovante' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'hodometro' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        $conflitos = $this->conflitos($veiculo, $dados);

        if ($conflitos && empty($dados['justificativa'])) {
            $erros = ['justificativa' => ['Há dados fora do padrão. Informe a justificativa para registrar mesmo assim.']];

            foreach ($conflitos as $conflito) {
                $erros[$conflito['campo']][] = $conflito['mensagem'] . ' Se estiver certo, informe a justificativa para registrar.';
            }

            throw ValidationException::withMessages($erros);
        }

        $alertas = array_merge(
            array_map(fn (array $c) => ['codigo' => $c['codigo'], 'mensagem' => $c['mensagem']], $conflitos),
            $this->alertasDePreco($dados),
        );

        $registro = $veiculo->abastecimentos()->create(Arr::except($dados, ['comprovante', 'hodometro']) + [
            'alertas' => $alertas ?: null,
        ]);

        $this->guardarFoto($registro, 'comprovante', $request->file('comprovante'));
        $this->guardarFoto($registro, 'hodometro', $request->file('hodometro'));

        // O consumo só se compara com a média dos outros abastecimentos: o novo fica de fora dela.
        $this->alertaDeConsumo($veiculo, $registro, $calculo);

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

        Storage::disk('local')->delete(array_filter([$abastecimento->comprovante_caminho, $abastecimento->hodometro_caminho]));
        $abastecimento->delete();

        return response()->json($this->montar($veiculo, $calculo));
    }

    /**
     * Conflitos que pedem justificativa: o hodômetro só sobe (km menor que o anterior, ou maior que o seguinte),
     * e um abastecimento igual a este (mesma data, km, litros e valor) já existe.
     *
     * @return list<array{campo: string, codigo: string, mensagem: string}>
     */
    private function conflitos(VeiculoConta $veiculo, array $dados): array
    {
        $conflitos = [];
        $data = $dados['data'];
        $km = (int) $dados['km'];

        $anterior = $veiculo->abastecimentos()->where('data', '<=', $data)->orderByDesc('data')->orderByDesc('km')->first();
        $posterior = $veiculo->abastecimentos()->where('data', '>', $data)->orderBy('data')->orderBy('km')->first();

        if ($anterior && $km < $anterior->km) {
            $conflitos[] = ['campo' => 'km', 'codigo' => 'km_menor_que_anterior', 'mensagem' => "O km não pode ser menor que o do abastecimento anterior ({$anterior->km} km)."];
        }

        if ($posterior && $km > $posterior->km) {
            $conflitos[] = ['campo' => 'km', 'codigo' => 'km_maior_que_seguinte', 'mensagem' => "O km não pode ser maior que o do abastecimento seguinte ({$posterior->km} km)."];
        }

        $duplicado = $veiculo->abastecimentos()
            ->where('data', $data)
            ->where('km', $km)
            ->where('litros', $dados['litros'])
            ->where('valor_total', $dados['valor_total'])
            ->exists();

        if ($duplicado) {
            $conflitos[] = ['campo' => 'justificativa', 'codigo' => 'possivel_duplicado', 'mensagem' => 'Já existe um abastecimento igual a este: mesma data, km, litros e valor.'];
        }

        return $conflitos;
    }

    /** @return list<array{codigo: string, mensagem: string}> */
    private function alertasDePreco(array $dados): array
    {
        $preco = (float) $dados['valor_total'] / (float) $dados['litros'];
        ['min' => $min, 'max' => $max] = config('abastecimento.preco_litro');

        if ($preco >= $min && $preco <= $max) {
            return [];
        }

        return [[
            'codigo' => 'preco_fora_da_faixa',
            'mensagem' => sprintf(
                'Preço de R$ %s por litro fora da faixa de R$ %s a R$ %s.',
                number_format($preco, 2, ',', ''),
                number_format($min, 2, ',', ''),
                number_format($max, 2, ',', ''),
            ),
        ]];
    }

    /** Consumo deste abastecimento contra a média dos outros do veículo. Só vira alerta se fugir do desvio aceito. */
    private function alertaDeConsumo(VeiculoConta $veiculo, Abastecimento $registro, CalculoConsumo $calculo): void
    {
        $outros = $veiculo->abastecimentos()->where('id', '!=', $registro->id)->get();
        $media = $calculo->calcular($this->paraCalculo($outros))['consumo_medio_km_l'];

        $consumo = $calculo->calcular($this->paraCalculo($veiculo->abastecimentos()->get()))['registros'];
        $consumoDoNovo = collect($consumo)->firstWhere('id', $registro->id)['consumo_km_l'] ?? null;

        if (!$media || !$consumoDoNovo) {
            return;
        }

        $desvio = config('abastecimento.desvio_consumo');

        if (abs($consumoDoNovo - $media) / $media <= $desvio) {
            return;
        }

        $alertas = $registro->alertas ?? [];
        $alertas[] = [
            'codigo' => 'consumo_fora_do_padrao',
            'mensagem' => sprintf('Consumo de %s km/l, contra uma média de %s km/l.', number_format($consumoDoNovo, 2, ',', ''), number_format($media, 2, ',', '')),
        ];

        $registro->update(['alertas' => $alertas]);
    }

    private function guardarFoto(Abastecimento $registro, string $tipo, ?UploadedFile $arquivo): void
    {
        if (!$arquivo) {
            return;
        }

        $caminho = "abastecimentos/{$registro->conta_id}/{$registro->veiculo_conta_id}/{$registro->id}-{$tipo}-" . Str::uuid() . '.' . ($arquivo->extension() ?: 'jpg');

        Storage::disk('local')->put($caminho, $arquivo->get());

        $registro->update([$tipo === 'comprovante' ? 'comprovante_caminho' : 'hodometro_caminho' => $caminho]);
    }

    private function paraCalculo($registros): Collection
    {
        return $registros->map(fn (Abastecimento $a) => [
            'id' => $a->id,
            'data' => $a->data->toDateString(),
            'km' => $a->km,
            'litros' => (float) $a->litros,
            'valor_total' => (float) $a->valor_total,
            'tanque_cheio' => $a->tanque_cheio,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function montar(VeiculoConta $veiculo, CalculoConsumo $calculo): array
    {
        $registros = $veiculo->abastecimentos()->get();

        $resultado = $calculo->calcular($this->paraCalculo($registros));

        $calculados = collect($resultado['registros'])->keyBy('id');

        return [
            // Capacidade do tanque na ficha do modelo: a tela usa quando a pessoa
            // marca "encheu o tanque". Nulo = a ficha não traz (digita os litros).
            'tanque_litros' => $veiculo->ehCarro() ? $this->fichasCatalogo->tanqueLitros($veiculo->marca, $veiculo->modelo) : null,
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
