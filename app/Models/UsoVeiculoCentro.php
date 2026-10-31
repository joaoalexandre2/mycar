<?php

namespace App\Models;

use App\Models\Concerns\PertenceAConta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cada troca de centro de custo de um uso (de qual para qual, quem e por quê).
 * Só se acrescenta; é a base dos relatórios de custo por centro.
 */
class UsoVeiculoCentro extends Model
{
    use PertenceAConta;

    public const UPDATED_AT = null;

    protected $table = 'usos_veiculo_centros';

    protected $fillable = [
        'uso_veiculo_id', 'veiculo_conta_id',
        'centro_anterior_id', 'centro_anterior_nome',
        'centro_novo_id', 'centro_novo_nome',
        'user_id', 'motivo',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function uso(): BelongsTo
    {
        return $this->belongsTo(UsoVeiculo::class, 'uso_veiculo_id');
    }

    /** @return array<string, mixed> */
    public function formatar(): array
    {
        return [
            'id' => $this->id,
            'uso_veiculo_id' => $this->uso_veiculo_id,
            'veiculo_conta_id' => $this->veiculo_conta_id,
            'de' => ['id' => $this->centro_anterior_id, 'nome' => $this->centro_anterior_nome],
            'para' => ['id' => $this->centro_novo_id, 'nome' => $this->centro_novo_nome],
            'usuario' => $this->usuario?->name,
            'motivo' => $this->motivo,
            'em' => $this->created_at?->toIso8601String(),
        ];
    }
}
