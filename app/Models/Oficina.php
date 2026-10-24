<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Oficina extends Model
{
    use HasFactory;

    protected $fillable = [
        'nome',
        'cnpj',
        'telefone',
        'endereco',
        'resumo_semanal',
    ];

    protected $casts = [
        'resumo_semanal' => 'boolean',
        'arquivada_em' => 'datetime',
    ];

    /**
     * Oficinas em funcionamento. As arquivadas (dono migrou de perfil) não
     * recebem avisos nem resumo e não entram nas contagens.
     */
    public function scopeAtivas(Builder $consulta): Builder
    {
        return $consulta->whereNull('arquivada_em');
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * E-mails de quem recebe o resumo desta oficina: quem a tem como MyCar
     * principal (e ainda usa o perfil oficina) e quem a tem como MyCar extra.
     *
     * @return list<string>
     */
    public function emailsParaAvisos(): array
    {
        $principais = $this->usuarios()
            ->where('perfil', 'oficina')
            ->whereNotNull('email_verified_at')
            ->pluck('email');

        $extras = User::whereNotNull('email_verified_at')
            ->whereHas('acessos', fn ($q) => $q->where('oficina_id', $this->id))
            ->pluck('email');

        return $principais->merge($extras)->unique()->values()->all();
    }

    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class);
    }
}
