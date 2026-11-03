<?php

namespace App\Models;

use App\Models\Concerns\PertenceAConta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

class Abastecimento extends Model
{
    use PertenceAConta;

    public const COMBUSTIVEIS = ['gasolina', 'etanol', 'diesel', 'gnv', 'outro'];

    protected $table = 'abastecimentos';

    protected $fillable = [
        'veiculo_conta_id',
        'motorista_id',
        'data',
        'hora',
        'km',
        'litros',
        'valor_total',
        'tanque_cheio',
        'combustivel',
        'posto',
        'justificativa',
        'alertas',
        'comprovante_caminho',
        'hodometro_caminho',
    ];

    /** Caminhos do disco ficam fora da resposta: a tela recebe só os links assinados. */
    protected $hidden = ['comprovante_caminho', 'hodometro_caminho'];

    protected $appends = ['comprovante_url', 'hodometro_url'];

    protected $casts = [
        'data' => 'date:Y-m-d',
        'litros' => 'decimal:3',
        'valor_total' => 'decimal:2',
        'tanque_cheio' => 'boolean',
        'alertas' => 'array',
    ];

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(VeiculoConta::class, 'veiculo_conta_id');
    }

    public function motorista(): BelongsTo
    {
        return $this->belongsTo(Motorista::class);
    }

    /** Hora sempre como HH:MM, seja qual for o formato que o banco devolve. */
    public function getHoraAttribute($valor): ?string
    {
        return $valor ? substr((string) $valor, 0, 5) : null;
    }

    public function getComprovanteUrlAttribute(): ?string
    {
        return $this->comprovante_caminho ? $this->urlDaFoto('comprovante') : null;
    }

    public function getHodometroUrlAttribute(): ?string
    {
        return $this->hodometro_caminho ? $this->urlDaFoto('hodometro') : null;
    }

    private function urlDaFoto(string $tipo): string
    {
        return URL::temporarySignedRoute('abastecimentos.fotos.mostrar', now()->addHours(2), ['abastecimento' => $this->id, 'tipo' => $tipo], absolute: false);
    }
}
