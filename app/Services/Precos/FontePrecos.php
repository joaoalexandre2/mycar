<?php

namespace App\Services\Precos;

/**
 * Uma loja ou marketplace de onde buscamos preço. Cada fonte usa a API
 * oficial dela (nunca raspagem de página) e devolve ofertas no mesmo formato.
 */
interface FontePrecos
{
    /** Nome mostrado na tela (ex.: "Mercado Livre"). */
    public function nome(): string;

    /** Tem as chaves de acesso necessárias? */
    public function configurada(): bool;

    /**
     * @return array<int, array{titulo: string, preco: float, loja: string, url: string, imagem: ?string, frete_gratis: bool}>
     *
     * @throws FontePrecosIndisponivelException quando a loja não responde ou recusa a chave
     */
    public function buscar(string $consulta, int $limite = 50): array;
}
