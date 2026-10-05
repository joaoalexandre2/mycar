<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Oficina extends Model
{
    use HasFactory;

    protected $fillable = [
        'nome',
        'cnpj',
        'telefone',
        'endereco',
        'resumo_semanal',
    ];

    protected $casts = [
        'resumo_semanal' => 'boolean',
        'arquivada_em' => 'datetime',
    ];

    /**
     * Oficinas em funcionamento. As arquivadas (dono migrou de perfil) não
     * recebem avisos nem resumo e não entram nas contagens.
     */
    public function scopeAtivas(Builder $consulta): Builder
    {
        return $consulta->whereNull('arquivada_em');
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class);
    }
}
