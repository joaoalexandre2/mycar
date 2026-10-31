<?php

namespace App\Models;

use App\Models\Concerns\PertenceAConta;
use Illuminate\Database\Eloquent\Model;

/** Motivo cadastrado pela frota para trocar o centro de custo de um uso. Isolado por conta. */
class MotivoTrocaCentro extends Model
{
    use PertenceAConta;

    protected $table = 'motivos_troca_centro';

    protected $fillable = ['nome', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }
}
