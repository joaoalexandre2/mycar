<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * O ano de fabricação é igual ao ano do modelo ou um ano antes dele
 * (2018/2018 ou 2018/2019). Nunca depois, nem mais de um ano antes.
 */
class AnoFabricacao implements ValidationRule
{
    public function __construct(private readonly mixed $anoModelo)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Sem o ano do modelo no pedido, só vale a faixa geral (já validada à parte).
        if ($value === null || $value === '' || !is_numeric($this->anoModelo)) {
            return;
        }

        $diferenca = (int) $this->anoModelo - (int) $value;

        if ($diferenca < 0 || $diferenca > 1) {
            $fail('O ano de fabricação deve ser igual ao ano do modelo ou um ano antes dele (ex.: 2018/2019).');
        }
    }
}
