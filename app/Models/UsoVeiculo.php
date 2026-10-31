<?php

namespace App\Models;

use App\Models\Concerns\PertenceAConta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    /** De onde veio o km informado: lido junto de uma foto ou digitado sem foto. */
    public const ORIGENS_KM = ['foto', 'manual'];

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
        'km_saida_origem',
        'km_chegada_origem',
        'saida_em',
        'chegada_em',
        'saida_por_user_id',
        'chegada_por_user_id',
        'origem',
        'observacoes',
    ];

    protected $casts = [
        'inicio_previsto' => 'datetime',
        'fim_previsto' => 'datetime',
        'saida_em' => 'datetime',
        'chegada_em' => 'datetime',
    ];

    /**
     * "de 26/10 08:00 a 26/10 18:00 (finalidade: visita; centro de custo: Compras)":
     * o horário do uso e o que se sabe sobre ele, para as mensagens de conflito
     * dizerem com quem está o veículo (e não só quando).
     */
    public function janelaEDetalhes(string $formato = 'd/m H:i'): string
    {
        $janela = 'de ' . $this->inicio_previsto->format($formato) . ' a ' . $this->fim_previsto->format($formato);

        $detalhes = array_filter([
            $this->finalidade ? "finalidade: {$this->finalidade}" : null,
            $this->centroCusto ? "centro de custo: {$this->centroCusto->nome}" : null,
        ]);

        return $detalhes ? "{$janela} (" . implode('; ', $detalhes) . ')' : $janela;
    }

    /** Nome de quem vai usar (ou usa) o veículo neste uso. */
    public function quemUsa(): string
    {
        return $this->motorista?->nome ?? 'sem motorista definido';
    }

    protected static function booted(): void
    {
        // Apagou o uso: apaga as fotos do hodômetro também, inclusive as versões substituídas.
        static::deleting(function (UsoVeiculo $uso) {
            $versoes = $uso->fotos()->pluck('caminho')->all();

            Storage::disk('local')->delete(array_filter(array_merge([$uso->foto_saida, $uso->foto_chegada], $versoes)));
        });
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(VeiculoConta::class, 'veiculo_conta_id');
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(UsoVeiculoFoto::class, 'uso_veiculo_id');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(UsoVeiculoEvento::class, 'uso_veiculo_id');
    }

    public function centrosHistorico(): HasMany
    {
        return $this->hasMany(UsoVeiculoCentro::class, 'uso_veiculo_id');
    }

    /** Grava no histórico (e na trilha de auditoria) a troca do centro de custo que acabou de ser feita. */
    public function registrarTrocaDeCentro(?int $anteriorId, ?int $userId, ?string $motivo = null): UsoVeiculoCentro
    {
        $troca = UsoVeiculoCentro::create([
            'uso_veiculo_id' => $this->id,
            'veiculo_conta_id' => $this->veiculo_conta_id,
            'centro_anterior_id' => $anteriorId,
            'centro_anterior_nome' => $anteriorId ? CentroCusto::find($anteriorId)?->nome : null,
            'centro_novo_id' => $this->centro_custo_id,
            'centro_novo_nome' => $this->centro_custo_id ? CentroCusto::find($this->centro_custo_id)?->nome : null,
            'user_id' => $userId,
            'motivo' => $motivo,
        ]);

        UsoVeiculoEvento::registrar($this, 'trocou_centro', $userId, [
            'antes' => $troca->centro_anterior_nome,
            'depois' => $troca->centro_novo_nome,
        ], $motivo);

        return $troca;
    }

    public function saidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saida_por_user_id');
    }

    public function chegadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chegada_por_user_id');
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
