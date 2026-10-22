<?php

namespace App\Models;

use App\Models\Concerns\PertenceAConta;
use Illuminate\Database\Eloquent\Model;

/** Uma versão da foto do hodômetro de um uso. Trocar a foto cria outra linha e marca a anterior como substituída. */
class UsoVeiculoFoto extends Model
{
    use PertenceAConta;

    public const UPDATED_AT = null;

    protected $table = 'usos_veiculo_fotos';

    protected $fillable = ['uso_veiculo_id', 'momento', 'caminho', 'user_id', 'substituida_em', 'apagada_em'];

    protected function casts(): array
    {
        return ['substituida_em' => 'datetime', 'apagada_em' => 'datetime'];
    }
}
