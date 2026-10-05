<?php

namespace App\Models\Concerns;

use App\Models\Conta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Isola por conta (perfis pessoa e frota) o model que usa essa trait, no mesmo
 * molde de PertenceAOficina: toda leitura vem filtrada pela conta do usuário
 * autenticado e toda criação grava o conta_id sozinha.
 *
 * app('conta.atual') é 0 (sentinela) quando não há conta, o que falha fechado:
 * um usuário de oficina, por exemplo, nunca enxerga nada de nenhuma conta.
 */
trait PertenceAConta
{
    public static function bootPertenceAConta(): void
    {
        static::addGlobalScope('conta', function (Builder $builder) {
            $builder->where($builder->getModel()->getTable() . '.conta_id', app('conta.atual'));
        });

        static::creating(function ($model) {
            if (empty($model->conta_id)) {
                $model->conta_id = app('conta.atual');
            }
        });
    }

    public function conta(): BelongsTo
    {
        return $this->belongsTo(Conta::class);
    }
}
