<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/** Imagem anexada a uma sugestão. O arquivo fica no disco privado. */
class SugestaoAnexo extends Model
{
    public const LIMITE_POR_SUGESTAO = 3;

    protected $table = 'sugestao_anexos';

    protected $fillable = ['sugestao_id', 'caminho', 'mime', 'tamanho'];

    protected static function booted(): void
    {
        static::deleting(fn (SugestaoAnexo $anexo) => Storage::disk('local')->delete($anexo->caminho));
    }

    public function sugestao(): BelongsTo
    {
        return $this->belongsTo(Sugestao::class);
    }

    /** Link assinado, temporário e sem host (funciona com qualquer APP_URL). */
    public function urlAssinada(): string
    {
        return URL::temporarySignedRoute(
            'sugestoes.anexo',
            now()->addHours(2),
            ['anexo' => $this->id],
            absolute: false,
        );
    }
}
