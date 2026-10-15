<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;

/**
 * Lê o HTML de uma página de versão do fichacompleta.com.br e devolve a ficha
 * técnica agrupada (Motor, Dimensões, Pneus...), sem equipamentos.
 */
class FichaCompletaParser
{
    /**
     * @return array{titulo: string|null, grupos: array<int, array{grupo: string, campos: array<int, array{rotulo: string, valor: string}>}>}|null null = página sem ficha
     */
    public function extrair(string $html): ?array
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $nos = $xpath->query("//*[contains(@class,'ent-ficha-group__title') or contains(@class,'ent-spec-item')]");

        $grupos = [];
        $atual = 'Informações';

        foreach ($nos as $no) {
            if (str_contains($no->getAttribute('class'), 'ent-ficha-group__title')) {
                $atual = $this->texto($no->textContent);

                continue;
            }

            $rotulo = $xpath->query(".//*[contains(@class,'ent-spec-label')]", $no)->item(0);
            $valor = $xpath->query(".//*[contains(@class,'ent-spec-value')]", $no)->item(0);

            if (!$rotulo || !$valor) {
                continue;
            }

            $grupos[$atual][] = ['rotulo' => $this->texto($rotulo->textContent), 'valor' => $this->texto($valor->textContent)];
        }

        if (!$grupos) {
            return null;
        }

        $titulo = $xpath->query('//h1')->item(0);

        return [
            'titulo' => $titulo ? $this->texto($titulo->textContent) : null,
            'grupos' => array_map(
                fn (string $grupo, array $campos) => ['grupo' => $grupo, 'campos' => $campos],
                array_keys($grupos),
                array_values($grupos),
            ),
        ];
    }

    /**
     * Do índice de um modelo (/carros/fiat/strada/), a versão mais recente: maior
     * ano; no empate, a primeira da página.
     *
     * @return array{caminho: string, ano: int}|null
     */
    public function versaoMaisRecente(string $html, string $marcaSlug, string $modeloSlug): ?array
    {
        $padrao = '#href="(/carros/' . preg_quote($marcaSlug, '#') . '/' . preg_quote($modeloSlug, '#') . '-[a-z0-9-]*?-?(\d{4}))"#';

        if (!preg_match_all($padrao, $html, $m, PREG_SET_ORDER)) {
            return null;
        }

        $melhor = null;

        foreach ($m as $achado) {
            $ano = (int) $achado[2];

            if ($melhor === null || $ano > $melhor['ano']) {
                $melhor = ['caminho' => $achado[1], 'ano' => $ano];
            }
        }

        return $melhor;
    }

    private function texto(string $valor): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $valor));
    }
}
