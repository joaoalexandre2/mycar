<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\OrdemServico;
use App\Models\Manutencao;
use App\Models\Concerns\PertenceAOficina;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class Veiculo extends Model
{
    use HasFactory, PertenceAOficina;

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
        'proximo_vencimento_licenciamento',
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

    /**
     * Último dígito da placa, usado pelo calendário de licenciamento
     * (config/licenciamento.php). Formatos antigo e Mercosul sempre
     * terminam em número.
     */
    public function getFinalPlacaAttribute(): ?int
    {
        $ultimoCaractere = substr((string) $this->placa, -1);

        return ctype_digit($ultimoCaractere) ? (int) $ultimoCaractere : null;
    }

    /**
     * Próxima data de vencimento do licenciamento (CRLV), estimada a
     * partir do final da placa (config/licenciamento.php). É sempre o
     * último dia do mês de referência, no ano corrente ou no próximo caso
     * a data deste ano já tenha passado. ESTIMATIVA — ver aviso no config.
     */
    public function getProximoVencimentoLicenciamentoAttribute(): ?string
    {
        $mes = config("licenciamento.meses_por_final_placa.{$this->final_placa}");

        if ($mes === null) {
            return null;
        }

        $vencimento = now()->setDate(now()->year, $mes, 1)->endOfMonth();

        if ($vencimento->isPast()) {
            $vencimento = $vencimento->addYear();
        }

        return $vencimento->toDateString();
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
