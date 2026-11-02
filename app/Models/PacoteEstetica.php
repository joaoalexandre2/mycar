<?php

namespace App\Models;

use App\Models\Concerns\PertenceAConta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Pacote: combinação de serviços com preço próprio. */
class PacoteEstetica extends Model
{
    use PertenceAConta;

    protected $table = 'pacotes_estetica';

    protected $fillable = ['nome', 'preco'];

    protected function casts(): array
    {
        return ['preco' => 'decimal:2'];
    }

    public function servicos(): BelongsToMany
    {
        return $this->belongsToMany(ServicoEstetica::class, 'pacote_servico_estetica', 'pacote_id', 'servico_id');
    }
}
