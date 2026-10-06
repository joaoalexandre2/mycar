<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Catálogo de TIPOS de peça por modelo (dados em config/pecas_catalogo.php).
 * Não traz código de fabricante nem compatibilidade por ano/motor: ver o
 * aviso no config.
 */
class CatalogoPecas
{
    public const AVISO = 'Mostramos os tipos de peça do seu modelo, não códigos de fabricante. A compatibilidade exata muda por ano, motor e versão: antes de comprar, confirme pelo chassi ou pelo código da peça original.';

    /** @return array<int, string> ids (slug do nome) de todas as peças do catálogo */
    public static function idsDasPecas(): array
    {
        return array_map(fn (array $p) => Str::slug($p[1]), config('pecas_catalogo.pecas'));
    }

    /** "Corolla XEi 2.0" ->"corolla xei 2 0" (sem acento, só letras e números). */
    public static function normalizar(?string $texto): string
    {
        $limpo = preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii((string) $texto)));

        return trim((string) $limpo);
    }

    /**
     * Acha o modelo do catálogo pelo nome do veículo (o mais específico vence).
     *
     * @return array{nome: string, categoria: string, categoria_rotulo: string}|null
     */
    public function modeloDoVeiculo(?string $marca, ?string $modelo): ?array
    {
        $marcaTokens = explode(' ', self::normalizar($marca));
        $modeloTokens = explode(' ', self::normalizar($modelo));
        $melhor = null;
        $melhorPeso = 0;

        foreach (config('pecas_catalogo.modelos') as [$marcas, $palavras, $categoria, $nome]) {
            if (!array_intersect(explode('|', $marcas), $marcaTokens)) {
                continue;
            }

            $exigidas = explode(' ', $palavras);

            foreach ($exigidas as $palavra) {
                if (!$this->temPalavra($palavra, $modeloTokens)) {
                    continue 2;
                }
            }

            if (count($exigidas) > $melhorPeso) {
                $melhorPeso = count($exigidas);
                $melhor = [
                    'nome' => $nome,
                    'categoria' => $categoria,
                    'categoria_rotulo' => config("pecas_catalogo.categorias.{$categoria}"),
                    // Marca da peça original da montadora (ex.: ACDelco para a Chevrolet).
                    'original' => config('pecas_catalogo.originais.'.explode('|', $marcas)[0]),
                ];
            }
        }

        return $melhor;
    }

    /**
     * "320*" casa qualquer palavra que comece com 320 (320i, 320ia...);
     * sem asterisco, a palavra tem que ser igual.
     *
     * @param array<int, string> $tokens
     */
    private function temPalavra(string $palavra, array $tokens): bool
    {
        if (str_ends_with($palavra, '*')) {
            $prefixo = rtrim($palavra, '*');

            foreach ($tokens as $token) {
                if (str_starts_with($token, $prefixo)) {
                    return true;
                }
            }

            return false;
        }

        return in_array($palavra, $tokens, true);
    }

    /** Marcas de reposição comuns da peça (lista vazia se não houver). */
    private function marcasDa(string $nome): array
    {
        static $mapa = null;

        if ($mapa === null) {
            $mapa = [];

            foreach (config('pecas_catalogo.marcas') as [$nomes, $marcas]) {
                foreach ($nomes as $n) {
                    $mapa[$n] = $marcas;
                }
            }
        }

        return $mapa[$nome] ?? [];
    }

    /**
     * @param string|null $categoria null = catálogo inteiro; valor = só as peças que valem para ela;
     *                               'universal' = só as peças comuns a qualquer carro
     * @return array<int, array<string, mixed>>
     */
    public function pecas(?string $categoria, string $busca = '', ?string $sistema = null): array
    {
        $tokens = array_filter(explode(' ', self::normalizar($busca)));
        $resultado = [];

        foreach (config('pecas_catalogo.pecas') as [$sis, $nome, $termos, $posicao, $km, $obs, $categorias]) {
            if ($categoria === 'universal' && $categorias !== null) {
                continue;
            }

            if ($categoria !== null && $categoria !== 'universal' && $categorias !== null && !in_array($categoria, $categorias, true)) {
                continue;
            }

            if ($sistema !== null && $sistema !== '' && $sis !== $sistema) {
                continue;
            }

            $pontos = $this->pontuar($tokens, $nome, $termos, config("pecas_catalogo.sistemas.{$sis}"));

            if ($pontos === 0) {
                continue;
            }

            $resultado[] = [
                'id' => Str::slug($nome),
                'sistema' => $sis,
                'sistema_rotulo' => config("pecas_catalogo.sistemas.{$sis}"),
                'nome' => $nome,
                'posicao' => $posicao,
                'intervalo_km' => $km,
                'observacao' => $obs,
                'marcas' => $this->marcasDa($nome),
                '_pontos' => $pontos,
            ];
        }

        $ordemSistema = array_flip(array_keys(config('pecas_catalogo.sistemas')));

        // Com busca: as mais parecidas primeiro. Sem busca: por sistema, na ordem do catálogo.
        usort($resultado, fn ($a, $b) => $tokens
            ? [$b['_pontos'], $ordemSistema[$a['sistema']]] <=> [$a['_pontos'], $ordemSistema[$b['sistema']]]
            : $ordemSistema[$a['sistema']] <=> $ordemSistema[$b['sistema']]);

        return array_map(function (array $peca) {
            unset($peca['_pontos']);

            return $peca;
        }, $resultado);
    }

    /**
     * 0 = não casa. Todas as palavras da busca precisam aparecer (no nome,
     * nos sinônimos ou no sistema); acertar no nome vale mais.
     *
     * @param array<int, string> $tokens
     */
    private function pontuar(array $tokens, string $nome, string $termos, string $sistemaRotulo): int
    {
        if (!$tokens) {
            return 1;
        }

        $nomeN = self::normalizar($nome);
        $resto = self::normalizar($termos.' '.$sistemaRotulo);
        $pontos = 0;

        foreach ($tokens as $token) {
            $variantes = array_unique([$token, rtrim($token, 's'), preg_replace('/es$/', '', $token)]);
            $noNome = false;
            $noResto = false;

            foreach ($variantes as $v) {
                if ($v === '') {
                    continue;
                }

                $noNome = $noNome || str_contains($nomeN, $v);
                $noResto = $noResto || str_contains($resto, $v);
            }

            if (!$noNome && !$noResto) {
                return 0;
            }

            $pontos += $noNome ? 3 : 1;
        }

        // Nome que começa com a busca (ex.: "amortecedor...") sobe na lista.
        if (str_starts_with($nomeN, $tokens[array_key_first($tokens)])) {
            $pontos += 2;
        }

        return $pontos;
    }
}
