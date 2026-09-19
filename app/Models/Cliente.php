<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    protected $fillable = [
        'nome',
        'cpf',
        'telefone',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    protected $attributes = [
        'ativo' => true,
    ];

    public function veiculos(): HasMany
    {
        return $this->hasMany(Veiculo::class);
    }
}
