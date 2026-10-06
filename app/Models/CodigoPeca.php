<?php

namespace App\Models;

use App\Models\Concerns\PertenceAConta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Código de peça que a própria pessoa (ou o mecânico dela) confirmou para um
 * veículo, para reaproveitar na próxima compra. Não vem de nenhum catálogo.
 */
class CodigoPeca extends Model
{
    use PertenceAConta;

    protected $table = 'codigos_pecas';

    protected $fillable = [
        'veiculo_conta_id',
        'peca_id',
        'marca',
        'codigo',
        'observacoes',
    ];

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(VeiculoConta::class, 'veiculo_conta_id');
    }
}
