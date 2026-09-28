<?php

namespace App\Models\Concerns;

use App\Models\Oficina;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Isola por oficina todo model que usa essa trait: toda leitura já vem
 * filtrada pela oficina do usuário autenticado, e toda criação já grava
 * o oficina_id automaticamente. Isso evita que uma consulta esquecida em
 * algum controller/repositório vaze dados entre oficinas diferentes.
 *
 * Requisitos: a tabela do model precisa ter a coluna oficina_id, e o
 * request precisa estar autenticado (auth.token já garante isso nas
 * rotas protegidas).
 */
trait PertenceAOficina
{
    public static function bootPertenceAOficina(): void
    {
        static::addGlobalScope('oficina', function (Builder $builder) {
            $coluna = $builder->getModel()->getTable() . '.oficina_id';

            // app('oficina.atual') é 0 (sentinela, nenhuma oficina real tem
            // esse id) quando não há oficina definida — falha fechada: nunca
            // devolve dados de todas as oficinas por engano.
            $builder->where($coluna, app('oficina.atual'));
        });

        static::creating(function ($model) {
            if (empty($model->oficina_id)) {
                $model->oficina_id = app('oficina.atual');
            }
        });
    }

    public function oficina(): BelongsTo
    {
        return $this->belongsTo(Oficina::class);
    }
}
