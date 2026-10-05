<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Inquilino dos perfis "Cuidados com seu carro" (tipo pessoa) e "Frota"
 * (tipo frota). Não tem relação com Oficina: são mundos separados.
 */
class Conta extends Model
{
    public const TIPO_PESSOA = 'pessoa';

    public const TIPO_FROTA = 'frota';

    public const TIPOS = [self::TIPO_PESSOA, self::TIPO_FROTA];

    protected $fillable = [
        'tipo',
        'nome',
    ];

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
