<?php

namespace Tests;

use App\Support\SessaoCookie;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    /** O token da sessão, lido do cookie que o login grava (o corpo da resposta não traz mais o token). */
    protected function tokenDaSessao(TestResponse $resposta): string
    {
        foreach ($resposta->headers->getCookies() as $cookie) {
            if ($cookie->getName() === SessaoCookie::NOME) {
                return $cookie->getValue();
            }
        }

        $this->fail('A resposta não trouxe o cookie da sessão.');
    }
}
