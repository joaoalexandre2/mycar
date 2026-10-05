<?php

namespace App\Models;

use App\Models\Concerns\CalculaTributosVeiculo;
use App\Models\Concerns\PertenceAConta;
use Illuminate\Database\Eloquent\Model;

/**
 * Veículo de uma conta (pessoa ou frota). Os carros dos clientes de uma
 * oficina continuam em Veiculo.
 */
class VeiculoConta extends Model
{
    use CalculaTributosVeiculo, PertenceAConta;

    protected $table = 'veiculos_conta';

    protected $fillable = [
        'apelido',
        'placa',
        'marca',
        'modelo',
        'ano',
        'uf',
        'fipe_marca_id',
        'fipe_modelo_id',
        'fipe_ano',
        'fipe_valor',
        'fipe_consultado_em',
    ];

    protected $casts = [
        'fipe_valor' => 'decimal:2',
        'fipe_consultado_em' => 'datetime',
    ];

    protected $appends = [
        'ipva_estimado',
        'licenciamento_valor',
        'proximo_vencimento_ipva',
        'proximo_vencimento_licenciamento',
    ];
}
