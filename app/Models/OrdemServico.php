<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrdemServico extends Model
{
    protected $table = 'ordens_servico';

    protected $fillable = [
        'veiculo_id',
        'descricao',
        'status',
        'valor',
        'data_abertura',
        'data_fechamento',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'data_abertura' => 'date',
        'data_fechamento' => 'datetime',
    ];

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }
}