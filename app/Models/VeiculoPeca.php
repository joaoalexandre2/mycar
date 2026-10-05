<?php

namespace App\Models;

use App\Models\Concerns\PertenceAOficina;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VeiculoPeca extends Model
{
    use PertenceAOficina;

    protected $table = 'veiculo_pecas';

    public const TIPOS = [
        'pastilha_freio' => 'Pastilha de freio',
        'disco_freio' => 'Disco de freio',
        'correia' => 'Correia (dentada / poly-V)',
        'velas' => 'Velas',
        'bateria' => 'Bateria',
        'amortecedor' => 'Amortecedor',
        'palheta' => 'Palheta',
        'lampada' => 'Lâmpada',
    ];

    public const FONTES = ['ficha', 'servico'];

    protected $fillable = [
        'veiculo_id',
        'manutencao_id',
        'tipo',
        'especificacao',
        'marca',
        'fonte',
        'usado_em',
        'observacao',
    ];

    protected $casts = [
        'usado_em' => 'date',
    ];

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }

    public function manutencao(): BelongsTo
    {
        return $this->belongsTo(Manutencao::class);
    }
}
