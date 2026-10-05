<?php

namespace App\Models;

use App\Models\Concerns\PertenceAOficina;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Manutencao extends Model
{
    use HasFactory, PertenceAOficina;

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
        'alertado_em' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Se a data da próxima manutenção mudou, o alerta antigo não vale
        // mais para a nova data — limpa para que possa ser reenviado.
        static::saving(function (Manutencao $manutencao) {
            if ($manutencao->isDirty('proxima_data')) {
                $manutencao->alertado_em = null;
            }
        });
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }

    /** Peças registradas ao salvar esta manutenção. */
    public function pecas(): HasMany
    {
        return $this->hasMany(VeiculoPeca::class);
    }
}
