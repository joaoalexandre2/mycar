<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Coleta uma foto de cada modelo do catálogo (config/pecas_catalogo) na
 * Wikipédia/Wikimedia Commons e grava em database/data/imagens_modelos/ junto
 * do arquivo database/data/imagens_modelos.json, com autor, licença e link de
 * cada foto. Rodar na máquina de desenvolvimento e revisar antes de commitar:
 * em produção o app só LÊ esses arquivos, nunca raspa.
 *
 * Só entram fotos de licença livre (CC BY, CC BY-SA, CC0, domínio público) que
 * estejam no Commons. Imagem "uso justo" da Wikipédia é ignorada. O autor e a
 * licença precisam aparecer junto da foto no app (exigência das licenças CC).
 *
 * Boas práticas: User-Agent identificado, uma requisição por vez e pausa entre
 * elas, via API oficial.
 */
class RasparImagensModelos extends Command
{
    protected $signature = 'modelos:raspar-imagens
        {--apenas= : só estes modelos (nomes do catálogo separados por |)}
        {--pausa=1000 : pausa entre requisições, em milissegundos}
        {--largura=500 : largura da miniatura baixada (a Wikimedia só serve alguns tamanhos: 330, 500, 960...)}
        {--refazer : baixa de novo mesmo quem já tem foto}';

    protected $description = 'Coleta uma foto livre de cada modelo na Wikimedia Commons (rodar em desenvolvimento).';

    private const WIKIPEDIA = 'https://pt.wikipedia.org/w/api.php';
    private const COMMONS = 'https://commons.wikimedia.org/w/api.php';
    private const USER_AGENT = 'MyCarBot/1.0 (https://mycar.joaokirst.com.br; joaoalekirst@gmail.com)';

    /** Licenças aceitas (comparadas em minúsculas, sem espaços duplicados). */
    private const LICENCAS_LIVRES = ['cc by', 'cc-by', 'cc by-sa', 'cc-by-sa', 'cc0', 'public domain', 'pd', 'domínio público'];

    public function handle(): int
    {
        $pasta = base_path('database/data/imagens_modelos');
        $json = base_path('database/data/imagens_modelos.json');

        if (!is_dir($pasta)) {
            mkdir($pasta, 0775, true);
        }

        $atual = is_file($json) ? (json_decode((string) file_get_contents($json), true) ?: []) : [];
        $modelos = collect(config('pecas_catalogo.modelos'))->pluck(3)->unique()->values();

        if ($apenas = $this->option('apenas')) {
            $modelos = $modelos->filter(fn ($m) => in_array($m, explode('|', $apenas), true))->values();
        }

        $titulos = config('pecas_catalogo.wikipedia_imagem', []) + config('pecas_catalogo.wikipedia', []);
        $pausa = max(0, (int) $this->option('pausa'));
        $largura = max(200, (int) $this->option('largura'));
        $ok = $pulados = $semFoto = $licencaNaoLivre = $erros = 0;

        foreach ($modelos as $indice => $nome) {
            if (!$this->option('refazer') && isset($atual[$nome]) && is_file("{$pasta}/{$atual[$nome]['arquivo']}")) {
                $pulados++;

                continue;
            }

            if ($indice > 0) {
                usleep($pausa * 1000);
            }

            try {
                $arquivoNaWiki = $this->arquivoPrincipal($titulos[$nome] ?? $nome);

                if ($arquivoNaWiki === null) {
                    $this->warn("[sem foto]  {$nome}");
                    $semFoto++;
                    unset($atual[$nome]);

                    continue;
                }

                usleep($pausa * 1000);
                $info = $this->infoDoArquivo($arquivoNaWiki, $largura);

                if ($info === null) {
                    $this->warn("[fora do Commons] {$nome} ({$arquivoNaWiki})");
                    $semFoto++;
                    unset($atual[$nome]);

                    continue;
                }

                if (!$this->licencaLivre($info['licenca'])) {
                    $this->warn("[licença] {$nome}: {$info['licenca']}");
                    $licencaNaoLivre++;
                    unset($atual[$nome]);

                    continue;
                }

                usleep($pausa * 1000);
                $binario = Http::withHeaders(['User-Agent' => self::USER_AGENT])->timeout(40)->get($info['miniatura']);

                if (!$binario->successful() || strlen($binario->body()) < 2000) {
                    throw new \RuntimeException('download falhou ('.$binario->status().')');
                }

                $extensao = Str::lower(pathinfo(parse_url($info['miniatura'], PHP_URL_PATH) ?: '', PATHINFO_EXTENSION)) ?: 'jpg';
                $extensao = in_array($extensao, ['jpg', 'jpeg', 'png', 'webp'], true) ? $extensao : 'jpg';
                $slug = Str::slug($nome);
                $arquivo = "{$slug}.{$extensao}";

                file_put_contents("{$pasta}/{$arquivo}", $binario->body());

                $atual[$nome] = [
                    'slug' => $slug,
                    'arquivo' => $arquivo,
                    'autor' => $info['autor'],
                    'licenca' => $info['licenca'],
                    'licenca_url' => $info['licenca_url'],
                    'pagina' => $info['pagina'],
                    'fonte' => 'Wikimedia Commons',
                    'coletado_em' => now()->toDateString(),
                ];

                $this->line(sprintf('[ok]        %-28s %s · %s', $nome, $info['licenca'], mb_strimwidth($info['autor'], 0, 30, '…')));
                $ok++;
            } catch (\Throwable $e) {
                $this->warn("[erro]      {$nome}: {$e->getMessage()}");
                $erros++;
            }
        }

        ksort($atual);
        file_put_contents($json, json_encode($atual, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);

        $this->info("Concluído: {$ok} novas, {$pulados} já existentes, {$semFoto} sem foto, {$licencaNaoLivre} com licença não livre, {$erros} com erro.");

        return self::SUCCESS;
    }

    /** Nome do arquivo da imagem principal da página (a do infobox). */
    private function arquivoPrincipal(string $titulo): ?string
    {
        $resposta = Http::withHeaders(['User-Agent' => self::USER_AGENT])->timeout(25)->get(self::WIKIPEDIA, [
            'action' => 'query',
            'titles' => $titulo,
            'prop' => 'pageimages',
            'piprop' => 'name',
            'redirects' => 1,
            'format' => 'json',
            'formatversion' => 2,
        ]);

        $arquivo = $resposta->json('query.pages.0.pageimage');

        // Logotipo e desenho vetorial não servem de foto do carro.
        return $arquivo && !preg_match('/logo|\.svg$/i', $arquivo) ? $arquivo : null;
    }

    /**
     * @return array{miniatura: string, autor: string, licenca: string, licenca_url: ?string, pagina: string}|null
     */
    private function infoDoArquivo(string $arquivo, int $largura): ?array
    {
        $resposta = Http::withHeaders(['User-Agent' => self::USER_AGENT])->timeout(25)->get(self::COMMONS, [
            'action' => 'query',
            'titles' => 'File:'.$arquivo,
            'prop' => 'imageinfo',
            'iiprop' => 'url|extmetadata|mime',
            'iiurlwidth' => $largura,
            'format' => 'json',
            'formatversion' => 2,
        ]);

        $pagina = $resposta->json('query.pages.0');
        $info = $pagina['imageinfo'][0] ?? null;

        if (!$info || ($pagina['missing'] ?? false)) {
            return null;
        }

        if (!str_starts_with((string) ($info['mime'] ?? ''), 'image/')) {
            return null;
        }

        $meta = $info['extmetadata'] ?? [];
        $limpar = fn (?string $html) => trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return [
            // A Wikimedia só gera miniaturas em tamanhos padrão (330, 500, 960...): se
            // a largura pedida não for um deles, a API devolve o próximo maior.
            'miniatura' => (string) ($info['thumburl'] ?? $info['url']),
            'autor' => $limpar($meta['Artist']['value'] ?? '') ?: 'Autor não informado',
            'licenca' => $limpar($meta['LicenseShortName']['value'] ?? '') ?: 'Licença não informada',
            'licenca_url' => $meta['LicenseUrl']['value'] ?? null,
            'pagina' => (string) ($info['descriptionurl'] ?? 'https://commons.wikimedia.org/wiki/File:'.str_replace(' ', '_', $arquivo)),
        ];
    }

    private function licencaLivre(string $licenca): bool
    {
        $texto = Str::lower(preg_replace('/\s+/', ' ', $licenca));

        // "CC BY-SA 4.0", "CC BY 2.0", "CC0", "Public domain"...
        foreach (self::LICENCAS_LIVRES as $aceita) {
            if (str_starts_with($texto, $aceita)) {
                return true;
            }
        }

        return false;
    }
}
