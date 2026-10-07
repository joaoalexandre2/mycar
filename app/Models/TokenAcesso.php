<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Sessão de um aparelho/navegador. Guarda só o hash do token, nunca o token. */
class TokenAcesso extends Model
{
    protected $table = 'tokens_acesso';

    /** Quantas sessões simultâneas cada pessoa pode ter. */
    public const LIMITE_POR_USUARIO = 10;

    /** Sessão sem uso por tantos dias é descartada no próximo login. */
    public const DIAS_SEM_USO = 90;

    protected $fillable = ['user_id', 'token_hash', 'dispositivo', 'ultimo_uso_em'];

    protected $casts = ['ultimo_uso_em' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
