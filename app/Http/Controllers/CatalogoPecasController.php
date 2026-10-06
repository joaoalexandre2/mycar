<?php

namespace App\Http\Controllers;

use App\Models\VeiculoConta;
use App\Services\CatalogoPecas;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Catálogo de peças (perfis pessoa e frota): com um veículo da conta, traz as
 * peças do modelo dele; sem veículo, o catálogo inteiro. Só leitura.
 */
class CatalogoPecasController extends Controller
{
    public function index(Request $request, CatalogoPecas $catalogo)
    {
        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'veiculo_id' => ['nullable', 'integer'],
            'sistema' => ['nullable', Rule::in(array_keys(config('pecas_catalogo.sistemas')))],
        ]);

        $busca = $filtros['q'] ?? '';
        $veiculo = null;
        $modelo = null;
        $escopo = 'catalogo';
        $categoria = null;

        if (!empty($filtros['veiculo_id'])) {
            $veiculo = VeiculoConta::find($filtros['veiculo_id']);

            if (!$veiculo) {
                return response()->json(['message' => 'Veículo não encontrado.'], 404);
            }

            $modelo = $catalogo->modeloDoVeiculo($veiculo->marca, $veiculo->modelo);
            $escopo = $modelo ? 'modelo' : 'geral';
            $categoria = $modelo ? $modelo['categoria'] : 'universal';
        }

        // Os contadores por sistema respeitam a busca, mas não o filtro de sistema.
        $porSistema = collect($catalogo->pecas($categoria, $busca))->countBy('sistema');
        $pecas = $catalogo->pecas($categoria, $busca, $filtros['sistema'] ?? null);

        return response()->json([
            'veiculo' => $veiculo ? [
                'id' => $veiculo->id,
                'nome' => trim($veiculo->apelido ?: "{$veiculo->marca} {$veiculo->modelo}"),
                'placa' => $veiculo->placa,
            ] : null,
            'modelo' => $modelo,
            'escopo' => $escopo,
            'total' => count($pecas),
            'sistemas' => collect(config('pecas_catalogo.sistemas'))
                ->map(fn ($rotulo, $chave) => ['chave' => $chave, 'rotulo' => $rotulo, 'total' => $porSistema->get($chave, 0)])
                ->values(),
            'pecas' => $pecas,
            'modelos_no_catalogo' => count(config('pecas_catalogo.modelos')),
            'aviso' => CatalogoPecas::AVISO,
        ]);
    }
}
