<?php

namespace App\Models;

use App\Models\Concerns\PertenceAConta;
use Illuminate\Database\Eloquent\Model;

/** Modelo de grupo salvo pela frota: um ponto de partida com permissões e menus já marcados. */
class ModeloGrupo extends Model
{
    use PertenceAConta;

    protected $table = 'modelos_grupo';

    protected $fillable = ['nome', 'descricao', 'permissoes', 'menus_liberados', 'criado_por_user_id'];

    protected function casts(): array
    {
        return ['permissoes' => 'array', 'menus_liberados' => 'array'];
    }
}
