<?php

namespace App\Support;

use Carbon\Carbon;
use DateTimeInterface;

/**
 * Fuso das datas que a frota digita e vê. O banco e o servidor guardam em UTC; o que a empresa
 * digita ("2026-11-10T08:00", sem fuso) é horário de Brasília, e o que sai para a tela também.
 *
 * Entrada: texto sem fuso é horário local; texto com fuso (Z ou +hh:mm) é respeitado.
 * Saída: sempre com o fuso local, para o frontend mostrar a hora certa.
 */
class FusoDaFrota
{
    public const NOME = 'America/Sao_Paulo';

    /** Uma data-hora vinda da empresa, convertida para UTC para gravar e comparar. */
    public static function entrada(string|DateTimeInterface $valor): Carbon
    {
        if ($valor instanceof DateTimeInterface) {
            return Carbon::instance($valor)->utc();
        }

        $temFuso = (bool) preg_match('/(Z|[+-]\d{2}:?\d{2})$/i', trim($valor));

        return ($temFuso ? Carbon::parse($valor) : Carbon::parse($valor, self::NOME))->utc();
    }

    /** O início ou o fim do dia informado, no fuso local, já convertido para UTC. */
    public static function dia(string $valor, bool $fim = false): Carbon
    {
        $dia = Carbon::parse($valor, self::NOME);

        return ($fim ? $dia->endOfDay() : $dia->startOfDay())->utc();
    }

    /** A data-hora no fuso local, como Carbon. */
    public static function local(DateTimeInterface $valor): Carbon
    {
        return Carbon::instance($valor)->setTimezone(self::NOME);
    }

    /** A data-hora para a resposta da API, com o fuso local (ex.: 2026-11-10T08:00:00-03:00). */
    public static function saida(?DateTimeInterface $valor): ?string
    {
        return $valor ? self::local($valor)->toIso8601String() : null;
    }
}
