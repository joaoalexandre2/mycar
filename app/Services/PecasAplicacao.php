<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Códigos de peça por modelo, ano e motor, lidos de
 * database/data/pecas_aplicacao/{modelo}.json (um arquivo por modelo do
 * catálogo, gerado por `php artisan pecas:importar` a partir de planilhas de
 * catálogos de aplicação dos fabricantes). O app só LÊ os arquivos: nenhuma
 * coleta acontece em produção e nenhum código é inventado. Sem arquivo, sem
 * código.
 */
class PecasAplicacao
{
    /** @var array<string, array<int, array<string, mixed>>> */
    private array $modelos = [];

    public function __construct(private readonly ?string $pasta = null) {}

    /**
     * Códigos que servem ao veículo, agrupados pelo id da peça do catálogo.
     * Ano e motor desconhecidos não filtram. Uma aplicação sem ano ou sem motor
     * vale para qualquer ano ou motor.
     *
     * @param  string  $nomeDoModelo  nome do modelo no catálogo (ex.: "Volkswagen Gol")
     * @param  string|null  $motor  texto livre do veículo (ex.: "Gol 1.0 City (Flex)")
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function paraModelo(string $nomeDoModelo, ?int $ano = null, ?string $motor = null): array
    {
        $tokensDoVeiculo = $motor ? array_filter(explode(' ', CatalogoPecas::normalizar($motor))) : [];
        $resultado = [];

        foreach ($this->aplicacoesDo($nomeDoModelo) as $a) {
            if ($ano !== null && !$this->anoServe($a, $ano)) {
                continue;
            }

            if ($tokensDoVeiculo && !$this->motorServe($a['motor'] ?? null, $tokensDoVeiculo)) {
                continue;
            }

            $resultado[$a['peca']][] = [
                'fabricante' => $a['fabricante'],
                'codigo' => $a['codigo'],
                'ano_de' => $a['ano_de'] ?? null,
                'ano_ate' => $a['ano_ate'] ?? null,
                'motor' => $a['motor'] ?? null,
                'observacao' => $a['observacao'] ?? null,
                'fonte' => $a['fonte'] ?? null,
            ];
        }

        return $resultado;
    }

    /** Quantas aplicações há para o modelo (0 = sem arquivo). */
    public function total(string $nomeDoModelo): int
    {
        return count($this->aplicacoesDo($nomeDoModelo));
    }

    /** Nome do arquivo do modelo, também usado pelo importador. */
    public static function arquivoDo(string $nomeDoModelo): string
    {
        return Str::slug($nomeDoModelo) . '.json';
    }

    public function pasta(): string
    {
        return rtrim($this->pasta ?? base_path('database/data/pecas_aplicacao'), '/\\');
    }

    /** @return array<int, array<string, mixed>> */
    private function aplicacoesDo(string $nomeDoModelo): array
    {
        if (!isset($this->modelos[$nomeDoModelo])) {
            $arquivo = $this->pasta() . '/' . self::arquivoDo($nomeDoModelo);
            $conteudo = is_file($arquivo) ? file_get_contents($arquivo) : false;
            $dados = $conteudo ? (json_decode($conteudo, true) ?: []) : [];

            $this->modelos[$nomeDoModelo] = $dados['aplicacoes'] ?? [];
        }

        return $this->modelos[$nomeDoModelo];
    }

    /** @param  array<string, mixed>  $aplicacao */
    private function anoServe(array $aplicacao, int $ano): bool
    {
        $de = $aplicacao['ano_de'] ?? null;
        $ate = $aplicacao['ano_ate'] ?? null;

        return ($de === null || $ano >= $de) && ($ate === null || $ano <= $ate);
    }

    /**
     * "1.0" serve a "Gol 1.0 City": todas as palavras do motor da aplicação
     * têm de aparecer no texto do veículo.
     *
     * @param  array<int, string>  $tokensDoVeiculo
     */
    private function motorServe(?string $motorDaAplicacao, array $tokensDoVeiculo): bool
    {
        if ($motorDaAplicacao === null || trim($motorDaAplicacao) === '') {
            return true;
        }

        $exigidos = array_filter(explode(' ', CatalogoPecas::normalizar($motorDaAplicacao)));

        return !array_diff($exigidos, $tokensDoVeiculo);
    }
}
