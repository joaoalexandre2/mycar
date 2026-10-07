<?php

namespace App\Models;

use App\Models\Concerns\PertenceAConta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ficha de manutenção (óleo, filtros, pneus) de um veículo de conta. */
class FichaTecnicaConta extends Model
{
    use PertenceAConta;

    protected $table = 'fichas_tecnicas_conta';

    protected $fillable = ['veiculo_conta_id', ...FichaTecnica::CAMPOS];

    protected $casts = [
        'oleo_capacidade_litros' => 'decimal:1',
    ];

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(VeiculoConta::class, 'veiculo_conta_id');
    }
}
