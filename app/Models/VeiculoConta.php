<?php

namespace App\Models;

use App\Models\Concerns\CalculaTributosVeiculo;
use App\Models\Concerns\PertenceAConta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'revisao_prevista_em',
        'fipe_marca_id',
        'fipe_modelo_id',
        'fipe_ano',
        'fipe_valor',
        'fipe_consultado_em',
    ];

    protected $casts = [
        'fipe_valor' => 'decimal:2',
        'fipe_consultado_em' => 'datetime',
        'revisao_prevista_em' => 'date:Y-m-d',
        'revisao_alertada_em' => 'date:Y-m-d',
    ];

    /** Controle interno dos lembretes: não precisa ir para o frontend. */
    protected $hidden = [
        'ipva_alertado_ano',
        'licenciamento_alertado_ano',
        'revisao_alertada_em',
    ];

    protected $appends = [
        'ipva_estimado',
        'licenciamento_valor',
        'proximo_vencimento_ipva',
        'proximo_vencimento_licenciamento',
    ];

    protected static function booted(): void
    {
        // Mudou a data da revisão? O aviso antigo não vale para a nova data.
        static::saving(function (VeiculoConta $veiculo) {
            if ($veiculo->isDirty('revisao_prevista_em')) {
                $veiculo->revisao_alertada_em = null;
            }
        });
    }

    public function abastecimentos(): HasMany
    {
        return $this->hasMany(Abastecimento::class, 'veiculo_conta_id');
    }
}
