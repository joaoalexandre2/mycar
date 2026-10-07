<?php

namespace App\Http\Controllers;

use App\Models\FichaTecnica;
use App\Models\FichaTecnicaConta;
use App\Models\VeiculoConta;
use App\Services\CatalogoPecas;
use App\Services\EspecificacoesDoNome;
use App\Services\FichasModelos;
use App\Services\FipeIndisponivelException;
use App\Services\FipeService;
use Illuminate\Http\Request;

/**
 * Ficha técnica de um veículo da conta (pessoa e frota), em três partes:
 * o que a tabela FIPE informa, o que está escrito no nome da versão, e a
 * ficha de manutenção que o dono preenche. Não inventa dado: sem fonte, vem
 * vazio e a tela indica onde procurar.
 */
class FichaTecnicaContaController extends Controller
{
    public function __construct(
        private readonly CatalogoPecas $catalogo,
        private readonly FichasModelos $fichasModelos,
    ) {
    }

    public function show($id, FipeService $fipe, EspecificacoesDoNome $especificacoes)
    {
        $veiculo = VeiculoConta::find($id);

        if (!$veiculo) {
            return $this->naoEncontrado();
        }

        return response()->json($this->montar($veiculo, $fipe, $especificacoes));
    }

    public function update(Request $request, $id, FipeService $fipe, EspecificacoesDoNome $especificacoes)
    {
        $veiculo = VeiculoConta::find($id);

        if (!$veiculo) {
            return $this->naoEncontrado();
        }

        $dados = $request->validate([
            'oleo_viscosidade' => ['nullable', 'string', 'max:20'],
            'oleo_especificacao' => ['nullable', 'string', 'max:60'],
            'oleo_capacidade_litros' => ['nullable', 'numeric', 'min:0', 'max:99'],
            'filtro_oleo' => ['nullable', 'string', 'max:60'],
            'filtro_ar' => ['nullable', 'string', 'max:60'],
            'filtro_combustivel' => ['nullable', 'string', 'max:60'],
            'pneu_medida' => ['nullable', 'string', 'max:30'],
            'pneu_pressao_dianteira' => ['nullable', 'integer', 'min:0', 'max:100'],
            'pneu_pressao_traseira' => ['nullable', 'integer', 'min:0', 'max:100'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ]);

        FichaTecnicaConta::updateOrCreate(['veiculo_conta_id' => $veiculo->id], $dados);

        return response()->json($this->montar($veiculo, $fipe, $especificacoes));
    }

    /**
     * @return array<string, mixed>
     */
    private function montar(VeiculoConta $veiculo, FipeService $fipe, EspecificacoesDoNome $especificacoes): array
    {
        [$dadosFipe, $status] = $this->consultarFipe($veiculo, $fipe);

        $manual = FichaTecnicaConta::where('veiculo_conta_id', $veiculo->id)->first();

        return [
            'veiculo' => [
                'id' => $veiculo->id,
                'nome' => trim($veiculo->apelido ?: "{$veiculo->marca} {$veiculo->modelo}"),
                'apelido' => $veiculo->apelido,
                'placa' => $veiculo->placa,
                'marca' => $veiculo->marca,
                'modelo' => $veiculo->modelo,
                'ano' => $veiculo->ano,
                'uf' => $veiculo->uf,
            ],
            'fipe' => $dadosFipe,
            'fipe_status' => $status,
            // Prefere o nome completo da versão que a FIPE devolve; senão, o que foi cadastrado.
            'especificacoes' => $especificacoes->extrair($dadosFipe['modelo'] ?? $veiculo->modelo),
            // Dados do MODELO (todas as versões e gerações), coletados da Wikipédia.
            'dados_modelo' => $this->dadosDoModelo($veiculo),
            'manutencao' => $manual ? $manual->only(FichaTecnica::CAMPOS) : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function dadosDoModelo(VeiculoConta $veiculo): ?array
    {
        $modelo = $this->catalogo->modeloDoVeiculo($veiculo->marca, $veiculo->modelo);
        $ficha = $this->fichasModelos->porNome($modelo['nome'] ?? null);

        return $ficha ? ['modelo' => $modelo['nome']] + $ficha : null;
    }

    /**
     * @return array{0: array<string, mixed>|null, 1: 'ok'|'sem_codigo'|'indisponivel'}
     */
    private function consultarFipe(VeiculoConta $veiculo, FipeService $fipe): array
    {
        if (!$veiculo->fipe_marca_id || !$veiculo->fipe_modelo_id || !$veiculo->fipe_ano) {
            return [null, 'sem_codigo'];
        }

        try {
            $r = $fipe->valor('carros', $veiculo->fipe_marca_id, $veiculo->fipe_modelo_id, $veiculo->fipe_ano);
        } catch (FipeIndisponivelException) {
            return [null, 'indisponivel'];
        }

        return [[
            'codigo_fipe' => $r['CodigoFipe'] ?? null,
            'marca' => $r['Marca'] ?? null,
            'modelo' => $r['Modelo'] ?? null,
            'ano_modelo' => $r['AnoModelo'] ?? null,
            'combustivel' => $r['Combustivel'] ?? null,
            'valor' => isset($r['Valor']) ? FipeService::valorParaDecimal($r['Valor']) : null,
            'mes_referencia' => isset($r['MesReferencia']) ? trim($r['MesReferencia']) : null,
        ], 'ok'];
    }

    private function naoEncontrado()
    {
        return response()->json(['message' => 'Veículo não encontrado.'], 404);
    }
}
