<?php

namespace App\Models;

use App\Models\Concerns\PertenceAConta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Motorista da frota. Pode ser só um cadastro (user_id vazio) ou alguém com
 * login no sistema (user_id preenchido, quando o convite existir).
 */
class Motorista extends Model
{
    use PertenceAConta;

    protected $table = 'motoristas';

    protected $fillable = ['nome', 'telefone', 'cnh_numero', 'cnh_validade', 'ativo'];

    protected function casts(): array
    {
        return ['cnh_validade' => 'date:Y-m-d', 'ativo' => 'boolean'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function usos(): HasMany
    {
        return $this->hasMany(UsoVeiculo::class);
    }

    /**
     * Motivo de não poder dirigir na data (null = liberado). Motorista sem CNH
     * cadastrada também fica bloqueado: não há como provar a validade.
     */
    public function impedimentoParaDirigir(\DateTimeInterface $em): ?string
    {
        if (!$this->ativo) {
            return "O motorista {$this->nome} está desativado.";
        }

        if ($this->cnh_validade === null) {
            return "O motorista {$this->nome} não tem a validade da CNH cadastrada.";
        }

        if ($this->cnh_validade->toDateString() < $em->format('Y-m-d')) {
            return "A CNH de {$this->nome} vence em {$this->cnh_validade->format('d/m/Y')}, antes do uso.";
        }

        return null;
    }

    public function emUso(): bool
    {
        return $this->usos()->exists();
    }
}
