<?php

namespace App\Console\Commands;

use App\Services\CatalogoPecas;
use App\Services\PecasAplicacao;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Importa uma planilha CSV de catálogo de aplicação (modelo, ano, motor, peça,
 * fabricante, código) para database/data/pecas_aplicacao/{modelo}.json.
 *
 * Colunas (cabeçalho obrigatório, `;` ou `,`):
 *   modelo;ano_de;ano_ate;motor;peca;fabricante;codigo;observacao
 * "modelo" é o nome do catálogo (ex.: Volkswagen Gol) e "peca" o nome ou o id
 * do catálogo (ex.: Filtro de óleo). ano_de, ano_ate, motor e observacao são
 * opcionais por linha. A fonte (ex.: "Catálogo Tecfil 2025") é obrigatória e
 * fica gravada em cada código. Tudo ou nada: com qualquer linha inválida, nada
 * é gravado.
 */
class ImportarPecasAplicacao extends Command
{
    protected $signature = 'pecas:importar
        {arquivo : planilha CSV}
        {--fonte= : de onde vêm os códigos (obrigatório)}
        {--pasta=database/data/pecas_aplicacao : pasta de saída}
        {--simular : só valida e mostra o resumo, sem gravar}';

    protected $description = 'Importa uma planilha de catálogo de aplicação de peças para os arquivos do app.';

    private const COLUNAS = ['modelo', 'ano_de', 'ano_ate', 'motor', 'peca', 'fabricante', 'codigo', 'observacao'];

    private const OBRIGATORIAS = ['modelo', 'peca', 'fabricante', 'codigo'];

    public function handle(): int
    {
        $fonte = trim((string) $this->option('fonte'));

        if ($fonte === '') {
            $this->error('Informe a fonte com --fonte="Catálogo Tecfil 2025" (de onde vêm os códigos).');

            return self::FAILURE;
        }

        $caminho = (string) $this->argument('arquivo');

        if (!is_file($caminho)) {
            $this->error("Arquivo não encontrado: {$caminho}");

            return self::FAILURE;
        }

        [$linhas, $erros, $emBranco] = $this->ler($caminho, $fonte);

        if ($erros) {
            $this->error(count($erros) . ' problema(s) na planilha. Nada foi gravado:');

            foreach (array_slice($erros, 0, 30) as $erro) {
                $this->line("  - {$erro}");
            }

            if (count($erros) > 30) {
                $this->line('  ... e mais ' . (count($erros) - 30) . '.');
            }

            return self::FAILURE;
        }

        $pasta = str_starts_with((string) $this->option('pasta'), '/') || preg_match('#^[A-Za-z]:[\\\\/]#', (string) $this->option('pasta'))
            ? (string) $this->option('pasta')
            : base_path((string) $this->option('pasta'));
        $servico = new PecasAplicacao($pasta);
        $porModelo = [];

        foreach ($linhas as $linha) {
            $porModelo[$linha['modelo']][] = $linha['aplicacao'];
        }

        $novas = $repetidas = 0;

        foreach ($porModelo as $modelo => $aplicacoes) {
            $arquivo = $servico->pasta() . '/' . PecasAplicacao::arquivoDo($modelo);
            $atual = is_file($arquivo) ? (json_decode((string) file_get_contents($arquivo), true) ?: []) : [];
            $existentes = $atual['aplicacoes'] ?? [];
            $chaves = array_flip(array_map(fn (array $a) => $this->chave($a), $existentes));

            foreach ($aplicacoes as $a) {
                if (isset($chaves[$this->chave($a)])) {
                    $repetidas++;

                    continue;
                }

                $existentes[] = $a;
                $chaves[$this->chave($a)] = true;
                $novas++;
            }

            usort($existentes, fn ($x, $y) => [$x['peca'], $x['ano_de'] ?? 0, $x['fabricante'], $x['codigo']]
                <=> [$y['peca'], $y['ano_de'] ?? 0, $y['fabricante'], $y['codigo']]);

            if (!$this->option('simular')) {
                if (!is_dir($servico->pasta())) {
                    mkdir($servico->pasta(), 0775, true);
                }

                file_put_contents($arquivo, json_encode(
                    ['modelo' => $modelo, 'aplicacoes' => $existentes],
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                ) . "\n");
            }

            $this->line(sprintf('  %-32s %d código(s)', $modelo, count($aplicacoes)));
        }

        $this->info(sprintf(
            '%s: %d linha(s), %d nova(s), %d já existia(m), %d modelo(s).',
            $this->option('simular') ? 'Simulação (nada gravado)' : 'Importado',
            count($linhas),
            $novas,
            $repetidas,
            count($porModelo),
        ));

        if ($emBranco > 0) {
            $this->line("{$emBranco} linha(s) sem fabricante e sem código foram ignoradas (planilha ainda por preencher).");
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0: array<int, array{modelo: string, aplicacao: array<string, mixed>}>, 1: array<int, string>, 2: int}
     *                                                                                                                     linhas válidas, problemas e quantas linhas em branco foram ignoradas
     */
    private function ler(string $caminho, string $fonte): array
    {
        $conteudo = (string) file_get_contents($caminho);
        $conteudo = preg_replace('/^\xEF\xBB\xBF/', '', $conteudo);
        $primeira = strtok($conteudo, "\r\n") ?: '';
        $separador = substr_count($primeira, ';') >= substr_count($primeira, ',') ? ';' : ',';

        $modelosDoCatalogo = [];

        foreach (config('pecas_catalogo.modelos') as $linha) {
            $modelosDoCatalogo[Str::slug($linha[3])] = $linha[3];
        }

        $pecasDoCatalogo = [];

        foreach (config('pecas_catalogo.pecas') as $linha) {
            $pecasDoCatalogo[Str::slug($linha[1])] = Str::slug($linha[1]);
        }

        $arquivo = fopen('php://memory', 'r+');
        fwrite($arquivo, $conteudo);
        rewind($arquivo);

        $cabecalho = array_map(fn ($c) => Str::slug((string) $c, '_'), (array) fgetcsv($arquivo, 0, $separador));
        $erros = [];

        foreach (self::OBRIGATORIAS as $coluna) {
            if (!in_array($coluna, $cabecalho, true)) {
                $erros[] = "Coluna obrigatória ausente no cabeçalho: {$coluna} (esperado: " . implode(';', self::COLUNAS) . ')';
            }
        }

        if ($erros) {
            return [[], $erros, 0];
        }

        $linhas = [];
        $emBranco = 0;
        $numero = 1;
        $anoMax = (int) date('Y') + 1;

        while (($campos = fgetcsv($arquivo, 0, $separador)) !== false) {
            $numero++;

            if ($campos === [null] || !array_filter($campos, fn ($c) => trim((string) $c) !== '')) {
                continue;
            }

            $d = array_combine($cabecalho, array_pad(array_map('trim', $campos), count($cabecalho), ''));

            // Planilha de trabalho: linha com modelo e peça, mas ainda sem fabricante nem código.
            if (($d['fabricante'] ?? '') === '' && ($d['codigo'] ?? '') === '') {
                $emBranco++;

                continue;
            }

            $problemas = [];

            $modeloSlug = Str::slug($d['modelo']);
            $pecaSlug = Str::slug($d['peca']);
            $modelo = $modelosDoCatalogo[$modeloSlug] ?? null;

            if (!$modelo) {
                $problemas[] = "modelo \"{$d['modelo']}\" não está no catálogo";
            }

            if (!isset($pecasDoCatalogo[$pecaSlug])) {
                $problemas[] = "peça \"{$d['peca']}\" não está no catálogo";
            }

            foreach (['fabricante' => 60, 'codigo' => 60, 'observacao' => 255, 'motor' => 60] as $campo => $max) {
                $valor = $d[$campo] ?? '';

                if (in_array($campo, ['fabricante', 'codigo'], true) && $valor === '') {
                    $problemas[] = "{$campo} vazio";
                } elseif (mb_strlen($valor) > $max) {
                    $problemas[] = "{$campo} com mais de {$max} caracteres";
                }
            }

            $de = $this->ano($d['ano_de'] ?? '', $anoMax, 'ano_de', $problemas);
            $ate = $this->ano($d['ano_ate'] ?? '', $anoMax, 'ano_ate', $problemas);

            if ($de !== null && $ate !== null && $de > $ate) {
                $problemas[] = 'ano_de maior que ano_ate';
            }

            if ($problemas) {
                $erros[] = "linha {$numero}: " . implode('; ', $problemas);

                continue;
            }

            $linhas[] = [
                'modelo' => $modelo,
                'aplicacao' => [
                    'peca' => $pecaSlug,
                    'fabricante' => $d['fabricante'],
                    'codigo' => $d['codigo'],
                    'ano_de' => $de,
                    'ano_ate' => $ate,
                    'motor' => ($d['motor'] ?? '') !== '' ? $d['motor'] : null,
                    'observacao' => ($d['observacao'] ?? '') !== '' ? $d['observacao'] : null,
                    'fonte' => $fonte,
                ],
            ];
        }

        fclose($arquivo);

        return [$linhas, $erros, $emBranco];
    }

    /** @param  array<int, string>  $problemas */
    private function ano(string $valor, int $max, string $campo, array &$problemas): ?int
    {
        if ($valor === '') {
            return null;
        }

        if (!ctype_digit($valor) || (int) $valor < 1980 || (int) $valor > $max) {
            $problemas[] = "{$campo} \"{$valor}\" inválido (use um ano entre 1980 e {$max})";

            return null;
        }

        return (int) $valor;
    }

    /** @param  array<string, mixed>  $a */
    private function chave(array $a): string
    {
        return implode('|', [
            $a['peca'], CatalogoPecas::normalizar($a['fabricante']), mb_strtolower((string) $a['codigo']),
            $a['ano_de'] ?? '', $a['ano_ate'] ?? '', CatalogoPecas::normalizar((string) ($a['motor'] ?? '')),
        ]);
    }
}
