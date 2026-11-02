<?php

namespace App\Http\Controllers;

use App\Models\Servico;
use App\Models\ServicoAnexo;
use App\Models\VeiculoConta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Serviços feitos nos veículos da conta (troca de óleo, bateria, palhetas...) com aviso da próxima vez
 * por prazo e/ou km, peças usadas, divisão entre peças e mão de obra, e notas fiscais. Isolados pela conta via PertenceAConta.
 */
class ServicoController extends Controller
{
    private const MIMES = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function index(Request $request)
    {
        $request->validate(['veiculo_id' => ['nullable', 'integer']]);

        return response()->json($this->montar($request->query('veiculo_id')));
    }

    public function store(Request $request)
    {
        $dados = $this->validar($request);

        if (!VeiculoConta::find($dados['veiculo_conta_id'])) {
            return response()->json(['message' => 'Veículo não encontrado.'], 404);
        }

        $servico = DB::transaction(function () use ($dados) {
            $pecas = $this->pecasDo($dados);
            $dados = $this->comTotais($dados, $pecas);
            unset($dados['pecas']);

            $servico = Servico::create($dados);
            $this->gravarPecas($servico, $pecas);

            return $servico;
        });

        return response()->json($this->montar(), 201);
    }

    public function update(Request $request, int $id)
    {
        $servico = Servico::find($id);

        if (!$servico) {
            return response()->json(['message' => 'Serviço não encontrado.'], 404);
        }

        $dados = $this->validar($request);

        DB::transaction(function () use ($servico, $dados) {
            $pecas = $this->pecasDo($dados);
            $dados = $this->comTotais($dados, $pecas);
            unset($dados['pecas'], $dados['veiculo_conta_id']);

            $servico->update($dados);
            $servico->pecas()->delete();
            $this->gravarPecas($servico, $pecas);
        });

        return response()->json($this->montar($servico->veiculo_conta_id));
    }

    public function destroy($id)
    {
        $servico = Servico::with('anexos')->find($id);

        if (!$servico) {
            return response()->json(['message' => 'Serviço não encontrado.'], 404);
        }

        Storage::disk('local')->delete($servico->anexos->pluck('caminho')->all());
        $servico->delete();

        return response()->json($this->montar());
    }

    /** Nota fiscal, recibo ou orçamento do serviço. */
    public function anexar(Request $request, int $id)
    {
        $servico = Servico::find($id);

        if (!$servico) {
            return response()->json(['message' => 'Serviço não encontrado.'], 404);
        }

        $dados = $request->validate([
            'arquivo' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:8192'],
        ]);

        $arquivo = $dados['arquivo'];
        $caminho = "servicos/{$servico->conta_id}/{$servico->id}/" . Str::uuid() . '.' . ($arquivo->extension() ?: 'pdf');

        Storage::disk('local')->put($caminho, $arquivo->get());

        $anexo = ServicoAnexo::create([
            'servico_id' => $servico->id,
            'nome' => Str::limit($arquivo->getClientOriginalName(), 120, ''),
            'caminho' => $caminho,
        ]);

        return response()->json($anexo, 201);
    }

    public function removerAnexo(int $anexo)
    {
        $registro = ServicoAnexo::find($anexo);

        if (!$registro) {
            return response()->json(['message' => 'Anexo não encontrado.'], 404);
        }

        Storage::disk('local')->delete($registro->caminho);
        $registro->delete();

        return response()->json($this->montar($registro->servico?->veiculo_conta_id));
    }

    private function validar(Request $request): array
    {
        $dados = $request->validate([
            'veiculo_conta_id' => ['required', 'integer'],
            'tipo' => ['required', Rule::in(array_keys(config('servicos.tipos')))],
            'titulo' => ['required_if:tipo,outro', 'nullable', 'string', 'max:80'],
            'realizado_em' => ['required', 'date', 'before_or_equal:today'],
            'km' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'valor' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'valor_pecas' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'valor_mao_obra' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'natureza' => ['nullable', Rule::in(Servico::NATUREZAS)],
            'oficina' => ['nullable', 'string', 'max:80'],
            'pecas' => ['nullable', 'array', 'max:50'],
            'pecas.*.descricao' => ['required', 'string', 'max:120'],
            'pecas.*.quantidade' => ['required', 'numeric', 'gt:0', 'max:9999'],
            'pecas.*.valor_unitario' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'observacoes' => ['nullable', 'string', 'max:255'],
            'intervalo_meses' => ['nullable', 'integer', 'min:1', 'max:120'],
            'intervalo_km' => ['nullable', 'integer', 'min:100', 'max:1000000'],
        ], [], [
            'titulo' => 'nome do serviço',
            'intervalo_km' => 'intervalo em km',
        ]);

        // Aviso por km só faz sentido sabendo o km em que o serviço foi feito.
        if (!empty($dados['intervalo_km']) && !isset($dados['km'])) {
            throw ValidationException::withMessages(['km' => ['Informe o km do veículo no dia do serviço para avisar por quilometragem.']]);
        }

        return $dados;
    }

    /** @return list<array{descricao: string, quantidade: float, valor_unitario: float}> */
    private function pecasDo(array $dados): array
    {
        return collect($dados['pecas'] ?? [])
            ->map(fn (array $p) => [
                'descricao' => trim($p['descricao']),
                'quantidade' => (float) $p['quantidade'],
                'valor_unitario' => (float) $p['valor_unitario'],
            ])
            ->values()
            ->all();
    }

    /**
     * O total é a soma de peças e mão de obra quando a divisão é informada; senão, vale o valor digitado.
     * Peças sem mão de obra informada contam como total das peças.
     */
    private function comTotais(array $dados, array $pecas): array
    {
        $valorPecas = $pecas !== []
            ? round(collect($pecas)->sum(fn (array $p) => $p['quantidade'] * $p['valor_unitario']), 2)
            : ($dados['valor_pecas'] ?? null);

        $maoDeObra = $dados['valor_mao_obra'] ?? null;

        if ($valorPecas !== null || $maoDeObra !== null) {
            $dados['valor_pecas'] = $valorPecas;
            $dados['valor_mao_obra'] = $maoDeObra;
            $dados['valor'] = round((float) ($valorPecas ?? 0) + (float) ($maoDeObra ?? 0), 2);
        } else {
            $dados['valor_pecas'] = null;
            $dados['valor_mao_obra'] = null;
        }

        if (($dados['tipo'] ?? null) !== 'outro') {
            $dados['titulo'] = null;
        }

        return $dados;
    }

    private function gravarPecas(Servico $servico, array $pecas): void
    {
        foreach ($pecas as $peca) {
            $servico->pecas()->create($peca);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function montar(mixed $veiculoId = null): array
    {
        $hoje = now()->startOfDay();
        $veiculos = VeiculoConta::all()->keyBy('id');

        $kmAtual = $veiculos->map(fn (VeiculoConta $v) => $v->kmAtual());

        $consulta = Servico::query()->with(['pecas', 'anexos'])->orderByDesc('realizado_em')->orderByDesc('id');

        if ($veiculoId !== null) {
            $consulta->where('veiculo_conta_id', $veiculoId);
        }

        $servicos = $consulta->get();

        // Só o mais recente de cada tipo, por veículo, é o "vigente".
        $vigentes = $servicos->unique(fn (Servico $s) => $s->chave())->pluck('id')->flip();

        $linhas = $servicos->map(function (Servico $s) use ($vigentes, $veiculos, $kmAtual, $hoje) {
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
        });

        return [
            'tipos' => collect(config('servicos.tipos'))
                ->map(fn ($rotulo, $tipo) => ['tipo' => $tipo, 'rotulo' => $rotulo])
                ->values(),
            'servicos' => $linhas->values(),
            // Quem pede atenção agora: serviços vigentes vencidos ou perto de vencer. O total de custo vem junto.
            'resumo' => [
                'vencidos' => $linhas->where('vigente', true)->where('situacao', 'vencido')->count(),
                'vence_em_breve' => $linhas->where('vigente', true)->where('situacao', 'vence_em_breve')->count(),
                'custo_total' => round((float) $servicos->sum('valor'), 2),
            ],
        ];
    }
}
