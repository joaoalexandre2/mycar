<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\OrdemServico;
use App\Models\Manutencao;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class Veiculo extends Model
{
    protected $fillable = [
        'cliente_id',
        'placa',
        'marca',
        'modelo',
        'ano',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

     public function ordensServico(): HasMany
    {
        return $this->hasMany(OrdemServico::class);
    }

    public function manutencoes(): HasMany
{
    return $this->hasMany(Manutencao::class);
}
}
