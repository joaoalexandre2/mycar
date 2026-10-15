<?php

namespace App\Console\Commands;

use App\Services\FichaCompletaParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Coleta a ficha técnica de referência (versão mais recente) de TODOS os
 * modelos do fichacompleta.com.br e grava um arquivo por marca em
 * database/data/fichas_catalogo/{marca}.json. Rodar na máquina de
 * desenvolvimento e revisar antes de commitar: em produção o app só LÊ esses
 * arquivos, nunca raspa.
 *
 * Retomável: modelos já coletados são pulados (use --refazer para atualizar).
 *
 * Boas práticas: User-Agent identificado (MyCarBot), uma requisição por vez,
 * pausa entre elas e nada de contornar bloqueio: se o site responder 403 ou
 * pedir captcha, o modelo é pulado.
 *
 * Os dados são de terceiros: o uso comercial depende dos termos do site.
 */
class RasparFichaCompleta extends Command
{
    protected $signature = 'modelos:raspar-fichacompleta
        {--marca= : só estas marcas (slugs separados por |, ex.: fiat|ford)}
        {--pausa=1500 : pausa entre requisições, em milissegundos}
        {--refazer : recoleta modelos que já estão nos arquivos}
        {--pasta=database/data/fichas_catalogo : pasta de saída}';

    protected $description = 'Coleta a ficha técnica de todos os modelos no fichacompleta.com.br (rodar em desenvolvimento).';

    private const BASE = 'https://www.fichacompleta.com.br';

    private const USER_AGENT = 'MyCarBot/1.0 (https://mycar.joaokirst.com.br; joaoalekirst@gmail.com)';

    private const IGNORAR = ['marcas', 'comparativo'];

    private int $pausa = 1500;

    public function handle(FichaCompletaParser $parser): int
    {
        $this->pausa = max(0, (int) $this->option('pausa'));
        $pasta = base_path((string) $this->option('pasta'));

        if (!is_dir($pasta)) {
            mkdir($pasta, 0775, true);
        }

        $marcas = $this->slugs(self::BASE . '/carros/marcas/', '#href="/carros/([a-z0-9-]+)/"#');
        $marcas = array_values(array_diff($marcas, self::IGNORAR));

        if ($filtro = $this->option('marca')) {
            $marcas = array_values(array_intersect($marcas, explode('|', $filtro)));
        }

        if (!$marcas) {
            $this->error('Nenhuma marca encontrada (site fora do ar ou bloqueando).');

            return self::FAILURE;
        }

        $ok = $semFicha = $erros = 0;

        foreach ($marcas as $marca) {
            $arquivo = "{$pasta}/{$marca}.json";
            $atual = is_file($arquivo) ? (json_decode((string) file_get_contents($arquivo), true) ?: []) : [];

            $modelos = $this->slugs(self::BASE . "/carros/{$marca}/", '#href="/carros/' . preg_quote($marca, '#') . '/([a-z0-9-]+)/"#');
            $this->line("== {$marca}: " . count($modelos) . ' modelos');

            foreach ($modelos as $modelo) {
                if (isset($atual[$modelo]) && !$this->option('refazer')) {
                    continue;
                }

                try {
                    $resultado = $this->coletar($parser, $marca, $modelo);
                } catch (\Throwable $e) {
                    $this->warn("[erro]    {$marca}/{$modelo}: {$e->getMessage()}");
                    $erros++;

                    continue;
                }

                if ($resultado === null) {
                    $this->warn("[sem ficha] {$marca}/{$modelo}");
                    $semFicha++;

                    continue;
                }

                $atual[$modelo] = $resultado;
                $ok++;
                $this->line(sprintf('[ok]      %-40s %s', "{$marca}/{$modelo}", $resultado['versao']));

                // Grava a cada modelo: se parar no meio, retoma de onde parou.
                $this->salvar($arquivo, $atual);
            }

            if ($atual) {
                $this->salvar($arquivo, $atual);
            }
        }

        $this->info("Concluído: {$ok} novos, {$semFicha} sem ficha, {$erros} com erro. Pasta: {$pasta}");

        return self::SUCCESS;
    }

    /** @return array<string, mixed>|null */
    private function coletar(FichaCompletaParser $parser, string $marca, string $modelo): ?array
    {
        $indice = $this->baixar("/carros/{$marca}/{$modelo}/");
        $versao = $indice ? $parser->versaoMaisRecente($indice, $marca, $modelo) : null;

        if (!$versao) {
            return null;
        }

        $ficha = $parser->extrair((string) $this->baixar($versao['caminho']));

        if (!$ficha) {
            return null;
        }

        return [
            'fonte' => 'Ficha Completa',
            'url' => self::BASE . $versao['caminho'],
            'versao' => $ficha['titulo'],
            'ano' => $versao['ano'],
            'coletado_em' => now()->toDateString(),
            'grupos' => $ficha['grupos'],
        ];
    }

    /** @return array<int, string> */
    private function slugs(string $url, string $padrao): array
    {
        $html = $this->baixar(substr($url, strlen(self::BASE)));

        if (!$html || !preg_match_all($padrao, $html, $m)) {
            return [];
        }

        return array_values(array_diff(array_unique($m[1]), self::IGNORAR));
    }

    private function baixar(string $caminho): ?string
    {
        usleep($this->pausa * 1000);

        $resposta = Http::withHeaders(['User-Agent' => self::USER_AGENT])
            ->timeout(25)
            ->get(self::BASE . $caminho);

        if (!$resposta->successful()) {
            return null;
        }

        $corpo = $resposta->body();

        // Se pedirem captcha, não insistimos.
        return str_contains(mb_strtolower($corpo), 'captcha') ? null : $corpo;
    }

    /** @param array<string, mixed> $dados */
    private function salvar(string $arquivo, array $dados): void
    {
        ksort($dados);
        file_put_contents($arquivo, json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    }
}
