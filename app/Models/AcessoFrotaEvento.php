<?php

namespace App\Models;

use App\Models\Concerns\PertenceAConta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trilha de auditoria de acesso da frota: quem criou, mudou ou removeu grupos
 * e membros, e quem mudou o papel de alguém. Só se acrescenta.
 */
class AcessoFrotaEvento extends Model
{
    use PertenceAConta;

    public const UPDATED_AT = null;

    protected $table = 'acessos_frota_eventos';

    protected $fillable = ['ator_id', 'alvo_tipo', 'alvo_id', 'acao', 'detalhes'];

    protected function casts(): array
    {
        return ['detalhes' => 'array'];
    }

    public function ator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ator_id');
    }

    /** @param  array<string, mixed>|null  $detalhes */
    public static function registrar(?int $atorId, string $alvoTipo, ?int $alvoId, string $acao, ?array $detalhes = null): self
    {
        return self::create([
            'ator_id' => $atorId,
            'alvo_tipo' => $alvoTipo,
            'alvo_id' => $alvoId,
            'acao' => $acao,
            'detalhes' => $detalhes,
        ]);
    }
}
