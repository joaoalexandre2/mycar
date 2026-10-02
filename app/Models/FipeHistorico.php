<?php

namespace App\Models;

use App\Models\Concerns\PertenceAOficina;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FipeHistorico extends Model
{
    use PertenceAOficina;

    public $timestamps = false;

    protected $fillable = [
        'veiculo_id',
        'valor',
        'consultado_em',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'consultado_em' => 'datetime',
    ];

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }
}
