<?php

namespace App\Models;

use App\Models\Concerns\PertenceAConta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

/** Nota fiscal, recibo ou orçamento de um serviço. Servido por link assinado e temporário. */
class ServicoAnexo extends Model
{
    use PertenceAConta;

    protected $table = 'servico_anexos';

    protected $fillable = ['servico_id', 'nome', 'caminho'];

    /** O caminho do disco não sai na resposta: a tela recebe só o link. */
    protected $hidden = ['caminho'];

    protected $appends = ['url'];

    public function servico(): BelongsTo
    {
        return $this->belongsTo(Servico::class);
    }

    public function getUrlAttribute(): string
    {
        return URL::temporarySignedRoute('servicos.anexos.mostrar', now()->addHours(2), ['anexo' => $this->id], absolute: false);
    }
}
