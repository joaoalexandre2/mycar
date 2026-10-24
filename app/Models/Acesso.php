<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um MyCar a mais de uma pessoa: o mesmo login com, por exemplo, uma oficina e
 * uma frota. O MyCar principal fica nas colunas do usuário; os demais, aqui.
 */
class Acesso extends Model
{
    protected $table = 'acessos';

    protected $fillable = ['user_id', 'perfil', 'oficina_id', 'conta_id', 'papel_frota'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function oficina(): BelongsTo
    {
        return $this->belongsTo(Oficina::class);
    }

    public function conta(): BelongsTo
    {
        return $this->belongsTo(Conta::class);
    }

    /** Nome da oficina ou da conta a que este acesso leva. */
    public function nome(): string
    {
        return (string) ($this->perfil === 'oficina' ? $this->oficina?->nome : $this->conta?->nome);
    }
}
