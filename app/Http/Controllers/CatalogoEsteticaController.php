<?php

namespace App\Http\Controllers;

use App\Models\PacoteEstetica;
use App\Models\ServicoEstetica;
use Illuminate\Http\Request;

/** Serviços e pacotes da estética. Serviço usado em atendimento não some: sai do catálogo (inativo). */
class CatalogoEsteticaController extends Controller
{
    public function servicos()
    {
        return response()->json(
            ServicoEstetica::query()->orderBy('nome')->get()->map(fn (ServicoEstetica $s) => $this->servico($s))->values(),
        );
    }

    public function criarServico(Request $request)
    {
        $dados = $this->validarServico($request);
        $dados['dias_previstos'] = $dados['dias_previstos'] ?? 0;

        $servico = ServicoEstetica::create($dados);

        return response()->json($this->servico($servico), 201);
    }

    public function atualizarServico(Request $request, $id)
    {
        $servico = ServicoEstetica::find($id);

        if (!$servico) {
            return response()->json(['message' => 'Serviço não encontrado.'], 404);
        }

        $servico->update($this->validarServico($request, parcial: true));

        return response()->json($this->servico($servico->fresh()));
    }

    public function pacotes()
    {
        return response()->json(
            PacoteEstetica::query()->with('servicos')->orderBy('nome')->get()->map(fn (PacoteEstetica $p) => $this->pacote($p))->values(),
        );
    }

    public function criarPacote(Request $request)
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'preco' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'servicos' => ['required', 'array', 'min:1'],
            'servicos.*' => ['integer'],
        ]);

        $pacote = PacoteEstetica::create(['nome' => $dados['nome'], 'preco' => $dados['preco']]);
        $pacote->servicos()->sync(ServicoEstetica::whereIn('id', $dados['servicos'])->pluck('id'));

        return response()->json($this->pacote($pacote->load('servicos')), 201);
    }

    public function removerPacote($id)
    {
        $pacote = PacoteEstetica::find($id);

        if (!$pacote) {
            return response()->json(['message' => 'Pacote não encontrado.'], 404);
        }

        $pacote->delete();

        return response()->json(['message' => 'Pacote removido.']);
    }

    /** @return array<string, mixed> */
    private function validarServico(Request $request, bool $parcial = false): array
    {
        $obrigatorio = $parcial ? 'sometimes' : 'required';

        return $request->validate([
            'nome' => [$obrigatorio, 'string', 'max:120'],
            'preco' => [$obrigatorio, 'numeric', 'min:0', 'max:99999999'],
            'dias_previstos' => ['nullable', 'integer', 'min:0', 'max:365'],
            'ativo' => ['sometimes', 'boolean'],
        ]);
    }

    /** @return array<string, mixed> */
    private function servico(ServicoEstetica $s): array
    {
        return [
            'id' => $s->id,
            'nome' => $s->nome,
            'preco' => (float) $s->preco,
            'dias_previstos' => (int) $s->dias_previstos,
            'longo' => (int) $s->dias_previstos > 0,
            'ativo' => (bool) $s->ativo,
        ];
    }

    /** @return array<string, mixed> */
    private function pacote(PacoteEstetica $p): array
    {
        return [
            'id' => $p->id,
            'nome' => $p->nome,
            'preco' => (float) $p->preco,
            'servicos' => $p->servicos->map(fn (ServicoEstetica $s) => ['id' => $s->id, 'nome' => $s->nome])->values(),
        ];
    }
}
