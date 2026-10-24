<?php

namespace App\Services;

/**
 * Ficha técnica de referência (versão mais recente) de todos os modelos
 * coletados em database/data/fichas_catalogo/{marca}.json por
 * `php artisan modelos:raspar-fichacompleta`. Só LÊ os arquivos, um por marca,
 * e só o da marca consultada: nenhuma raspagem acontece em produção.
 */
class FichasCatalogo
{
    /** @var array<string, array<string, array<string, mixed>>> */
    private array $marcas = [];

    public function __construct(private readonly ?string $pasta = null) {}

    /**
     * Acha o modelo do site pelo nome do veículo (o slug com mais palavras, todas
     * presentes no nome do veículo, vence).
     *
     * @return array<string, mixed>|null modelo (slug), marca (slug) e a ficha
     */
    public function paraVeiculo(?string $marca, ?string $modelo): ?array
    {
        $marcaTexto = ' ' . CatalogoPecas::normalizar($marca) . ' ';
        $palavrasDoModelo = explode(' ', CatalogoPecas::normalizar($modelo));

        if (trim($marcaTexto) === '' || $palavrasDoModelo === ['']) {
            return null;
        }

        foreach ($this->slugsDeMarcas() as $slugMarca) {
            if (!str_contains($marcaTexto, ' ' . str_replace('-', ' ', $slugMarca) . ' ')) {
                continue;
            }

            $melhor = null;
            $melhorPeso = 0;

            foreach ($this->modelosDaMarca($slugMarca) as $slugModelo => $ficha) {
                $palavras = explode('-', $slugModelo);

                if (count($palavras) > $melhorPeso && !array_diff($palavras, $palavrasDoModelo)) {
                    $melhorPeso = count($palavras);
                    $melhor = ['marca' => $slugMarca, 'modelo' => $slugModelo] + $ficha;
                }
            }

            if ($melhor) {
                return $melhor;
            }
        }

        return null;
    }

    /**
     * Capacidade do tanque de combustível, em litros, segundo a ficha do modelo
     * ("Tanque de combustível: 53 litros"). Nulo quando o modelo não está no
     * catálogo ou a ficha não traz o tanque.
     */
    public function tanqueLitros(?string $marca, ?string $modelo): ?float
    {
        $ficha = $this->paraVeiculo($marca, $modelo);

        foreach ($ficha['grupos'] ?? [] as $grupo) {
            foreach ($grupo['campos'] ?? [] as $campo) {
                $rotulo = CatalogoPecas::normalizar($campo['rotulo'] ?? '');

                if (str_starts_with($rotulo, 'tanque') && preg_match('/(\d+(?:[.,]\d+)?)\s*litros?/iu', (string) ($campo['valor'] ?? ''), $achado)) {
                    $litros = (float) str_replace(',', '.', $achado[1]);

                    return $litros > 0 ? $litros : null;
                }
            }
        }

        return null;
    }

    public function total(): int
    {
        return array_sum(array_map(
            fn (string $marca) => count($this->modelosDaMarca($marca)),
            $this->slugsDeMarcas(),
        ));
    }

    /** @return array<int, string> */
    private function slugsDeMarcas(): array
    {
        $arquivos = glob($this->pasta() . '/*.json') ?: [];

        return array_map(fn (string $a) => basename($a, '.json'), $arquivos);
    }

    /** @return array<string, array<string, mixed>> */
    private function modelosDaMarca(string $marca): array
    {
        if (!isset($this->marcas[$marca])) {
            $arquivo = $this->pasta() . "/{$marca}.json";
            $conteudo = is_file($arquivo) ? file_get_contents($arquivo) : false;

            $this->marcas[$marca] = $conteudo ? (json_decode($conteudo, true) ?: []) : [];
        }

        return $this->marcas[$marca];
    }

    private function pasta(): string
    {
        return rtrim($this->pasta ?? base_path('database/data/fichas_catalogo'), '/\\');
    }
}
