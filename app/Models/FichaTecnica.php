<?php

namespace App\Models;

use App\Models\Concerns\PertenceAOficina;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FichaTecnica extends Model
{
    use PertenceAOficina;

    protected $table = 'fichas_tecnicas';

    public const CAMPOS = [
        'oleo_viscosidade',
        'oleo_especificacao',
        'oleo_capacidade_litros',
        'filtro_oleo',
        'filtro_ar',
        'filtro_combustivel',
        'pneu_medida',
        'pneu_pressao_dianteira',
        'pneu_pressao_traseira',
        'observacoes',
    ];

    protected $fillable = ['veiculo_id', ...self::CAMPOS];

    protected $casts = [
        'oleo_capacidade_litros' => 'decimal:1',
    ];

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }
}
