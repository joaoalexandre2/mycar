<?php

namespace App\Http\Controllers;

use App\Models\Documento;
use App\Models\VeiculoConta;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Documentos dos veículos da conta (pessoa e frota) com vencimento: CRLV,
 * vistoria e outros. Isolados pela conta via PertenceAConta.
 */
class DocumentoController extends Controller
{
    /** Dias para o documento ser considerado "vence em breve". */
    private const DIAS_EM_BREVE = 30;

    public function index($id)
    {
        $veiculo = VeiculoConta::find($id);

        if (!$veiculo) {
            return $this->naoEncontrado('Veículo');
        }

        return response()->json($this->montar($veiculo));
    }

    public function store(Request $request, $id)
    {
        $veiculo = VeiculoConta::find($id);

        if (!$veiculo) {
            return $this->naoEncontrado('Veículo');
        }

        $dados = $request->validate([
            'tipo' => ['required', Rule::in(Documento::TIPOS)],
            'titulo' => ['required_if:tipo,outro', 'nullable', 'string', 'max:80'],
            'vencimento' => ['nullable', 'date'],
            'observacoes' => ['nullable', 'string', 'max:255'],
        ], [], [
            'titulo' => 'nome do documento',
        ]);

        if ($dados['tipo'] !== 'outro') {
            $dados['titulo'] = null;
        }

        $veiculo->documentos()->create($dados);

        return response()->json($this->montar($veiculo), 201);
    }

    public function destroy($id, $documentoId)
    {
        $veiculo = VeiculoConta::find($id);

        if (!$veiculo) {
            return $this->naoEncontrado('Veículo');
        }

        $documento = $veiculo->documentos()->find($documentoId);

        if (!$documento) {
            return $this->naoEncontrado('Documento');
        }

        $documento->delete();

        return response()->json($this->montar($veiculo));
    }

    /**
     * @return array<string, mixed>
     */
    private function montar(VeiculoConta $veiculo): array
    {
        $hoje = now()->startOfDay();
        $crlv = $veiculo->crlvAtual();

        return [
            'documentos' => $veiculo->documentos()
                ->orderByRaw('vencimento is null')
                ->orderBy('vencimento')
                ->get()
                ->map(function (Documento $d) use ($hoje) {
                    $dias = $d->vencimento ? (int) $hoje->diffInDays($d->vencimento->copy()->startOfDay(), false) : null;

                    return $d->toArray() + [
                        'dias_para_vencer' => $dias,
                        'situacao' => match (true) {
                            $dias === null => 'sem_data',
                            $dias < 0 => 'vencido',
                            $dias <= self::DIAS_EM_BREVE => 'vence_em_breve',
                            default => 'em_dia',
                        },
                    ];
                })
                ->values(),
            // A data real do CRLV substitui a estimativa pela placa nos avisos.
            'crlv' => [
                'vencimento' => $crlv?->vencimento?->toDateString(),
                'estimativa_licenciamento' => $veiculo->proximo_vencimento_licenciamento,
            ],
        ];
    }

    private function naoEncontrado(string $o_que)
    {
        return response()->json(['message' => "{$o_que} não encontrado."], 404);
    }
}
