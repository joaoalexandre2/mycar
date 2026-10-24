<?php

namespace App\Models;

use App\Enums\TipoVeiculo;
use App\Models\Concerns\CalculaTributosVeiculo;
use App\Models\Concerns\PertenceAOficina;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Veiculo extends Model
{
    use CalculaTributosVeiculo, HasFactory, PertenceAOficina;

    /** Quem não informa o tipo é carro (igual ao padrão do banco). */
    protected $attributes = [
        'tipo_veiculo' => 'carro',
    ];

    protected $fillable = [
        'cliente_id',
        'tipo_veiculo',
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
        'tipo_veiculo' => TipoVeiculo::class,
        'fipe_valor' => 'decimal:2',
        'fipe_consultado_em' => 'datetime',
    ];

    protected $appends = [
        'ano_completo',
        'idade_anos',
        'possivel_isencao_ipva',
        'isento_ipva',
        'ipva_estimado',
        'ipva_aliquota_media',
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
