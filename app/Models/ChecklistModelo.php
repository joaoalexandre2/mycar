<?php

namespace App\Models;

use App\Models\Concerns\PertenceAConta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Modelo de checklist da empresa: os itens que o motorista confere na saída, na devolução ou nos dois.
 * Cada empresa configura os seus, sem mexer no código.
 */
class ChecklistModelo extends Model
{
    use PertenceAConta;

    public const AMBOS = 'ambos';

    public const MOMENTOS = [ChecklistUso::SAIDA, ChecklistUso::DEVOLUCAO, self::AMBOS];

    protected $table = 'checklist_modelos';

    protected $fillable = ['nome', 'momento', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    public function itens(): HasMany
    {
        return $this->hasMany(ChecklistModeloItem::class, 'checklist_modelo_id')->orderBy('ordem')->orderBy('id');
    }

    /** O modelo serve ao momento se for dele ou se valer para os dois. */
    public function serveAo(string $momento): bool
    {
        return $this->momento === $momento || $this->momento === self::AMBOS;
    }

    /** Cria o modelo padrão (os itens de sempre) na primeira vez que a empresa abre os checklists. */
    public static function garantirPadrao(): void
    {
        if (static::query()->exists()) {
            return;
        }

        $modelo = static::create(['nome' => 'Padrão da frota', 'momento' => self::AMBOS, 'ativo' => true]);

        foreach (array_values(ChecklistUso::ITENS) as $ordem => $rotulo) {
            $chave = array_keys(ChecklistUso::ITENS)[$ordem];
            $modelo->itens()->create(['chave' => $chave, 'rotulo' => $rotulo, 'obrigatorio' => true, 'ordem' => $ordem]);
        }
    }

    /** Chave estável a partir do rótulo, única dentro do modelo ("Nível de óleo" -> "nivel-de-oleo"). */
    public static function chaveDoRotulo(string $rotulo, array $jaUsadas): string
    {
        $base = Str::limit(Str::slug($rotulo), 36, '');
        $chave = $base !== '' ? $base : 'item';
        $sufixo = 2;

        while (in_array($chave, $jaUsadas, true)) {
            $chave = $base . '-' . $sufixo++;
        }

        return $chave;
    }
}
