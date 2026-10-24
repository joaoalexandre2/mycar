<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Inquilino dos perfis "Cuidados com seu carro" (tipo pessoa) e "Frota"
 * (tipo frota). Não tem relação com Oficina: são mundos separados.
 */
class Conta extends Model
{
    public const TIPO_PESSOA = 'pessoa';

    public const TIPO_FROTA = 'frota';

    public const TIPOS = [self::TIPO_PESSOA, self::TIPO_FROTA];

    protected $fillable = [
        'tipo',
        'nome',
        'lembretes_email',
    ];

    protected $casts = [
        'lembretes_email' => 'boolean',
    ];

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * E-mails de quem recebe os avisos desta conta: quem a tem como MyCar
     * principal (e ainda usa um perfil de conta) e quem a tem como MyCar extra.
     *
     * @return list<string>
     */
    public function emailsParaAvisos(): array
    {
        $principais = $this->usuarios()
            ->whereIn('perfil', self::TIPOS)
            ->whereNotNull('email_verified_at')
            ->pluck('email');

        $extras = User::whereNotNull('email_verified_at')
            ->whereHas('acessos', fn ($q) => $q->where('conta_id', $this->id))
            ->pluck('email');

        return $principais->merge($extras)->unique()->values()->all();
    }
}
