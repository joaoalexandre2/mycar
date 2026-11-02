<?php

namespace App\Models;

use App\Models\Concerns\PertenceAConta;
use Illuminate\Database\Eloquent\Model;

/** Serviço da estética. Com dias_previstos > 0 é um serviço longo (vitrificação, PPF). */
class ServicoEstetica extends Model
{
    use PertenceAConta;

    protected $table = 'servicos_estetica';

    protected $fillable = ['nome', 'preco', 'dias_previstos', 'ativo'];

    protected function casts(): array
    {
        return ['preco' => 'decimal:2', 'dias_previstos' => 'integer', 'ativo' => 'boolean'];
    }
}
