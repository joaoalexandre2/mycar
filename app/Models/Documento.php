<?php

namespace App\Models;

use App\Models\Concerns\PertenceAConta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Documento de um veículo da conta, com data de vencimento. O CRLV tem
 * tratamento especial: a data real dele substitui a estimativa de
 * licenciamento nos avisos.
 */
class Documento extends Model
{
    use PertenceAConta;

    public const TIPOS = ['crlv', 'vistoria', 'outro'];

    protected $table = 'documentos_conta';

    protected $fillable = [
        'veiculo_conta_id',
        'tipo',
        'titulo',
        'vencimento',
        'observacoes',
    ];

    protected $casts = [
        'vencimento' => 'date:Y-m-d',
        'alertado_em' => 'date:Y-m-d',
    ];

    protected $hidden = ['alertado_em'];

    protected $appends = ['rotulo'];

    protected static function booted(): void
    {
        // Mudou o vencimento? O aviso antigo não vale para a nova data.
        static::saving(function (Documento $documento) {
            if ($documento->isDirty('vencimento')) {
                $documento->alertado_em = null;
            }
        });
    }

    public function getRotuloAttribute(): string
    {
        return match ($this->tipo) {
            'crlv' => 'CRLV',
            'vistoria' => 'Vistoria',
            default => $this->titulo ?: 'Documento',
        };
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(VeiculoConta::class, 'veiculo_conta_id');
    }
}
