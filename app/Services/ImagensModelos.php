<?php

namespace App\Services;

/**
 * Foto de cada modelo do catálogo, lida de database/data/imagens_modelos.json e
 * database/data/imagens_modelos/ (gerados por `php artisan modelos:raspar-imagens`).
 * O app só LÊ: nenhuma raspagem acontece em produção. Cada foto traz autor e
 * licença, que a tela precisa mostrar junto (exigência das licenças Creative Commons).
 */
class ImagensModelos
{
    /** @var array<string, array<string, mixed>>|null */
    private ?array $imagens = null;

    public function __construct(
        private readonly ?string $json = null,
        private readonly ?string $pasta = null,
    ) {
    }

    /**
     * @return array<string, mixed>|null slug, arquivo, autor, licença, link e fonte
     */
    public function porNome(?string $nomeDoModelo): ?array
    {
        return $nomeDoModelo === null ? null : ($this->todas()[$nomeDoModelo] ?? null);
    }

    /** Caminho do arquivo da foto no disco, ou null se o slug não existir. */
    public function caminhoDoSlug(string $slug): ?string
    {
        foreach ($this->todas() as $imagem) {
            if (($imagem['slug'] ?? null) === $slug) {
                $caminho = ($this->pasta ?? base_path('database/data/imagens_modelos')).'/'.basename($imagem['arquivo']);

                return is_file($caminho) ? $caminho : null;
            }
        }

        return null;
    }

    /** @return array<string, array<string, mixed>> */
    private function todas(): array
    {
        if ($this->imagens === null) {
            $caminho = $this->json ?? base_path('database/data/imagens_modelos.json');
            $conteudo = is_file($caminho) ? file_get_contents($caminho) : false;

            $this->imagens = $conteudo ? (json_decode($conteudo, true) ?: []) : [];
        }

        return $this->imagens;
    }
}
