<?php

namespace App\Models;

use App\Models\Concerns\PertenceAConta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Um carro no pátio da estética, do check-in à entrega. A etapa segue a ordem de ETAPAS:
 * entrada, em atendimento, entrega e entregue (a entrega só fecha com foto de checkout).
 */
class AtendimentoEstetica extends Model
{
    use PertenceAConta;

    public const ENTRADA = 'entrada';

    public const ATENDIMENTO = 'atendimento';

    public const ENTREGA = 'entrega';

    public const ENTREGUE = 'entregue';

    public const ETAPAS = [self::ENTRADA, self::ATENDIMENTO, self::ENTREGA, self::ENTREGUE];

    public const FORMAS = ['pix', 'cartao', 'dinheiro', 'boleto'];

    protected $table = 'atendimentos_estetica';

    protected $fillable = [
        'veiculo_conta_id',
        'pacote_id',
        'placa',
        'modelo',
        'cliente_nome',
        'cliente_telefone',
        'etapa',
        'valor',
        'forma_pagamento',
        'pago',
        'entrada_em',
        'previsao_entrega',
        'entregue_em',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'pago' => 'boolean',
            'entrada_em' => 'datetime',
            'previsao_entrega' => 'datetime',
            'entregue_em' => 'datetime',
        ];
    }

    /** Placa só com letras e números, em maiúsculas: "abc-1d23" vira "ABC1D23". */
    public static function normalizarPlaca(string $placa): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $placa) ?? '');
    }

    public function etapaSeguinte(): ?string
    {
        $indice = array_search($this->etapa, self::ETAPAS, true);

        return $indice === false || $indice >= count(self::ETAPAS) - 1 ? null : self::ETAPAS[$indice + 1];
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(VeiculoConta::class, 'veiculo_conta_id');
    }

    public function pacote(): BelongsTo
    {
        return $this->belongsTo(PacoteEstetica::class, 'pacote_id');
    }

    public function servicos(): HasMany
    {
        return $this->hasMany(AtendimentoServicoEstetica::class, 'atendimento_id');
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(FotoEstetica::class, 'atendimento_id');
    }

    /** Soma dos serviços cobrados, ou o valor informado quando não há serviços lançados. */
    public function totalDosServicos(): float
    {
        $soma = (float) $this->servicos->sum('preco');

        return $soma > 0 ? $soma : (float) $this->valor;
    }

    /** Dias desde a entrega (ou a entrada, se ainda não entregue) enquanto a conta não está paga. */
    public function diasEmAberto(): ?int
    {
        if ($this->pago) {
            return null;
        }

        return (int) ($this->entregue_em ?? $this->entrada_em)->diffInDays(now());
    }
}
