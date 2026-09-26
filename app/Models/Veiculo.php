<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\OrdemServico;
use App\Models\Manutencao;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class Veiculo extends Model
{
    use HasFactory;

    protected $fillable = [
        'cliente_id',
        'placa',
        'marca',
        'modelo',
        'ano',
        'uf',
        'fipe_marca_id',
        'fipe_modelo_id',
        'fipe_ano',
        'fipe_valor',
        'fipe_consultado_em',
    ];

    protected $casts = [
        'fipe_valor' => 'decimal:2',
        'fipe_consultado_em' => 'datetime',
    ];

    protected $appends = [
        'ipva_estimado',
        'licenciamento_valor',
    ];

    /**
     * IPVA estimado = valor FIPE × alíquota do estado (config/tributos.php).
     * Null quando falta o estado, o valor FIPE ou a alíquota do estado.
     */
    public function getIpvaEstimadoAttribute(): ?float
    {
        $aliquota = config("tributos.estados.{$this->uf}.ipva");

        if ($this->fipe_valor === null || $aliquota === null) {
            return null;
        }

        return round((float) $this->fipe_valor * $aliquota / 100, 2);
    }

    public function getLicenciamentoValorAttribute(): ?float
    {
        return config("tributos.estados.{$this->uf}.licenciamento");
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

     public function ordensServico(): HasMany
    {
        return $this->hasMany(OrdemServico::class);
    }

    public function manutencoes(): HasMany
{
    return $this->hasMany(Manutencao::class);
}
}
