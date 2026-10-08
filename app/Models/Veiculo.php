<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\OrdemServico;
use App\Models\Manutencao;
use App\Models\Concerns\CalculaTributosVeiculo;
use App\Models\Concerns\PertenceAOficina;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class Veiculo extends Model
{
    use CalculaTributosVeiculo, HasFactory, PertenceAOficina;

    protected $fillable = [
        'cliente_id',
        'placa',
        'marca',
        'modelo',
        'ano',
        'ano_fabricacao',
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
        'ano_completo',
        'idade_anos',
        'possivel_isencao_ipva',
        'ipva_estimado',
        'licenciamento_valor',
        'proximo_vencimento_licenciamento',
    ];

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

    public function historicoFipe(): HasMany
    {
        return $this->hasMany(FipeHistorico::class)->orderBy('consultado_em');
    }

    public function pecas(): HasMany
    {
        return $this->hasMany(VeiculoPeca::class);
    }
}
