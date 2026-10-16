<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Vínculo entre um usuário e uma identidade em Google/Facebook. */
class ContaSocial extends Model
{
    protected $table = 'contas_sociais';

    protected $fillable = ['user_id', 'provedor', 'provedor_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
