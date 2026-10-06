<?php

namespace App\Http\Controllers;

use App\Models\Servico;
use App\Models\VeiculoConta;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Serviços feitos nos veículos da conta (troca de óleo, bateria, palhetas...)
 * com aviso da próxima vez por prazo e/ou km. Isolados pela conta via
 * PertenceAConta.
 */
class ServicoController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['veiculo_id' => ['nullable', 'integer']]);

        return response()->json($this->montar($request->query('veiculo_id')));
    }

    public function store(Request $request)
    {
        $dados = $request->validate([
            'veiculo_conta_id' => ['required', 'integer'],
            'tipo' => ['required', Rule::in(array_keys(config('servicos.tipos')))],
            'titulo' => ['required_if:tipo,outro', 'nullable', 'string', 'max:80'],
            'realizado_em' => ['required', 'date', 'before_or_equal:today'],
            'km' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'valor' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'observacoes' => ['nullable', 'string', 'max:255'],
            'intervalo_meses' => ['nullable', 'integer', 'min:1', 'max:120'],
            'intervalo_km' => ['nullable', 'integer', 'min:100', 'max:1000000'],
        ], [], [
            'titulo' => 'nome do serviço',
            'intervalo_km' => 'intervalo em km',
        ]);

        // Aviso por km só faz sentido sabendo o km em que o serviço foi feito.
        if (!empty($dados['intervalo_km']) && !isset($dados['km'])) {
            return response()->json([
                'message' => 'Informe o km do veículo no dia do serviço para avisar por quilometragem.',
                'errors' => ['km' => ['Informe o km do veículo no dia do serviço para avisar por quilometragem.']],
            ], 422);
        }

        if (!VeiculoConta::find($dados['veiculo_conta_id'])) {
            return response()->json(['message' => 'Veículo não encontrado.'], 404);
        }

        if ($dados['tipo'] !== 'outro') {
            $dados['titulo'] = null;
        }

        Servico::create($dados);

        return response()->json($this->montar(), 201);
    }

    public function destroy($id)
    {
        $servico = Servico::find($id);

        if (!$servico) {
            return response()->json(['message' => 'Serviço não encontrado.'], 404);
        }

        $servico->delete();

        return response()->json($this->montar());
    }

    /**
     * @return array<string, mixed>
     */
    private function montar(mixed $veiculoId = null): array
    {
        $hoje = now()->startOfDay();
        $veiculos = VeiculoConta::all()->keyBy('id');

        $kmAtual = $veiculos->map(fn (VeiculoConta $v) => $v->kmAtual());

        $consulta = Servico::query()->orderByDesc('realizado_em')->orderByDesc('id');

        if ($veiculoId !== null) {
            $consulta->where('veiculo_conta_id', $veiculoId);
        }

        $servicos = $consulta->get();

        // Só o mais recente de cada tipo, por veículo, é o "vigente".
        $vigentes = $servicos->unique(fn (Servico $s) => $s->chave())->pluck('id')->flip();

        return [
            'tipos' => collect(config('servicos.tipos'))
                ->map(fn ($rotulo, $tipo) => ['tipo' => $tipo, 'rotulo' => $rotulo])
                ->values(),
            'servicos' => $servicos->map(function (Servico $s) use ($vigentes, $veiculos, $kmAtual, $hoje) {
                $veiculo = $veiculos->get($s->veiculo_conta_id);
                $atual = $kmAtual->get($s->veiculo_conta_id);
                $vigente = $vigentes->has($s->id);

                return $s->toArray() + [
                    'veiculo' => $veiculo ? trim($veiculo->apelido ?: "{$veiculo->marca} {$veiculo->modelo}") : null,
                    'placa' => $veiculo?->placa,
                    'vigente' => $vigente,
                    'km_atual' => $atual,
                    'dias_restantes' => $vigente ? $s->diasRestantes($hoje) : null,
                    'km_restante' => $vigente ? $s->kmRestante($atual) : null,
                    'situacao' => $vigente ? $s->situacao($atual, $hoje) : 'anterior',
                ];
            })->values(),
        ];
    }
}
