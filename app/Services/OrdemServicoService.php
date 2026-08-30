<?php

namespace App\Services;

use App\Models\OrdemServico;
use App\Repositories\Interfaces\OrdemServicoRepositoryInterface;
use Illuminate\Validation\ValidationException;

class OrdemServicoService
{
    private OrdemServicoRepositoryInterface $repository;

    public function __construct(
        OrdemServicoRepositoryInterface $repository
    ) {
        $this->repository = $repository;
    }

    public function criar(array $dados): OrdemServico
    {
        /*
         * Toda nova ordem de serviço começa como aberta.
         */
        $dados['status'] = 'aberta';
        $dados['data_fechamento'] = null;

        return $this->repository->criar($dados);
    }

    public function listar()
    {
        return $this->repository->listar();
    }

    public function buscarPorId(int $id): ?OrdemServico
    {
        return $this->repository->buscarPorId($id);
    }

    public function atualizar(int $id, array $dados): ?OrdemServico
    {
        $ordemServico = $this->repository->buscarPorId($id);

        if (!$ordemServico) {
            return null;
        }

        $statusAtual = $ordemServico->status;
        $novoStatus = $dados['status'] ?? $statusAtual;

        /*
         * Ao finalizar uma OS, a data de fechamento
         * é preenchida automaticamente.
         */
        if (
            $novoStatus === 'finalizada' &&
            $statusAtual !== 'finalizada'
        ) {
            $dados['data_fechamento'] = now();
        }

        $this->validarRegras($dados, $ordemServico);

        return $this->repository->atualizar($id, $dados);
    }

    public function remover(int $id): bool
    {
        $ordemServico = $this->repository->buscarPorId($id);

        if (!$ordemServico) {
            return false;
        }

        /*
         * OS finalizada ou cancelada não pode ser excluída.
         */
        if (
            in_array($ordemServico->status, [
                'finalizada',
                'cancelada',
            ])
        ) {
            throw ValidationException::withMessages([
                'status' =>
                    'Uma ordem de serviço finalizada ou cancelada não pode ser excluída.',
            ]);
        }

        return $this->repository->deletar($id);
    }

    private function validarRegras(
        array $dados,
        ?OrdemServico $ordemServico = null
    ): void {
        $statusAtual = $ordemServico?->status;
        $novoStatus = $dados['status'] ?? $statusAtual;

        /*
         * Uma OS finalizada ou cancelada não pode
         * ter seu status alterado.
         */
        if (
            $statusAtual &&
            in_array($statusAtual, [
                'finalizada',
                'cancelada',
            ]) &&
            $novoStatus !== $statusAtual
        ) {
            throw ValidationException::withMessages([
                'status' =>
                    'Uma ordem de serviço finalizada ou cancelada não pode ter seu status alterado.',
            ]);
        }

        /*
         * Recupera a data de fechamento.
         */
        $dataFechamento = array_key_exists(
            'data_fechamento',
            $dados
        )
            ? $dados['data_fechamento']
            : $ordemServico?->data_fechamento;

        /*
         * Uma OS finalizada precisa possuir
         * uma data de fechamento.
         */
        if ($novoStatus === 'finalizada' && !$dataFechamento) {
            throw ValidationException::withMessages([
                'data_fechamento' =>
                    'Uma ordem de serviço finalizada deve possuir data de fechamento.',
            ]);
        }

        /*
         * Uma OS que não está finalizada não pode
         * possuir data de fechamento.
         */
        if ($novoStatus !== 'finalizada' && $dataFechamento) {
            throw ValidationException::withMessages([
                'data_fechamento' =>
                    'A data de fechamento só pode ser informada quando a ordem estiver finalizada.',
            ]);
        }

        /*
         * Regras de transição de status.
         */
        if ($statusAtual && $novoStatus !== $statusAtual) {
            $transicoesPermitidas = [
                'aberta' => [
                    'em_andamento',
                    'cancelada',
                ],

                'em_andamento' => [
                    'aguardando_peca',
                    'finalizada',
                    'cancelada',
                ],

                'aguardando_peca' => [
                    'em_andamento',
                    'cancelada',
                ],

                'finalizada' => [],

                'cancelada' => [],
            ];

            if (
                !in_array(
                    $novoStatus,
                    $transicoesPermitidas[$statusAtual] ?? []
                )
            ) {
                throw ValidationException::withMessages([
                    'status' =>
                        "Não é permitido alterar o status de '{$statusAtual}' para '{$novoStatus}'.",
                ]);
            }
        }
    }
}