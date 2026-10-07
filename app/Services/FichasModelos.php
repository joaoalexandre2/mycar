<?php

namespace App\Services;

/**
 * Ficha técnica dos principais modelos, lida de database/data/fichas_modelos.json
 * (gerado por `php artisan modelos:raspar-fichas`). O app só LÊ o arquivo:
 * nenhuma raspagem acontece em produção.
 */
class FichasModelos
{
    /** @var array<string, array<string, mixed>>|null */
    private ?array $fichas = null;

    public function __construct(private readonly ?string $arquivo = null)
    {
    }

    /**
     * @return array<string, mixed>|null fonte, url, licença, data da coleta e campos
     */
    public function porNome(?string $nomeDoModelo): ?array
    {
        if ($nomeDoModelo === null) {
            return null;
        }

        return $this->todas()[$nomeDoModelo] ?? null;
    }

    public function total(): int
    {
        return count($this->todas());
    }

    /** @return array<string, array<string, mixed>> */
    private function todas(): array
    {
        if ($this->fichas === null) {
            $caminho = $this->arquivo ?? base_path('database/data/fichas_modelos.json');
            $conteudo = is_file($caminho) ? file_get_contents($caminho) : false;

            $this->fichas = $conteudo ? (json_decode($conteudo, true) ?: []) : [];
        }

        return $this->fichas;
    }
}
