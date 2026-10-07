<?php

namespace App\Services;

/**
 * Lê o infobox "Info/Automóvel" do texto-fonte (wikitext) de uma página da
 * Wikipédia e devolve só os campos técnicos que são confiáveis, já limpos.
 *
 * Fica de fora, de propósito: consumo/autonomia (os valores da Wikipédia são
 * inconsistentes) e qualquer coisa que não seja ficha técnica.
 */
class WikipediaInfobox
{
    /** campo do infobox (normalizado) => [chave, rótulo] */
    private const CAMPOS = [
        'producao' => ['producao', 'Produção'],
        'carroceria' => ['carroceria', 'Carroceria'],
        'classe' => ['segmento', 'Segmento'],
        'motor' => ['motores', 'Motores'],
        'potencia' => ['potencia', 'Potência'],
        'torque' => ['torque', 'Torque'],
        'caixa de velocidades' => ['cambio', 'Câmbio'],
        'transmissao' => ['cambio', 'Câmbio'],
        'comprimento' => ['comprimento', 'Comprimento'],
        'largura' => ['largura', 'Largura'],
        'altura' => ['altura', 'Altura'],
        'entre eixos' => ['entre_eixos', 'Entre-eixos'],
        'peso' => ['peso', 'Peso'],
        'tanque' => ['tanque', 'Tanque de combustível'],
        'vel max' => ['velocidade_maxima', 'Velocidade máxima'],
    ];

    /**
     * @return array<int, array{chave: string, rotulo: string, valor: string}>|null null = página sem infobox de automóvel
     */
    public function extrair(string $wikitext): ?array
    {
        $corpo = $this->corpoDoInfobox($wikitext);

        if ($corpo === null) {
            return null;
        }

        $campos = [];

        foreach ($this->parametros($corpo) as $nome => $valor) {
            $chave = self::normalizarNome($nome);

            if (!isset(self::CAMPOS[$chave])) {
                continue;
            }

            [$id, $rotulo] = self::CAMPOS[$chave];
            $limpo = $this->limpar($valor);

            // Dois nomes para o mesmo campo (câmbio): vale o primeiro preenchido.
            if ($limpo === '' || isset($campos[$id])) {
                continue;
            }

            $campos[$id] = ['chave' => $id, 'rotulo' => $rotulo, 'valor' => $limpo];
        }

        return array_values($campos);
    }

    /** "Entre eixos" / "entre_eixos" / "Transmissão" -> "entre eixos" / "transmissao". */
    public static function normalizarNome(string $nome): string
    {
        $semAcento = CatalogoPecas::normalizar($nome);

        return $semAcento;
    }

    private function corpoDoInfobox(string $texto): ?string
    {
        if (!preg_match('/\{\{\s*Info\/Autom/iu', $texto, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $inicio = $m[0][1];
        $profundidade = 0;
        $tamanho = strlen($texto);

        for ($i = $inicio; $i < $tamanho - 1; $i++) {
            $par = substr($texto, $i, 2);

            if ($par === '{{') {
                $profundidade++;
                $i++;
            } elseif ($par === '}}') {
                $profundidade--;
                $i++;

                if ($profundidade === 0) {
                    return substr($texto, $inicio + 2, $i - $inicio - 3);
                }
            }
        }

        return null;
    }

    /**
     * Parâmetros do nível principal ("| nome = valor"), sem quebrar em "|" de
     * templates ({{...}}) ou links ([[...]]) aninhados.
     *
     * @return array<string, string>
     */
    private function parametros(string $corpo): array
    {
        $partes = [];
        $atual = '';
        $chaves = 0;
        $colchetes = 0;
        $tamanho = strlen($corpo);

        for ($i = 0; $i < $tamanho; $i++) {
            $par = substr($corpo, $i, 2);

            if ($par === '{{') {
                $chaves++;
                $atual .= $par;
                $i++;
            } elseif ($par === '}}') {
                $chaves--;
                $atual .= $par;
                $i++;
            } elseif ($par === '[[') {
                $colchetes++;
                $atual .= $par;
                $i++;
            } elseif ($par === ']]') {
                $colchetes--;
                $atual .= $par;
                $i++;
            } elseif ($corpo[$i] === '|' && $chaves === 0 && $colchetes === 0) {
                $partes[] = $atual;
                $atual = '';
            } else {
                $atual .= $corpo[$i];
            }
        }

        $partes[] = $atual;
        array_shift($partes); // o nome do próprio template ("Info/Automóvel")

        $parametros = [];

        foreach ($partes as $parte) {
            if (!str_contains($parte, '=')) {
                continue;
            }

            [$nome, $valor] = explode('=', $parte, 2);
            $parametros[trim($nome)] = $valor;
        }

        return $parametros;
    }

    /** Tira a marcação do wiki e deixa texto simples. */
    private function limpar(string $valor): string
    {
        // Referências e comentários.
        $valor = preg_replace('/<ref[^>]*\/>/iu', '', $valor);
        $valor = preg_replace('/<ref[^>]*>.*?<\/ref>/isu', '', $valor);
        $valor = preg_replace('/<!--.*?-->/su', '', $valor);

        // {{converter|4163|mm|in|3}} -> "4163 mm" (só a medida original).
        $valor = preg_replace_callback('/\{\{\s*(?:converter|convert|conv)\s*\|([^|}]+)\|([^|}]+)[^}]*\}\}/iu', fn ($m) => trim($m[1]).' '.trim($m[2]), $valor);

        // Templates de formatação: fica o conteúdo. Outros templates somem.
        for ($i = 0; $i < 4; $i++) {
            $valor = preg_replace('/\{\{\s*(?:pequeno|small)\s*\|([^{}]*)\}\}/iu', '$1', $valor);
            $valor = preg_replace('/\{\{[^{}]*\}\}/u', '', $valor);
        }

        // Links: [[Arquivo:...]] some; [[A|B]] vira B; [[A]] vira A.
        $valor = preg_replace('/\[\[(?:File|Ficheiro|Arquivo|Imagem|Image):[^\]]*\]\]/iu', '', $valor);
        $valor = preg_replace('/\[\[(?:[^\]|]*\|)?([^\]]*)\]\]/u', '$1', $valor);

        $valor = preg_replace('/<br\s*\/?>/iu', '; ', $valor);
        $valor = strip_tags($valor);
        $valor = str_replace(["'''", "''", '&nbsp;', "\xC2\xA0"], ['', '', ' ', ' '], $valor);
        $valor = html_entity_decode($valor, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $valor = preg_replace('/\s+/u', ' ', $valor);
        $valor = preg_replace('/\s*;(\s*;)*\s*/u', '; ', $valor);

        return trim($valor, " ;\t\n\r");
    }
}
