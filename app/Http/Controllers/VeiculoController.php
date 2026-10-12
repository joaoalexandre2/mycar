<?php

namespace App\Http\Controllers;

use App\Http\Requests\SalvarVeiculoRequest;
use App\Services\FipeIndisponivelException;
use App\Services\VeiculoService;
use Illuminate\Http\Request;

class VeiculoController extends Controller
{
    public function __construct(
        private VeiculoService $veiculoService
    ) {}

    public function store(SalvarVeiculoRequest $request)
    {
        $veiculo = $this->veiculoService->criar($request->validated());

        return response()->json($veiculo, 201);
    }

    public function index(Request $request)
    {
        $filtros = ['busca' => $request->query('busca')];

        if ($request->boolean('all')) {
            return response()->json($this->veiculoService->listar($filtros), 200);
        }

        $porPaginaSolicitada = (int) $request->query('per_page', 15);
        $porPagina = $porPaginaSolicitada > 0 ? min($porPaginaSolicitada, 100) : 15;
        $paginador = $this->veiculoService->paginar($filtros, $porPagina);

        return response()->json([
            'data' => $paginador->items(),
            'meta' => [
                'current_page' => $paginador->currentPage(),
                'last_page' => $paginador->lastPage(),
                'per_page' => $paginador->perPage(),
                'total' => $paginador->total(),
            ],
            'resumo' => $this->veiculoService->resumo(),
        ], 200);
    }

    public function show($id)
    {
        $veiculo = $this->veiculoService->buscarPorId((int) $id);

        if (!$veiculo) {
            return $this->naoEncontrado();
        }

        return response()->json($veiculo);
    }

    public function update(SalvarVeiculoRequest $request, $id)
    {
        $veiculo = $this->veiculoService->atualizar((int) $id, $request->validated());

        if (!$veiculo) {
            return $this->naoEncontrado();
        }

        return response()->json($veiculo);
    }

    public function consultarFipe($id)
    {
        $veiculo = $this->veiculoService->encontrar((int) $id);

        if (!$veiculo) {
            return $this->naoEncontrado();
        }

        if (!$this->veiculoService->possuiCodigosFipe($veiculo)) {
            return response()->json([
                'message' => 'Veículo sem código FIPE (marca, modelo e ano) cadastrado.',
            ], 422);
        }

        try {
            $this->veiculoService->consultarFipe($veiculo);
        } catch (FipeIndisponivelException) {
            return response()->json([
                'message' => 'Não foi possível consultar a tabela FIPE no momento.',
            ], 502);
        }

        return response()->json($veiculo);
    }

    public function historico($id)
    {
        $veiculo = $this->veiculoService->historico((int) $id);

        if (!$veiculo) {
            return $this->naoEncontrado();
        }

        return response()->json([
            'veiculo' => $veiculo,
            'ordens_servico' => $veiculo->ordensServico,
            'manutencoes' => $veiculo->manutencoes,
            'fipe_historico' => $veiculo->historicoFipe,
        ]);
    }

    public function destroy($id)
    {
        if (!$this->veiculoService->remover((int) $id)) {
            return $this->naoEncontrado();
        }

        return response()->json([
            'message' => 'Veículo removido com sucesso.',
        ]);
    }

    private function naoEncontrado()
    {
        return response()->json([
            'message' => 'Veículo não encontrado.',
        ], 404);
    }
}
