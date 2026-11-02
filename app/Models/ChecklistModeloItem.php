<?php

namespace App\Models;

use App\Models\Concerns\PertenceAConta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistModeloItem extends Model
{
    use PertenceAConta;

    protected $table = 'checklist_modelo_itens';

    protected $fillable = ['checklist_modelo_id', 'chave', 'rotulo', 'obrigatorio', 'ordem'];

    protected function casts(): array
    {
        return ['obrigatorio' => 'boolean'];
    }

    public function modelo(): BelongsTo
    {
        return $this->belongsTo(ChecklistModelo::class, 'checklist_modelo_id');
    }
}
