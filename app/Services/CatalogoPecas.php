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

    /** "Corolla XEi 2.0" -> "corolla xei 2 0" (sem acento, só letras e números). */
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

            if (array_diff($exigidas, $modeloTokens)) {
                continue;
            }

            if (count($exigidas) > $melhorPeso) {
                $melhorPeso = count($exigidas);
                $melhor = [
                    'nome' => $nome,
                    'categoria' => $categoria,
                    'categoria_rotulo' => config("pecas_catalogo.categorias.{$categoria}"),
                ];
            }
        }

        return $melhor;
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
