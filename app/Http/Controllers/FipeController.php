<?php

namespace App\Http\Controllers;

use App\Services\FipeIndisponivelException;
use App\Services\FipeService;
use Closure;
use Illuminate\Http\Request;

class FipeController extends Controller
{
    public function __construct(private FipeService $fipe)
    {
    }

    public function marcas(Request $request)
    {
        $tipo = $this->tipo($request);

        return $this->responder(fn () => $this->fipe->marcas($tipo));
    }

    public function modelos(Request $request, int $marca)
    {
        $tipo = $this->tipo($request);

        return $this->responder(fn () => $this->fipe->modelos($tipo, $marca));
    }

    public function anos(Request $request, int $marca, int $modelo)
    {
        $tipo = $this->tipo($request);

        return $this->responder(fn () => $this->fipe->anos($tipo, $marca, $modelo));
    }

    public function valor(Request $request, int $marca, int $modelo, string $ano)
    {
        $tipo = $this->tipo($request);

        return $this->responder(fn () => $this->fipe->valor($tipo, $marca, $modelo, $ano));
    }

    private function tipo(Request $request): string
    {
        return $request->validate([
            'tipo' => ['sometimes', 'in:' . implode(',', FipeService::TIPOS)],
        ])['tipo'] ?? 'carros';
    }

    private function responder(Closure $consulta)
    {
        try {
            return response()->json($consulta());
        } catch (FipeIndisponivelException) {
            return response()->json([
                'message' => 'Não foi possível consultar a tabela FIPE no momento.',
            ], 502);
        }
    }
}
