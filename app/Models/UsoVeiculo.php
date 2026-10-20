<?php

namespace App\Models;

use App\Models\Concerns\PertenceAConta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Uso de um veículo da frota: a reserva na agenda (quem vai usar, quando), a
 * saída e a chegada com o km do hodômetro e a foto do painel.
 *
 * agendado -> em_uso (saída) -> concluido (chegada). Cancelado sai da agenda.
 */
class UsoVeiculo extends Model
{
    use PertenceAConta;

    public const STATUS = ['agendado', 'em_uso', 'concluido', 'cancelado'];

    /** Só estes bloqueiam o veículo e o motorista no horário. */
    public const OCUPAM_HORARIO = ['agendado', 'em_uso'];

    public const MOMENTOS = ['saida', 'chegada'];

    protected $table = 'usos_veiculo';

    protected $fillable = [
        'veiculo_conta_id',
        'motorista_id',
        'centro_custo_id',
        'inicio_previsto',
        'fim_previsto',
        'finalidade',
        'status',
        'km_saida',
        'km_chegada',
        'saida_em',
        'chegada_em',
        'origem',
        'observacoes',
    ];

    protected $casts = [
        'inicio_previsto' => 'datetime',
        'fim_previsto' => 'datetime',
        'saida_em' => 'datetime',
        'chegada_em' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Apagou o uso: apaga as fotos do hodômetro também.
        static::deleting(function (UsoVeiculo $uso) {
            Storage::disk('local')->delete(array_filter([$uso->foto_saida, $uso->foto_chegada]));
        });
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(VeiculoConta::class, 'veiculo_conta_id');
    }

    public function motorista(): BelongsTo
    {
        return $this->belongsTo(Motorista::class);
    }

    public function centroCusto(): BelongsTo
    {
        return $this->belongsTo(CentroCusto::class);
    }

    /** Usos que ocupam o horário e se sobrepõem à janela [inicio, fim). */
    public function scopeSobrepostos(Builder $query, $inicio, $fim): Builder
    {
        return $query->whereIn('status', self::OCUPAM_HORARIO)
            ->where('inicio_previsto', '<', $fim)
            ->where('fim_previsto', '>', $inicio);
    }

    /** Km rodado no uso, só quando saída e chegada foram informadas. */
    public function kmRodados(): ?int
    {
        return $this->km_saida !== null && $this->km_chegada !== null
            ? $this->km_chegada - $this->km_saida
            : null;
    }

    /** Caminho do arquivo da foto do hodômetro naquele momento (saida|chegada). */
    public function caminhoDaFoto(string $momento): ?string
    {
        return $momento === 'saida' ? $this->foto_saida : $this->foto_chegada;
    }

    /** Link assinado e temporário (relativo) para ver a foto no navegador. */
    public function urlDaFoto(string $momento): ?string
    {
        if (!$this->caminhoDaFoto($momento)) {
            return null;
        }

        return URL::temporarySignedRoute(
            'hodometros.mostrar',
            now()->addHours(2),
            ['uso' => $this->id, 'momento' => $momento],
            absolute: false,
        );
    }
}
