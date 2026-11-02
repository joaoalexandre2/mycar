<?php

namespace App\Models;

use App\Models\Concerns\PertenceAConta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Multa de trânsito de um veículo da frota, com a reserva e o motorista que estavam com ele. */
class Multa extends Model
{
    use PertenceAConta;

    public const ABERTA = 'aberta';

    public const PAGA = 'paga';

    public const RECORRIDA = 'recorrida';

    public const SITUACOES = [self::ABERTA, self::PAGA, self::RECORRIDA];

    protected $table = 'multas';

    protected $fillable = [
        'veiculo_conta_id',
        'uso_veiculo_id',
        'motorista_id',
        'data_hora',
        'descricao',
        'valor',
        'pontos',
        'situacao',
        'observacoes',
    ];

    protected function casts(): array
    {
        return ['data_hora' => 'datetime', 'valor' => 'decimal:2'];
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(VeiculoConta::class, 'veiculo_conta_id');
    }

    public function uso(): BelongsTo
    {
        return $this->belongsTo(UsoVeiculo::class, 'uso_veiculo_id');
    }

    public function motorista(): BelongsTo
    {
        return $this->belongsTo(Motorista::class, 'motorista_id');
    }

    /**
     * A reserva que estava com o veículo na data e hora da infração: a de saída e chegada reais,
     * ou a prevista quando ainda não houve saída. Cancelada não conta.
     */
    public static function usoNaData(int $veiculoId, \DateTimeInterface $momento): ?UsoVeiculo
    {
        return UsoVeiculo::query()
            ->where('veiculo_conta_id', $veiculoId)
            ->where('status', '!=', 'cancelado')
            ->where(function ($q) use ($momento) {
                $q->where(function ($r) use ($momento) {
                    $r->whereNotNull('saida_em')->where('saida_em', '<=', $momento)
                        ->where(fn ($s) => $s->whereNull('chegada_em')->orWhere('chegada_em', '>=', $momento));
                })->orWhere(function ($r) use ($momento) {
                    $r->whereNull('saida_em')->where('inicio_previsto', '<=', $momento)->where('fim_previsto', '>=', $momento);
                });
            })
            ->with('motorista')
            ->orderByDesc('inicio_previsto')
            ->first();
    }
}
