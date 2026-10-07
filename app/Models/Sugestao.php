<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Sugestão de melhoria enviada por um usuário para a equipe do MyCar. */
class Sugestao extends Model
{
    public const CATEGORIAS = ['melhoria', 'nova_funcao', 'problema', 'outro'];

    public const STATUS = ['nova', 'em_analise', 'planejada', 'feita', 'recusada'];

    protected $table = 'sugestoes';

    protected $fillable = ['user_id', 'categoria', 'titulo', 'descricao', 'perfil', 'status', 'resposta'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
