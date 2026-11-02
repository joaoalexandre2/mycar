<?php

namespace App\Models;

use App\Models\Concerns\PertenceAConta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Checklist de saída ou de devolução de uma reserva. Um por momento, e só se acrescenta. */
class ChecklistUso extends Model
{
    use PertenceAConta;

    public const UPDATED_AT = null;

    public const SAIDA = 'saida';

    public const DEVOLUCAO = 'devolucao';

    public const MOMENTOS = [self::SAIDA, self::DEVOLUCAO];

    /** Os itens que o motorista confere. A chave é o que vai para o banco; o texto é o que ele vê. */
    public const ITENS = [
        'combustivel' => 'Nível de combustível',
        'pneus' => 'Pneus e estepe',
        'luzes' => 'Luzes e sinalização',
        'lataria' => 'Lataria sem avarias',
        'limpeza' => 'Limpeza interna',
        'documentos' => 'Documentos no veículo',
        'macaco' => 'Macaco e triângulo',
    ];

    protected $table = 'checklists_uso';

    protected $fillable = ['uso_veiculo_id', 'momento', 'itens', 'observacoes', 'feito_por_user_id', 'checklist_modelo_id'];

    protected static function booted(): void
    {
        // A contagem de problemas é gravada junto, para o alerta filtrar no banco.
        static::saving(function (ChecklistUso $c) {
            $c->problemas = collect($c->itens ?? [])->where('ok', false)->count();
        });
    }

    protected function casts(): array
    {
        return ['itens' => 'array'];
    }

    public function uso(): BelongsTo
    {
        return $this->belongsTo(UsoVeiculo::class, 'uso_veiculo_id');
    }

    public function modelo(): BelongsTo
    {
        return $this->belongsTo(ChecklistModelo::class, 'checklist_modelo_id');
    }
}
