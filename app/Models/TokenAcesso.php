<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Sessão de um aparelho/navegador. Guarda só o hash do token, nunca o token. */
class TokenAcesso extends Model
{
    use Prunable;

    protected $table = 'tokens_acesso';

    /** Quantas sessões simultâneas cada pessoa pode ter. */
    public const LIMITE_POR_USUARIO = 10;

    /** Sessão sem uso por tantos dias é descartada no próximo login. */
    public const DIAS_SEM_USO = 90;

    protected $fillable = ['user_id', 'token_hash', 'dispositivo', 'ultimo_uso_em'];

    protected $casts = ['ultimo_uso_em' => 'datetime'];

    /**
     * Sessões sem uso há mais de DIAS_SEM_USO dias (o php artisan model:prune,
     * agendado todo dia, apaga). Quem nunca voltaria a usar o token já é
     * recusado pelo middleware; isto só tira as linhas esquecidas da tabela.
     *
     * Sessão que nunca registrou uso conta pela data de criação, como no
     * middleware.
     */
    public function prunable(): Builder
    {
        $limite = now()->subDays(self::DIAS_SEM_USO);

        return static::query()->where(function (Builder $query) use ($limite) {
            $query->where('ultimo_uso_em', '<', $limite)
                ->orWhere(function (Builder $query) use ($limite) {
                    $query->whereNull('ultimo_uso_em')->where('created_at', '<', $limite);
                });
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
