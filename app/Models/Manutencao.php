<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Manutencao extends Model
{
    protected $table = 'manutencoes';
    protected $fillable = [
        'veiculo_id',
        'tipo',
        'descricao',
        'valor',
        'data_manutencao',
        'quilometragem',
        'proxima_data',
        'proxima_quilometragem',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'data_manutencao' => 'date',
        'proxima_data' => 'date',
    ];

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }
}
