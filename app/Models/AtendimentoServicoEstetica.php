<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Serviço cobrado em um atendimento, com o preço daquela hora (o preço do catálogo pode mudar depois). */
class AtendimentoServicoEstetica extends Model
{
    public $timestamps = false;

    protected $table = 'atendimento_servicos_estetica';

    protected $fillable = ['atendimento_id', 'servico_id', 'nome', 'preco'];

    protected function casts(): array
    {
        return ['preco' => 'decimal:2'];
    }
}
