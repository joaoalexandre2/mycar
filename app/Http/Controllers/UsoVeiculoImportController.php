<?php

namespace App\Http\Controllers;

use App\Services\ImportadorUsosVeiculo;
use Illuminate\Http\Request;

/** Planilha de utilização dos veículos (frota): modelo para baixar e importação em CSV. */
class UsoVeiculoImportController extends Controller
{
    public function modelo()
    {
        return response(ImportadorUsosVeiculo::modeloCsv(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="planilha-de-utilizacao.csv"',
        ]);
    }

    public function importar(Request $request, ImportadorUsosVeiculo $importador)
    {
        $dados = $request->validate([
            'arquivo' => ['required', 'file', 'max:2048', 'extensions:csv,txt'],
            'simular' => ['sometimes', 'boolean'],
            'criar_faltantes' => ['sometimes', 'boolean'],
        ], [
            'arquivo.extensions' => 'Envie a planilha salva como CSV.',
            'arquivo.max' => 'A planilha é grande demais (máximo 2 MB).',
        ]);

        $conteudo = (string) file_get_contents($request->file('arquivo')->getRealPath());

        // Planilhas salvas pelo Excel antigo vêm em Windows-1252: converte para UTF-8.
        if (!mb_check_encoding($conteudo, 'UTF-8')) {
            $conteudo = mb_convert_encoding($conteudo, 'UTF-8', 'Windows-1252');
        }

        $resultado = $importador->importar(
            $conteudo,
            (bool) ($dados['simular'] ?? false),
            (bool) ($dados['criar_faltantes'] ?? false),
        );

        if ($resultado['erros']) {
            return response()->json([
                'message' => count($resultado['erros']) . ' problema(s) na planilha. Nada foi importado.',
                'erros' => array_slice($resultado['erros'], 0, 50),
                'total_de_erros' => count($resultado['erros']),
            ], 422);
        }

        return response()->json($resultado['resumo']);
    }
}
