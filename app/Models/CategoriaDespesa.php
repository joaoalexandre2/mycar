<?php

namespace App\Models;

use App\Models\Concerns\PertenceAConta;
use Illuminate\Database\Eloquent\Model;

class CategoriaDespesa extends Model
{
    use PertenceAConta;

    protected $table = 'categorias_despesa';

    /** Categorias que toda conta começa com. A conta pode renomear, criar outras e desativar estas. */
    public const PADROES = [
        'Pedágio',
        'Estacionamento',
        'Lavagem',
        'Documentação e taxas',
        'Reparo e peças',
        'Outros',
    ];

    protected $fillable = ['nome', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    /** Cria as categorias padrão na primeira vez que a conta as usa. */
    public static function garantirPadroes(): void
    {
        if (static::query()->exists()) {
            return;
        }

        foreach (self::PADROES as $nome) {
            static::create(['nome' => $nome, 'ativo' => true]);
        }
    }
}
