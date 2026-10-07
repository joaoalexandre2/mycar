<?php

namespace App\Models;

use App\Models\Concerns\PertenceAConta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/** Foto do álbum de um veículo de conta. Os arquivos ficam no disco privado. */
class FotoVeiculoConta extends Model
{
    use PertenceAConta;

    public const LIMITE_POR_VEICULO = 30;

    protected $table = 'fotos_veiculo_conta';

    protected $fillable = ['veiculo_conta_id', 'caminho', 'caminho_miniatura', 'mime', 'tamanho', 'legenda'];

    protected static function booted(): void
    {
        // Apagou a foto: apaga também os arquivos.
        static::deleting(function (FotoVeiculoConta $foto) {
            Storage::disk('local')->delete([$foto->caminho, $foto->caminho_miniatura]);
        });
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(VeiculoConta::class, 'veiculo_conta_id');
    }

    /**
     * Link assinado e temporário (relativo, sem host: funciona qualquer que seja o
     * APP_URL). O navegador usa em <img>, que não consegue mandar o token.
     */
    public function urlAssinada(string $tipo): string
    {
        return URL::temporarySignedRoute(
            'fotos.mostrar',
            now()->addHours(2),
            ['foto' => $this->id, 'tipo' => $tipo],
            absolute: false,
        );
    }
}
