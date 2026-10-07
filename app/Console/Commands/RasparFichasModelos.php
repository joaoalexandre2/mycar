<?php

namespace App\Console\Commands;

use App\Services\WikipediaInfobox;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Coleta a ficha técnica dos principais modelos (os de config/pecas_catalogo)
 * no infobox da Wikipédia e grava em database/data/fichas_modelos.json, com a
 * fonte, a revisão da página e a data de cada coleta. Rodar na máquina de
 * desenvolvimento e revisar o arquivo antes de commitar: em produção o app só
 * LÊ esse arquivo, nunca raspa.
 *
 * Boas práticas: User-Agent identificado, uma requisição por vez e pausa
 * entre elas, via API oficial da Wikipédia (não a página HTML).
 * Licença do conteúdo: CC BY-SA 4.0 (citar a fonte).
 */
class RasparFichasModelos extends Command
{
    protected $signature = 'modelos:raspar-fichas
        {--apenas= : só estes modelos (nomes do catálogo separados por |)}
        {--pausa=1200 : pausa entre requisições, em milissegundos}
        {--saida=database/data/fichas_modelos.json : arquivo de saída}';

    protected $description = 'Coleta a ficha técnica dos principais modelos na Wikipédia (rodar em desenvolvimento).';

    private const API = 'https://pt.wikipedia.org/w/api.php';

    public function handle(WikipediaInfobox $infobox): int
    {
        $saida = base_path((string) $this->option('saida'));
        $atual = is_file($saida) ? (json_decode((string) file_get_contents($saida), true) ?: []) : [];

        $modelos = collect(config('pecas_catalogo.modelos'))->pluck(3)->unique()->values();

        if ($apenas = $this->option('apenas')) {
            $modelos = $modelos->filter(fn ($m) => in_array($m, explode('|', $apenas), true))->values();
        }

        $titulos = config('pecas_catalogo.wikipedia', []);
        $pausa = max(0, (int) $this->option('pausa'));
        $ok = $semInfobox = $erros = 0;

        foreach ($modelos as $indice => $nome) {
            $titulo = $titulos[$nome] ?? $nome;

            if ($indice > 0) {
                usleep($pausa * 1000);
            }

            try {
                $resposta = Http::withHeaders(['User-Agent' => 'MyCarBot/1.0 (https://mycar.joaokirst.com.br; joaoalekirst@gmail.com)'])
                    ->timeout(25)
                    ->get(self::API, [
                        'action' => 'parse',
                        'page' => $titulo,
                        'prop' => 'wikitext|revid',
                        'redirects' => 1,
                        'format' => 'json',
                        'formatversion' => 2,
                    ]);
            } catch (\Throwable $e) {
                $this->warn("[erro]    {$nome}: {$e->getMessage()}");
                $erros++;

                continue;
            }

            $dados = $resposta->json('parse');

            if (!$dados) {
                $this->warn("[sem página] {$nome} (título tentado: {$titulo})");
                $semInfobox++;
                unset($atual[$nome]);

                continue;
            }

            $campos = $infobox->extrair((string) ($dados['wikitext'] ?? ''));

            if (!$campos) {
                $this->warn("[sem infobox] {$nome} -> {$dados['title']}");
                $semInfobox++;
                unset($atual[$nome]);

                continue;
            }

            $atual[$nome] = [
                'fonte' => 'Wikipédia',
                'pagina' => $dados['title'],
                'url' => 'https://pt.wikipedia.org/wiki/'.str_replace(' ', '_', $dados['title']),
                'revisao' => $dados['revid'] ?? null,
                'licenca' => 'CC BY-SA 4.0',
                'coletado_em' => now()->toDateString(),
                'campos' => $campos,
            ];

            $this->line(sprintf('[ok]      %-28s %2d campos', $nome, count($campos)));
            $ok++;
        }

        ksort($atual);
        file_put_contents($saida, json_encode($atual, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);

        $this->info("Concluído: {$ok} com ficha, {$semInfobox} sem página/infobox, {$erros} com erro. Arquivo: {$saida}");

        return self::SUCCESS;
    }
}
