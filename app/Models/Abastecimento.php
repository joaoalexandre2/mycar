<?php

namespace App\Models;

use App\Models\Concerns\PertenceAConta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Abastecimento extends Model
{
    use PertenceAConta;

    public const COMBUSTIVEIS = ['gasolina', 'etanol', 'diesel', 'gnv', 'outro'];

    protected $table = 'abastecimentos';

    protected $fillable = [
        'veiculo_conta_id',
        'data',
        'km',
        'litros',
        'valor_total',
        'tanque_cheio',
        'combustivel',
        'posto',
    ];

    protected $casts = [
        'data' => 'date:Y-m-d',
        'litros' => 'decimal:3',
        'valor_total' => 'decimal:2',
        'tanque_cheio' => 'boolean',
    ];

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(VeiculoConta::class, 'veiculo_conta_id');
    }
}
