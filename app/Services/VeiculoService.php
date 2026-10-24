<?php

namespace App\Services;

use App\Models\Veiculo;
use App\Repositories\VeiculoRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class VeiculoService
{
    public function __construct(
        private VeiculoRepository $repository,
        private FipeService $fipe
    ) {}

    /**
     * Cria o veículo e, se ele já veio com os códigos FIPE, busca o valor.
     * Se a FIPE estiver fora do ar o veículo continua salvo, com o valor em
     * branco (pode ser consultado depois pelo botão de atualizar).
     */
    public function criar(array $dados): Veiculo
    {
        $veiculo = $this->repository->create($dados);

        $this->preencherValorFipe($veiculo);

        return $veiculo;
    }

    public function listar(array $filtros = []): Collection
    {
        return $this->repository->listar($filtros);
    }

    public function paginar(array $filtros, int $porPagina): LengthAwarePaginator
    {
        return $this->repository->paginar($filtros, $porPagina);
    }

    public function resumo(): array
    {
        return $this->repository->resumo();
    }

    public function buscarPorId(int $id): ?Veiculo
    {
        return $this->repository->buscarPorId($id);
    }

    public function encontrar(int $id): ?Veiculo
    {
        return $this->repository->encontrar($id);
    }

    public function historico(int $id): ?Veiculo
    {
        return $this->repository->buscarComHistorico($id);
    }

    /**
     * Atualiza o veículo e refaz a consulta FIPE quando os códigos mudaram
     * ou quando ele ainda não tem valor.
     */
    public function atualizar(int $id, array $dados): ?Veiculo
    {
        $veiculo = $this->repository->atualizar($id, $dados);

        if (!$veiculo) {
            return null;
        }

        $codigosMudaram = $veiculo->wasChanged(['tipo_veiculo', 'fipe_marca_id', 'fipe_modelo_id', 'fipe_ano']);

        if ($codigosMudaram || $veiculo->fipe_valor === null) {
            $this->preencherValorFipe($veiculo);
        }

        return $veiculo;
    }

    public function remover(int $id): bool
    {
        return $this->repository->deletar($id);
    }

    public function possuiCodigosFipe(Veiculo $veiculo): bool
    {
        return $veiculo->fipe_marca_id && $veiculo->fipe_modelo_id && $veiculo->fipe_ano;
    }

    /**
     * Consulta a FIPE e grava o valor. Exige os três códigos no veículo.
     *
     * @throws FipeIndisponivelException quando a FIPE não responde
     */
    public function consultarFipe(Veiculo $veiculo): Veiculo
    {
        $resultado = $this->fipe->valor(
            $veiculo->tipoFipe(),
            $veiculo->fipe_marca_id,
            $veiculo->fipe_modelo_id,
            $veiculo->fipe_ano
        );

        $this->gravarValorFipe($veiculo, $this->valorParaDecimal($resultado['Valor'] ?? ''));

        return $veiculo;
    }

    private function preencherValorFipe(Veiculo $veiculo): void
    {
        if (!$this->possuiCodigosFipe($veiculo)) {
            return;
        }

        try {
            $this->consultarFipe($veiculo);
        } catch (FipeIndisponivelException) {
            // O veículo já está salvo; o valor pode ser consultado depois.
        }
    }

    /**
     * Atualiza o valor FIPE atual do veículo e registra uma linha em
     * fipe_historicos, para a tela de histórico mostrar a evolução do
     * valor ao longo das consultas.
     */
    private function gravarValorFipe(Veiculo $veiculo, float $valor): void
    {
        $agora = now();

        $veiculo->update([
            'fipe_valor' => $valor,
            'fipe_consultado_em' => $agora,
        ]);

        $veiculo->historicoFipe()->create([
            'valor' => $valor,
            'consultado_em' => $agora,
        ]);
    }

    private function valorParaDecimal(string $valor): float
    {
        return (float) str_replace(['.', ','], ['', '.'], preg_replace('/[^\d.,]/', '', $valor));
    }
}
