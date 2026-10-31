<?php

namespace App\Models;

use App\Models\Concerns\PertenceAConta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Grupo de usuários da frota, com as permissões que concede. Isolado por conta. */
class GrupoUsuario extends Model
{
    use PertenceAConta;

    protected $table = 'grupos_usuarios';

    protected $fillable = ['nome', 'descricao', 'permissoes', 'menus_liberados'];

    protected function casts(): array
    {
        return ['permissoes' => 'array', 'menus_liberados' => 'array'];
    }

    public function membros(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'grupo_usuario_membros', 'grupo_usuario_id', 'user_id')
            ->withTimestamps();
    }
}
