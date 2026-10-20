<?php

namespace App\Models;

use App\Models\Concerns\PertenceAConta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Centro de custos da frota (ex.: Vendas, Entregas). Isolado por conta. */
class CentroCusto extends Model
{
    use PertenceAConta;

    protected $table = 'centros_custo';

    protected $fillable = ['nome', 'codigo', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    public function usos(): HasMany
    {
        return $this->hasMany(UsoVeiculo::class);
    }

    public function veiculos(): HasMany
    {
        return $this->hasMany(VeiculoConta::class);
    }

    /** Tem histórico ou veículo ligado: nesse caso desativa em vez de apagar. */
    public function emUso(): bool
    {
        return $this->usos()->exists() || $this->veiculos()->exists();
    }
}
