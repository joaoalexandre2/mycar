<?php

namespace App\Models;

use App\Models\Concerns\CalculaTributosVeiculo;
use App\Models\Concerns\PertenceAConta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Veículo de uma conta (pessoa ou frota). Os carros dos clientes de uma
 * oficina continuam em Veiculo.
 */
class VeiculoConta extends Model
{
    use CalculaTributosVeiculo, PertenceAConta;

    protected $table = 'veiculos_conta';

    protected $fillable = [
        'apelido',
        'placa',
        'marca',
        'modelo',
        'ano',
        'uf',
        'revisao_prevista_em',
        'fipe_marca_id',
        'fipe_modelo_id',
        'fipe_ano',
        'fipe_valor',
        'fipe_consultado_em',
    ];

    protected $casts = [
        'fipe_valor' => 'decimal:2',
        'fipe_consultado_em' => 'datetime',
        'revisao_prevista_em' => 'date:Y-m-d',
        'revisao_alertada_em' => 'date:Y-m-d',
    ];

    /** Controle interno dos lembretes: não precisa ir para o frontend. */
    protected $hidden = [
        'ipva_alertado_ano',
        'licenciamento_alertado_ano',
        'revisao_alertada_em',
    ];

    protected $appends = [
        'ipva_estimado',
        'licenciamento_valor',
        'proximo_vencimento_ipva',
        'proximo_vencimento_licenciamento',
    ];

    protected static function booted(): void
    {
        // Mudou a data da revisão? O aviso antigo não vale para a nova data.
        static::saving(function (VeiculoConta $veiculo) {
            if ($veiculo->isDirty('revisao_prevista_em')) {
                $veiculo->revisao_alertada_em = null;
            }
        });
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class, 'veiculo_conta_id');
    }

    /**
     * CRLV mais recente com data de vencimento. Quando existe, a data real
     * vale no lugar da estimativa de licenciamento pela placa.
     */
    public function crlvAtual(): ?Documento
    {
        return $this->documentos()
            ->where('tipo', 'crlv')
            ->whereNotNull('vencimento')
            ->orderByDesc('vencimento')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Documentos com vencimento que valem para avisos: de CRLV só o mais
     * recente (os anteriores são histórico).
     *
     * @return \Illuminate\Support\Collection<int, Documento>
     */
    public function documentosComVencimento()
    {
        $crlvId = $this->crlvAtual()?->id;

        return $this->documentos()
            ->whereNotNull('vencimento')
            ->get()
            ->filter(fn (Documento $d) => $d->tipo !== 'crlv' || $d->id === $crlvId)
            ->values();
    }

    public function seguros(): HasMany
    {
        return $this->hasMany(Seguro::class, 'veiculo_conta_id');
    }

    public function abastecimentos(): HasMany
    {
        return $this->hasMany(Abastecimento::class, 'veiculo_conta_id');
    }
}
