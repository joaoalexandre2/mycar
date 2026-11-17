<?php

namespace App\Services\Hodometro;

use Illuminate\Http\UploadedFile;

/**
 * Lê o km de uma foto do painel. A implementação usa um modelo de IA; os testes
 * trocam por uma versão falsa.
 */
interface LeitorDeHodometro
{
    /** Se a leitura está configurada (há chave da API). Sem ela, a tela pede o km digitado. */
    public function disponivel(): bool;

    /** O km lido, ou null quando a foto não deixa ler com segurança. */
    public function ler(UploadedFile $foto): ?int;
}
