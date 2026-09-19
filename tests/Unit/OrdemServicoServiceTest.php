<?php

namespace Tests\Unit;

use App\Models\OrdemServico;
use App\Repositories\Interfaces\OrdemServicoRepositoryInterface;
use App\Services\OrdemServicoService;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Testes de unidade do OrdemServicoService: aqui o repositório é
 * substituído por um dublê (Mockery), então nenhum destes testes toca
 * o banco de dados — diferente de tests/Feature/OrdemServicoTest.php,
 * que exercita a stack inteira (rota -> middleware -> controller ->
 * service -> repository -> banco em memória).
 *
 * O objetivo aqui é isolar e travar exatamente a regra de negócio de
 * transição de status, sem depender de nada além do próprio Service.
 */
class OrdemServicoServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function ordemFalsa(array $atributos = []): OrdemServico
    {
        $ordem = new OrdemServico(array_merge([
            'status' => 'aberta',
            'data_abertura' => now()->toDateString(),
            'data_fechamento' => null,
        ], $atributos));

        // Simula um registro já persistido (com id), sem tocar o banco.
        $ordem->id = 1;
        $ordem->exists = true;

        return $ordem;
    }

    private function servicoComRepositorioFalso(): array
    {
        /** @var OrdemServicoRepositoryInterface&MockInterface $repositorio */
        $repositorio = Mockery::mock(OrdemServicoRepositoryInterface::class);

        return [new OrdemServicoService($repositorio), $repositorio];
    }

    public function test_criar_sempre_forca_status_aberta_mesmo_se_outro_for_enviado(): void
    {
        [$service, $repositorio] = $this->servicoComRepositorioFalso();

        $repositorio->shouldReceive('criar')
            ->once()
            ->with(Mockery::on(function (array $dados) {
                return $dados['status'] === 'aberta'
                    && $dados['data_fechamento'] === null;
            }))
            ->andReturn($this->ordemFalsa());

        // Um cliente malicioso (ou um bug no frontend) tentando criar
        // já como "finalizada" não deve conseguir: o Service reescreve
        // o status antes de chegar no repositório.
        $service->criar([
            'veiculo_id' => 1,
            'descricao' => 'Troca de óleo',
            'data_abertura' => now()->toDateString(),
            'status' => 'finalizada',
        ]);
    }

    public function test_atualizar_ordem_inexistente_retorna_null_sem_chamar_o_repositorio(): void
    {
        [$service, $repositorio] = $this->servicoComRepositorioFalso();

        $repositorio->shouldReceive('buscarPorId')->once()->with(999)->andReturn(null);

        // Não cadastro shouldReceive('atualizar') de propósito: se o
        // Service tentar chamar mesmo assim, o Mockery derruba o teste
        // sozinho com "method does not exist" — funciona como um
        // assertNeverCalled() implícito.

        $resultado = $service->atualizar(999, ['status' => 'em_andamento']);

        $this->assertNull($resultado);
    }

    public function test_transicao_de_aberta_para_finalizada_e_rejeitada(): void
    {
        [$service, $repositorio] = $this->servicoComRepositorioFalso();

        $repositorio->shouldReceive('buscarPorId')
            ->once()
            ->andReturn($this->ordemFalsa(['status' => 'aberta']));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Não é permitido alterar o status de 'aberta' para 'finalizada'.");

        $service->atualizar(1, ['status' => 'finalizada']);
    }

    public function test_transicao_de_aberta_para_em_andamento_e_aceita_e_delega_ao_repositorio(): void
    {
        [$service, $repositorio] = $this->servicoComRepositorioFalso();

        $repositorio->shouldReceive('buscarPorId')
            ->once()
            ->andReturn($this->ordemFalsa(['status' => 'aberta']));

        $repositorio->shouldReceive('atualizar')
            ->once()
            ->with(1, Mockery::on(fn (array $dados) => $dados['status'] === 'em_andamento'))
            ->andReturn($this->ordemFalsa(['status' => 'em_andamento']));

        $resultado = $service->atualizar(1, ['status' => 'em_andamento']);

        $this->assertSame('em_andamento', $resultado->status);
    }

    public function test_finalizar_preenche_data_fechamento_antes_de_persistir(): void
    {
        [$service, $repositorio] = $this->servicoComRepositorioFalso();

        $repositorio->shouldReceive('buscarPorId')
            ->once()
            ->andReturn($this->ordemFalsa(['status' => 'em_andamento']));

        $repositorio->shouldReceive('atualizar')
            ->once()
            ->with(1, Mockery::on(function (array $dados) {
                // A regra "só pode ter data_fechamento se finalizada"
                // teria barrado isso se o Service não preenchesse
                // data_fechamento ANTES de validar.
                return $dados['status'] === 'finalizada'
                    && !empty($dados['data_fechamento']);
            }))
            ->andReturn($this->ordemFalsa(['status' => 'finalizada']));

        // Repare que NÃO mandamos data_fechamento no payload de entrada
        // — quem preenche é o próprio Service.
        $service->atualizar(1, ['status' => 'finalizada']);
    }

    public function test_nao_permite_alterar_status_de_ordem_ja_finalizada(): void
    {
        [$service, $repositorio] = $this->servicoComRepositorioFalso();

        $repositorio->shouldReceive('buscarPorId')
            ->once()
            ->andReturn($this->ordemFalsa([
                'status' => 'finalizada',
                'data_fechamento' => now(),
            ]));

        $this->expectException(ValidationException::class);

        $service->atualizar(1, ['status' => 'em_andamento']);
    }

    public function test_data_fechamento_fora_da_finalizacao_e_rejeitada(): void
    {
        [$service, $repositorio] = $this->servicoComRepositorioFalso();

        $repositorio->shouldReceive('buscarPorId')
            ->once()
            ->andReturn($this->ordemFalsa(['status' => 'aberta']));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('A data de fechamento só pode ser informada quando a ordem estiver finalizada.');

        $service->atualizar(1, [
            'status' => 'em_andamento',
            'data_fechamento' => now()->toDateString(),
        ]);
    }

    public function test_remover_ordem_inexistente_retorna_false_sem_chamar_deletar(): void
    {
        [$service, $repositorio] = $this->servicoComRepositorioFalso();

        $repositorio->shouldReceive('buscarPorId')->once()->andReturn(null);

        $this->assertFalse($service->remover(999));
    }

    public function test_remover_ordem_finalizada_lanca_excecao_e_nao_chama_deletar(): void
    {
        [$service, $repositorio] = $this->servicoComRepositorioFalso();

        $repositorio->shouldReceive('buscarPorId')
            ->once()
            ->andReturn($this->ordemFalsa(['status' => 'finalizada']));

        // Sem shouldReceive('deletar'): se o Service chamar mesmo assim,
        // o teste falha sozinho.
        $this->expectException(ValidationException::class);

        $service->remover(1);
    }

    public function test_remover_ordem_aberta_delega_a_exclusao_ao_repositorio(): void
    {
        [$service, $repositorio] = $this->servicoComRepositorioFalso();

        $repositorio->shouldReceive('buscarPorId')
            ->once()
            ->andReturn($this->ordemFalsa(['status' => 'aberta']));

        $repositorio->shouldReceive('deletar')->once()->with(1)->andReturn(true);

        $this->assertTrue($service->remover(1));
    }
}
