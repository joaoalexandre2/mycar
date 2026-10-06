<?php

namespace App\Models;

use App\Models\Concerns\PertenceAConta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Serviço feito no veículo de uma conta (troca de óleo, bateria...), com o
 * aviso da próxima vez por prazo e/ou quilometragem.
 */
class Servico extends Model
{
    use PertenceAConta;

    protected $table = 'servicos_conta';

    protected $fillable = [
        'veiculo_conta_id',
        'tipo',
        'titulo',
        'realizado_em',
        'km',
        'valor',
        'observacoes',
        'intervalo_meses',
        'intervalo_km',
    ];

    protected $casts = [
        'realizado_em' => 'date:Y-m-d',
        'proximo_em' => 'date:Y-m-d',
        'valor' => 'decimal:2',
        'alerta_data_em' => 'date:Y-m-d',
        'alerta_km_em' => 'date:Y-m-d',
    ];

    protected $hidden = ['alerta_data_em', 'alerta_km_em'];

    protected $appends = ['rotulo'];

    protected static function booted(): void
    {
        // A próxima vez é sempre derivada do que foi informado.
        static::saving(function (Servico $servico) {
            $servico->proximo_em = $servico->intervalo_meses
                ? Carbon::parse($servico->realizado_em)->addMonthsNoOverflow($servico->intervalo_meses)->toDateString()
                : null;

            $servico->proxima_km = ($servico->intervalo_km && $servico->km !== null)
                ? $servico->km + $servico->intervalo_km
                : null;
        });
    }

    public function getRotuloAttribute(): string
    {
        if ($this->tipo === 'outro') {
            return $this->titulo ?: 'Serviço';
        }

        return config("servicos.tipos.{$this->tipo}", 'Serviço');
    }

    /** Serviços do mesmo tipo (e mesmo nome, no "outro") se substituem: vale o mais recente. */
    public function chave(): string
    {
        return $this->veiculo_conta_id.'|'.$this->tipo.'|'.($this->tipo === 'outro' ? mb_strtolower(trim((string) $this->titulo)) : '');
    }

    /**
     * Km que faltam para a próxima troca (negativo = passou), ou null sem
     * critério de km ou sem saber o km atual.
     */
    public function kmRestante(?int $kmAtual): ?int
    {
        if ($this->proxima_km === null || $kmAtual === null) {
            return null;
        }

        return $this->proxima_km - $kmAtual;
    }

    public function diasRestantes(Carbon $hoje): ?int
    {
        return $this->proximo_em ? (int) $hoje->copy()->startOfDay()->diffInDays($this->proximo_em->copy()->startOfDay(), false) : null;
    }

    /**
     * @return 'sem_aviso'|'em_dia'|'vence_em_breve'|'vencido'
     */
    public function situacao(?int $kmAtual, Carbon $hoje): string
    {
        $dias = $this->diasRestantes($hoje);
        $km = $this->kmRestante($kmAtual);

        if ($dias === null && $this->proxima_km === null) {
            return 'sem_aviso';
        }

        if (($dias !== null && $dias < 0) || ($km !== null && $km <= 0)) {
            return 'vencido';
        }

        if (($dias !== null && $dias <= config('servicos.margem_dias'))
            || ($km !== null && $km <= config('servicos.margem_km'))) {
            return 'vence_em_breve';
        }

        return 'em_dia';
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(VeiculoConta::class, 'veiculo_conta_id');
    }
}
