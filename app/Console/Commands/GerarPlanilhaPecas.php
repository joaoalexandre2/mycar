<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Gera uma planilha CSV de trabalho com modelo e peça já preenchidos e
 * fabricante/código em branco, para alguém consultar o catálogo oficial do
 * fabricante e anotar os códigos. Depois, `pecas:importar` ignora as linhas
 * ainda em branco. Não coloca nenhum código: só a lista do que falta.
 */
class GerarPlanilhaPecas extends Command
{
    protected $signature = 'pecas:planilha
        {--saida=planilha-de-trabalho.csv : arquivo CSV de saída}
        {--modelos= : nomes do catálogo separados por | (padrão: os 10 mais vendidos)}
        {--pecas= : nomes ou ids de peças separados por | (padrão: filtros, amortecedores, bombas e coifas)}';

    protected $description = 'Gera a planilha de trabalho (modelo x peça) para preencher com códigos de um catálogo oficial.';

    /** Modelos mais vendidos (ordem de prioridade, nomes do catálogo). */
    private const MODELOS_PADRAO = [
        'Volkswagen Gol', 'Chevrolet Onix', 'Hyundai HB20', 'Fiat Argo', 'Fiat Strada',
        'Fiat Uno', 'Fiat Palio', 'Toyota Corolla', 'Honda Civic', 'Volkswagen Polo',
    ];

    /** Itens pedidos: filtros, amortecedores, coifa e bombas. */
    private const PECAS_PADRAO = [
        'filtro-de-oleo', 'filtro-de-ar-do-motor', 'filtro-de-combustivel',
        'filtro-de-cabine-ar-condicionado', 'amortecedor-dianteiro', 'amortecedor-traseiro',
        'kit-coifa-e-batente-do-amortecedor-dianteiro', 'kit-coifa-e-batente-do-amortecedor-traseiro',
        'coifa-da-homocinetica-lado-roda', 'bomba-dagua', 'bomba-de-combustivel',
    ];

    public function handle(): int
    {
        $modelosDoCatalogo = [];

        foreach (config('pecas_catalogo.modelos') as $linha) {
            $modelosDoCatalogo[Str::slug($linha[3])] = $linha[3];
        }

        $pecasDoCatalogo = [];

        foreach (config('pecas_catalogo.pecas') as $linha) {
            $pecasDoCatalogo[Str::slug($linha[1])] = $linha[1];
        }

        $modelos = $this->resolver($this->lista('modelos', self::MODELOS_PADRAO), $modelosDoCatalogo, 'modelo');
        $pecas = $this->resolver($this->lista('pecas', self::PECAS_PADRAO), $pecasDoCatalogo, 'peça');

        if ($modelos === null || $pecas === null) {
            return self::FAILURE;
        }

        $caminho = (string) $this->option('saida');
        $caminho = (str_starts_with($caminho, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $caminho)) ? $caminho : base_path($caminho);

        // BOM + ponto e vírgula: o Excel em português abre com acentos e colunas certos.
        $arquivo = fopen($caminho, 'w');
        fwrite($arquivo, "\xEF\xBB\xBF");
        fputcsv($arquivo, ['modelo', 'ano_de', 'ano_ate', 'motor', 'peca', 'fabricante', 'codigo', 'observacao'], ';');

        foreach ($modelos as $modelo) {
            foreach ($pecas as $peca) {
                fputcsv($arquivo, [$modelo, '', '', '', $peca, '', '', ''], ';');
            }
        }

        fclose($arquivo);

        $this->info(sprintf(
            'Planilha gravada em %s: %d modelo(s) x %d peça(s) = %d linha(s) em branco.',
            $caminho,
            count($modelos),
            count($pecas),
            count($modelos) * count($pecas),
        ));
        $this->line('Preencha fabricante e codigo (e ano_de, ano_ate, motor se o catálogo separar). Para outro ano ou motor, copie a linha.');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, string>  $padrao
     * @return array<int, string>
     */
    private function lista(string $opcao, array $padrao): array
    {
        $valor = trim((string) $this->option($opcao));

        return $valor === '' ? $padrao : array_values(array_filter(array_map('trim', explode('|', $valor))));
    }

    /**
     * @param  array<int, string>  $pedidos
     * @param  array<string, string>  $catalogo  slug => nome
     * @return array<int, string>|null nomes do catálogo, ou null se algum não existe
     */
    private function resolver(array $pedidos, array $catalogo, string $tipo): ?array
    {
        $nomes = [];

        foreach ($pedidos as $pedido) {
            $slug = Str::slug($pedido);

            if (!isset($catalogo[$slug])) {
                $this->error("{$tipo} \"{$pedido}\" não está no catálogo.");

                return null;
            }

            $nomes[] = $catalogo[$slug];
        }

        return $nomes;
    }
}
