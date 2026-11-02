<?php

namespace App\Models;

use App\Models\Concerns\PertenceAConta;
use App\Support\FusoDaFrota;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Acidente, avaria, dano ou problema de um veículo, com as fotos antes e depois do reparo.
 * Quando há valor gasto, a ocorrência gera (ou atualiza) uma despesa na gestão financeira.
 */
class Ocorrencia extends Model
{
    use PertenceAConta;

    public const TIPOS = [
        'acidente' => 'Acidente',
        'avaria' => 'Avaria',
        'dano' => 'Dano na carroceria',
        'mecanico' => 'Problema mecânico',
        'uso' => 'Problema durante o uso',
    ];

    public const STATUS = [
        'aberta' => 'Aberta',
        'em_analise' => 'Em análise',
        'aguardando_orcamento' => 'Aguardando orçamento',
        'em_reparo' => 'Em reparo',
        'concluida' => 'Concluída',
        'cancelada' => 'Cancelada',
    ];

    /** Ocorrências que ainda pedem ação (entram nos alertas). */
    public const ABERTAS = ['aberta', 'em_analise', 'aguardando_orcamento', 'em_reparo'];

    /** Categoria usada pela despesa que a ocorrência gera. */
    public const CATEGORIA_DESPESA = 'Reparo e peças';

    protected $table = 'ocorrencias';

    protected $fillable = [
        'veiculo_conta_id',
        'motorista_id',
        'uso_veiculo_id',
        'registrado_por_user_id',
        'tipo',
        'data_hora',
        'local',
        'descricao',
        'prejuizo_estimado',
        'valor_gasto',
        'status',
        'despesa_id',
    ];

    protected function casts(): array
    {
        return [
            'data_hora' => 'datetime',
            'prejuizo_estimado' => 'decimal:2',
            'valor_gasto' => 'decimal:2',
        ];
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(VeiculoConta::class, 'veiculo_conta_id');
    }

    public function motorista(): BelongsTo
    {
        return $this->belongsTo(Motorista::class);
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(OcorrenciaFoto::class)->orderBy('id');
    }

    /**
     * Mantém a despesa desta ocorrência em dia: cria quando há valor gasto, atualiza quando muda,
     * e apaga quando o valor some.
     */
    public function sincronizarDespesa(): void
    {
        $valor = (float) $this->valor_gasto;

        if ($valor <= 0) {
            if ($this->despesa_id) {
                Despesa::whereKey($this->despesa_id)->delete();
                $this->forceFill(['despesa_id' => null])->saveQuietly();
            }

            return;
        }

        $categoria = CategoriaDespesa::firstOrCreate(['nome' => self::CATEGORIA_DESPESA], ['ativo' => true]);

        $dados = [
            'categoria_id' => $categoria->id,
            'veiculo_conta_id' => $this->veiculo_conta_id,
            'motorista_id' => $this->motorista_id,
            'data' => FusoDaFrota::local($this->data_hora)->toDateString(),
            'valor' => $valor,
            'descricao' => 'Ocorrência #' . $this->id . ': ' . self::TIPOS[$this->tipo] . ' — ' . Str::limit((string) $this->descricao, 100),
        ];

        $despesa = $this->despesa_id ? Despesa::find($this->despesa_id) : null;

        if ($despesa) {
            $despesa->update($dados);

            return;
        }

        $this->forceFill(['despesa_id' => Despesa::create($dados)->id])->saveQuietly();
    }
}
