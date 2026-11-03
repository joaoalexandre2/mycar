<?php

namespace App\Models;

use App\Models\Concerns\PertenceAConta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

/** Foto de uma ocorrência, antes ou depois do reparo. Servida por link assinado e temporário. */
class OcorrenciaFoto extends Model
{
    use PertenceAConta;

    public const MOMENTOS = ['antes', 'depois'];

    protected $table = 'ocorrencia_fotos';

    protected $fillable = ['ocorrencia_id', 'momento', 'caminho'];

    public function ocorrencia(): BelongsTo
    {
        return $this->belongsTo(Ocorrencia::class);
    }

    public function urlDaFoto(): string
    {
        return URL::temporarySignedRoute('ocorrencias.fotos.mostrar', now()->addHours(2), ['foto' => $this->id], absolute: false);
    }
}
