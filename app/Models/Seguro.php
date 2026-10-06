<?php

namespace App\Models;

use App\Models\Concerns\PertenceAConta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Seguro extends Model
{
    use PertenceAConta;

    public const TIPO_APOLICE = 'apolice';

    public const TIPO_PROPOSTA = 'proposta';

    public const TIPOS = [self::TIPO_APOLICE, self::TIPO_PROPOSTA];

    protected $table = 'seguros';

    protected $fillable = [
        'veiculo_conta_id',
        'tipo',
        'seguradora',
        'valor_anual',
        'franquia',
        'vigencia_fim',
        'observacoes',
    ];

    protected $casts = [
        'valor_anual' => 'decimal:2',
        'franquia' => 'decimal:2',
        'vigencia_fim' => 'date:Y-m-d',
        'alertado_em' => 'date:Y-m-d',
    ];

    /** Controle interno do lembrete: não precisa ir para o frontend. */
    protected $hidden = ['alertado_em'];

    protected static function booted(): void
    {
        // Mudou o fim da vigência? O aviso antigo não vale para a nova data.
        static::saving(function (Seguro $seguro) {
            if ($seguro->isDirty('vigencia_fim')) {
                $seguro->alertado_em = null;
            }
        });
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(VeiculoConta::class, 'veiculo_conta_id');
    }
}
