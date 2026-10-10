<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Placa brasileira em um dos dois padrões:
 *  - antigo:   3 letras + 4 números      (ABC1234 ou ABC-1234)
 *  - Mercosul: 3 letras + número + letra + 2 números (ABC1D23)
 *
 * Use PlacaBrasileira::normalizar() antes de validar e gravar: tira hífen e
 * espaços e deixa em maiúsculas, para a placa ser sempre guardada igual.
 */
class PlacaBrasileira implements ValidationRule
{
    private const ANTIGA = '/^[A-Z]{3}[0-9]{4}$/';
    private const MERCOSUL = '/^[A-Z]{3}[0-9][A-Z][0-9]{2}$/';

    public static function normalizar(mixed $placa): mixed
    {
        if (!is_string($placa)) {
            return $placa;
        }

        return strtoupper(preg_replace('/[\s-]+/', '', $placa));
    }

    /** 'mercosul', 'antiga' ou null quando não é uma placa válida. */
    public static function padrao(?string $placa): ?string
    {
        $placa = (string) self::normalizar($placa);

        if (preg_match(self::MERCOSUL, $placa)) {
            return 'mercosul';
        }

        return preg_match(self::ANTIGA, $placa) ? 'antiga' : null;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (self::padrao(is_string($value) ? $value : null) === null) {
            $fail('Placa inválida. Use o padrão antigo (ABC-1234) ou Mercosul (ABC1D23).');
        }
    }
}
