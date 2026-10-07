<?php

namespace App\Http\Controllers;

use App\Services\Precos\ComparadorPrecos;
use Illuminate\Http\Request;

/**
 * Comparador de preços (perfis pessoa e frota): busca o produto e devolve as
 * 3 ofertas mais baratas. A consulta é montada pela tela (peça + veículo, ou
 * a medida do pneu da ficha técnica).
 */
class ComparadorController extends Controller
{
    public function index(Request $request, ComparadorPrecos $comparador)
    {
        $dados = $request->validate([
            'q' => ['required', 'string', 'min:3', 'max:120'],
        ], [], ['q' => 'busca']);

        return response()->json($comparador->comparar($dados['q']));
    }
}
