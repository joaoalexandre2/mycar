<?php

namespace App\Http\Controllers;

use App\Models\VeiculoConta;
use App\Services\ConsolidacaoDeDespesas;
use Illuminate\Http\Request;

/**
 * Tudo que a conta gastou com a frota, consolidado: combustível, serviços, despesas avulsas,
 * multas e (à parte) o seguro. A regra fica em ConsolidacaoDeDespesas.
 * Isolado pela conta via PertenceAConta.
 */
class DespesaController extends Controller
{
    public function index(Request $request, ConsolidacaoDeDespesas $consolidacao)
    {
        $filtros = $request->validate([
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date', 'after_or_equal:de'],
            'veiculo_id' => ['nullable', 'integer'],
        ]);

        $veiculoId = $filtros['veiculo_id'] ?? null;

        if ($veiculoId !== null && !VeiculoConta::whereKey($veiculoId)->exists()) {
            return response()->json(['message' => 'Veículo não encontrado.'], 404);
        }

        return response()->json($consolidacao->consolidar($filtros['de'] ?? null, $filtros['ate'] ?? null, $veiculoId));
    }
}
