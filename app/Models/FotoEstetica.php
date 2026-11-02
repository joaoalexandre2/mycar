<?php

namespace App\Models;

use App\Models\Concerns\PertenceAConta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

/** Foto de check-in (como o carro chegou) ou de checkout (como o carro saiu). */
class FotoEstetica extends Model
{
    use PertenceAConta;

    public const UPDATED_AT = null;

    public const CHECKIN = 'checkin';

    public const CHECKOUT = 'checkout';

    public const MOMENTOS = [self::CHECKIN, self::CHECKOUT];

    protected $table = 'fotos_estetica';

    protected $fillable = ['atendimento_id', 'momento', 'caminho', 'user_id'];

    /** Link assinado e temporário para o navegador ver a foto (mesmo esquema do álbum da frota). */
    public function url(): string
    {
        return URL::temporarySignedRoute('estetica.fotos.mostrar', now()->addHours(2), ['foto' => $this->id], absolute: false);
    }
}
