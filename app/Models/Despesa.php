<?php

namespace App\Models;

use App\Models\Concerns\PertenceAConta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Despesa avulsa da conta: o que não tem outro registro no sistema. */
class Despesa extends Model
{
    use PertenceAConta;

    protected $table = 'despesas';

    protected $fillable = [
        'categoria_id',
        'veiculo_conta_id',
        'motorista_id',
        'centro_custo_id',
        'data',
        'valor',
        'descricao',
        'comprovante',
    ];

    protected function casts(): array
    {
        return ['data' => 'date:Y-m-d', 'valor' => 'decimal:2'];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaDespesa::class, 'categoria_id');
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(VeiculoConta::class, 'veiculo_conta_id');
    }

    public function motorista(): BelongsTo
    {
        return $this->belongsTo(Motorista::class);
    }

    public function centroCusto(): BelongsTo
    {
        return $this->belongsTo(CentroCusto::class);
    }
}
