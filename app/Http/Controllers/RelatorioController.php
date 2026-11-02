<?php

namespace App\Http\Controllers;

use App\Services\RelatoriosDaFrota;
use App\Support\FusoDaFrota;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Relatórios gerenciais da frota, em JSON (para a tela) e em CSV (para o Excel). Só administrador e gestor.
 */
class RelatorioController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(collect(RelatoriosDaFrota::CATALOGO)
            ->map(fn (array $relatorio, string $tipo) => ['tipo' => $tipo, 'nome' => $relatorio['nome'], 'descricao' => $relatorio['descricao']])
            ->values());
    }

    public function mostrar(Request $request, string $tipo, RelatoriosDaFrota $relatorios): JsonResponse
    {
        abort_unless($relatorios->existe($tipo), 404);

        $relatorio = $relatorios->gerar($tipo, $this->filtros($request), true);

        return response()->json($relatorio + ['tipo' => $tipo, 'total_linhas' => count($relatorio['linhas'])]);
    }

    /** CSV com separador ";", vírgula decimal e BOM, para abrir direto no Excel em português. */
    public function exportar(Request $request, string $tipo, RelatoriosDaFrota $relatorios): StreamedResponse
    {
        abort_unless($relatorios->existe($tipo), 404);

        $relatorio = $relatorios->gerar($tipo, $this->filtros($request), true);
        $arquivo = "relatorio-{$tipo}-" . now(FusoDaFrota::NOME)->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($relatorio) {
            $saida = fopen('php://output', 'w');

            fwrite($saida, "\xEF\xBB\xBF");
            fputcsv($saida, array_column($relatorio['colunas'], 'rotulo'), ';');

            foreach ($relatorio['linhas'] as $linha) {
                fputcsv($saida, array_map(
                    fn (array $coluna) => $this->celula($coluna['formato'], $linha[$coluna['chave']] ?? null),
                    $relatorio['colunas'],
                ), ';');
            }

            fclose($saida);
        }, $arquivo, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filtros(Request $request): array
    {
        return $request->validate([
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date', 'after_or_equal:de'],
            'veiculo_id' => ['nullable', 'integer'],
            'motorista_id' => ['nullable', 'integer'],
            'centro_custo_id' => ['nullable', 'integer'],
            'categoria_id' => ['nullable', 'integer'],
        ]);
    }

    private function celula(string $formato, mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        return match ($formato) {
            'data' => $this->dataBrasileira((string) $valor),
            'moeda', 'numero' => number_format((float) $valor, 2, ',', ''),
            'inteiro' => (string) (int) $valor,
            default => (string) $valor,
        };
    }

    private function dataBrasileira(string $valor): string
    {
        try {
            return Carbon::parse($valor)->format('d/m/Y');
        } catch (\Throwable) {
            return $valor;
        }
    }
}
