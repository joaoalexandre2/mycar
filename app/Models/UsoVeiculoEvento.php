<?php

namespace App\Models;

use App\Models\Concerns\PertenceAConta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trilha de auditoria da frota: quem fez o quê em cada uso (reserva, saída,
 * chegada, correções). Só se acrescenta; nada aqui é editado ou apagado.
 */
class UsoVeiculoEvento extends Model
{
    use PertenceAConta;

    public const UPDATED_AT = null;

    protected $table = 'usos_veiculo_eventos';

    protected $fillable = ['uso_veiculo_id', 'veiculo_conta_id', 'user_id', 'acao', 'dados', 'motivo'];

    protected function casts(): array
    {
        return ['dados' => 'array'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @param  array<string, mixed>|null  $dados */
    public static function registrar(?UsoVeiculo $uso, string $acao, ?int $userId, ?array $dados = null, ?string $motivo = null, ?int $veiculoId = null): self
    {
        return self::create([
            'uso_veiculo_id' => $uso?->id,
            'veiculo_conta_id' => $uso?->veiculo_conta_id ?? $veiculoId,
            'user_id' => $userId,
            'acao' => $acao,
            'dados' => $dados,
            'motivo' => $motivo,
        ]);
    }
}
