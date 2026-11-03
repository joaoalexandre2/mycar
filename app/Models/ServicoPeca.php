<?php

namespace App\Models;

use App\Models\Concerns\PertenceAConta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Peça usada num serviço (filtro, pastilha, lâmpada...). */
class ServicoPeca extends Model
{
    use PertenceAConta;

    protected $table = 'servico_pecas';

    protected $fillable = ['servico_id', 'descricao', 'quantidade', 'valor_unitario'];

    protected function casts(): array
    {
        return ['quantidade' => 'decimal:2', 'valor_unitario' => 'decimal:2'];
    }

    public function servico(): BelongsTo
    {
        return $this->belongsTo(Servico::class);
    }

    /** Quantidade vezes o valor unitário, arredondado em centavos. */
    public function total(): float
    {
        return round((float) $this->quantidade * (float) $this->valor_unitario, 2);
    }
}
