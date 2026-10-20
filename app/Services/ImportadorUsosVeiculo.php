<?php

namespace App\Services;

use App\Models\CentroCusto;
use App\Models\Motorista;
use App\Models\UsoVeiculo;
use App\Models\VeiculoConta;
use App\Rules\PlacaBrasileira;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Importa a planilha de utilização dos veículos (CSV) como usos já concluídos,
 * com o km de saída e de chegada, e leva o km atual de cada veículo ao maior km
 * informado. Tudo ou nada: com qualquer linha inválida, nada é gravado.
 *
 * Colunas (cabeçalho obrigatório, `;` ou `,`; linhas começando com # são ignoradas):
 *   placa;data_saida;hora_saida;data_chegada;hora_chegada;km_saida;km_chegada;motorista;centro_custo;finalidade
 * Obrigatórias: placa, data_saida, km_saida e km_chegada.
 */
class ImportadorUsosVeiculo
{
    public const COLUNAS = ['placa', 'data_saida', 'hora_saida', 'data_chegada', 'hora_chegada', 'km_saida', 'km_chegada', 'motorista', 'centro_custo', 'finalidade'];

    private const OBRIGATORIAS = ['placa', 'data_saida', 'km_saida', 'km_chegada'];

    public const MAX_LINHAS = 5000;

    /**
     * @return array{erros: array<int, string>, resumo: array<string, mixed>|null}
     */
    public function importar(string $conteudo, bool $simular = false, bool $criarFaltantes = false): array
    {
        [$linhas, $erros] = $this->ler($conteudo, $criarFaltantes);

        if (!$erros) {
            $erros = $this->conferirSequencias($linhas);
        }

        if ($erros) {
            return ['erros' => $erros, 'resumo' => null];
        }

        $resumo = $this->resumir($linhas);

        if (!$simular) {
            $this->gravar($linhas);
        }

        return ['erros' => [], 'resumo' => $resumo + ['gravado' => !$simular]];
    }

    /** Planilha-modelo: cabeçalho e uma linha de exemplo comentada (a importação a ignora). */
    public static function modeloCsv(): string
    {
        return "\xEF\xBB\xBF"
            . implode(';', self::COLUNAS) . "\r\n"
            . '# Exemplo (apague esta linha): ABC1D23;15/10/2026;08:00;15/10/2026;18:30;45200;45310;Maria Silva;Entregas;Visita a clientes' . "\r\n";
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>}
     */
    private function ler(string $conteudo, bool $criarFaltantes): array
    {
        $conteudo = preg_replace('/^\xEF\xBB\xBF/', '', $conteudo);
        $primeira = '';

        foreach (preg_split('/\r\n|\r|\n/', $conteudo) as $linhaDeTexto) {
            if (trim($linhaDeTexto) !== '' && !str_starts_with(ltrim($linhaDeTexto), '#')) {
                $primeira = $linhaDeTexto;

                break;
            }
        }

        $separador = substr_count($primeira, ';') >= substr_count($primeira, ',') ? ';' : ',';

        $arquivo = fopen('php://memory', 'r+');
        fwrite($arquivo, $conteudo);
        rewind($arquivo);

        $cabecalho = null;
        $numero = 0;

        while ($cabecalho === null && ($campos = fgetcsv($arquivo, 0, $separador)) !== false) {
            $numero++;

            if ($this->vazia($campos) || str_starts_with(ltrim((string) $campos[0]), '#')) {
                continue;
            }

            $cabecalho = array_map(fn ($c) => Str::slug((string) $c, '_'), $campos);
        }

        $erros = [];

        foreach (self::OBRIGATORIAS as $coluna) {
            if ($cabecalho === null || !in_array($coluna, $cabecalho, true)) {
                $erros[] = "Coluna obrigatória ausente no cabeçalho: {$coluna} (esperado: " . implode(';', self::COLUNAS) . ')';
            }
        }

        if ($erros) {
            return [[], $erros];
        }

        $veiculos = VeiculoConta::all()->keyBy(fn (VeiculoConta $v) => PlacaBrasileira::normalizar($v->placa));
        $motoristas = Motorista::all()->keyBy(fn (Motorista $m) => CatalogoPecas::normalizar($m->nome));
        $centros = CentroCusto::all()->keyBy(fn (CentroCusto $c) => CatalogoPecas::normalizar($c->nome));

        $linhas = [];
        $lidas = 0;

        while (($campos = fgetcsv($arquivo, 0, $separador)) !== false) {
            $numero++;

            if ($this->vazia($campos) || str_starts_with(ltrim((string) $campos[0]), '#')) {
                continue;
            }

            if (++$lidas > self::MAX_LINHAS) {
                $erros[] = 'A planilha passa de ' . self::MAX_LINHAS . ' linhas. Divida em arquivos menores.';

                break;
            }

            $d = array_combine($cabecalho, array_pad(array_map('trim', $campos), count($cabecalho), ''));
            $problemas = [];

            $placa = PlacaBrasileira::normalizar($d['placa']);
            $veiculo = $veiculos->get($placa);

            if (!$veiculo) {
                $problemas[] = "placa \"{$d['placa']}\" não está cadastrada";
            }

            $saida = $this->momento($d['data_saida'] ?? '', $d['hora_saida'] ?? '', '00:00', 'data_saida', 'hora_saida', $problemas);
            $chegada = $this->momento(($d['data_chegada'] ?? '') !== '' ? $d['data_chegada'] : ($d['data_saida'] ?? ''), $d['hora_chegada'] ?? '', '23:59', 'data_chegada', 'hora_chegada', $problemas);

            if ($saida && $chegada && $chegada->lte($saida)) {
                $problemas[] = 'a chegada precisa ser depois da saída';
            }

            $kmSaida = $this->km($d['km_saida'] ?? '', 'km_saida', $problemas);
            $kmChegada = $this->km($d['km_chegada'] ?? '', 'km_chegada', $problemas);

            if ($kmSaida !== null && $kmChegada !== null && $kmChegada < $kmSaida) {
                $problemas[] = "km de chegada ({$kmChegada}) menor que o de saída ({$kmSaida})";
            }

            $motorista = $this->achar($motoristas, $d['motorista'] ?? '', 'motorista', $criarFaltantes, $problemas);
            $centro = $this->achar($centros, $d['centro_custo'] ?? '', 'centro de custo', $criarFaltantes, $problemas);

            if (mb_strlen($d['finalidade'] ?? '') > 160) {
                $problemas[] = 'finalidade com mais de 160 caracteres';
            }

            if ($problemas) {
                $erros[] = "linha {$numero}: " . implode('; ', $problemas);

                continue;
            }

            $linhas[] = [
                'linha' => $numero,
                'veiculo' => $veiculo,
                'saida' => $saida,
                'chegada' => $chegada,
                'km_saida' => $kmSaida,
                'km_chegada' => $kmChegada,
                'motorista' => $motorista,
                'centro' => $centro,
                'finalidade' => ($d['finalidade'] ?? '') !== '' ? $d['finalidade'] : null,
            ];
        }

        fclose($arquivo);

        return [$linhas, $erros];
    }

    /**
     * Nos mesmos veículos: nada se sobrepõe (na planilha e na agenda já existente) e o
     * hodômetro não anda para trás de uma viagem para a seguinte.
     *
     * @param  array<int, array<string, mixed>>  $linhas
     * @return array<int, string>
     */
    private function conferirSequencias(array $linhas): array
    {
        $erros = [];

        foreach (collect($linhas)->groupBy(fn ($l) => $l['veiculo']->id) as $doVeiculo) {
            $ordenadas = $doVeiculo->sortBy(fn ($l) => $l['saida']->timestamp)->values();

            foreach ($ordenadas as $i => $atual) {
                $anterior = $i > 0 ? $ordenadas[$i - 1] : null;

                if ($anterior && $atual['saida']->lt($anterior['chegada'])) {
                    $erros[] = "linha {$atual['linha']}: o horário se sobrepõe ao da linha {$anterior['linha']} (mesmo veículo)";
                } elseif ($anterior && $atual['km_saida'] < $anterior['km_chegada']) {
                    $erros[] = "linha {$atual['linha']}: o km de saída ({$atual['km_saida']}) é menor que o km de chegada da linha {$anterior['linha']} ({$anterior['km_chegada']}); o hodômetro não anda para trás";
                }

                $existente = UsoVeiculo::sobrepostos($atual['saida'], $atual['chegada'])
                    ->where('veiculo_conta_id', $atual['veiculo']->id)
                    ->first();

                if ($existente) {
                    $erros[] = "linha {$atual['linha']}: já existe um uso desse veículo na agenda de " . $existente->inicio_previsto->format('d/m/Y H:i') . ' a ' . $existente->fim_previsto->format('d/m/Y H:i');
                }
            }
        }

        sort($erros, SORT_NATURAL);

        return $erros;
    }

    /**
     * @param  array<int, array<string, mixed>>  $linhas
     * @return array<string, mixed>
     */
    private function resumir(array $linhas): array
    {
        $linhasCol = collect($linhas);

        return [
            'usos' => $linhasCol->count(),
            'veiculos' => $linhasCol->pluck('veiculo.id')->unique()->count(),
            'km_rodados' => $linhasCol->sum(fn ($l) => $l['km_chegada'] - $l['km_saida']),
            'motoristas_novos' => $linhasCol->filter(fn ($l) => is_string($l['motorista']))->pluck('motorista')->unique()->values()->all(),
            'centros_novos' => $linhasCol->filter(fn ($l) => is_string($l['centro']))->pluck('centro')->unique()->values()->all(),
        ];
    }

    /** @param  array<int, array<string, mixed>>  $linhas */
    private function gravar(array $linhas): void
    {
        DB::transaction(function () use ($linhas) {
            $motoristas = [];
            $centros = [];
            $maiorKm = [];

            foreach ($linhas as $l) {
                $motoristaId = $this->resolver($l['motorista'], $motoristas, fn (string $nome) => Motorista::create(['nome' => $nome])->id);
                $centroId = $this->resolver($l['centro'], $centros, fn (string $nome) => CentroCusto::create(['nome' => $nome])->id)
                    ?? $l['veiculo']->centro_custo_id;

                UsoVeiculo::create([
                    'veiculo_conta_id' => $l['veiculo']->id,
                    'motorista_id' => $motoristaId,
                    'centro_custo_id' => $centroId,
                    'inicio_previsto' => $l['saida'],
                    'fim_previsto' => $l['chegada'],
                    'finalidade' => $l['finalidade'],
                    'status' => 'concluido',
                    'km_saida' => $l['km_saida'],
                    'km_chegada' => $l['km_chegada'],
                    'saida_em' => $l['saida'],
                    'chegada_em' => $l['chegada'],
                    'origem' => 'planilha',
                ]);

                $id = $l['veiculo']->id;
                $maiorKm[$id] = max($maiorKm[$id] ?? 0, $l['km_chegada']);
            }

            // O km atual só sobe: uma planilha de meses atrás não o derruba.
            foreach ($maiorKm as $veiculoId => $km) {
                $veiculo = VeiculoConta::find($veiculoId);

                if ($veiculo && ($veiculo->km_atual === null || $km > $veiculo->km_atual)) {
                    $veiculo->forceFill(['km_atual' => $km, 'km_atualizado_em' => now()])->save();
                }
            }
        });
    }

    /**
     * @param  Motorista|CentroCusto|string|null  $valor  modelo existente, nome a criar ou nada
     * @param  array<string, int>  $criados
     */
    private function resolver(mixed $valor, array &$criados, callable $criar): ?int
    {
        if ($valor === null) {
            return null;
        }

        if (!is_string($valor)) {
            return $valor->id;
        }

        $chave = CatalogoPecas::normalizar($valor);

        return $criados[$chave] ??= $criar($valor);
    }

    /**
     * Acha por nome (sem acento nem caixa). Se não existe: cria quando permitido
     * (devolve o nome), senão acusa o problema.
     *
     * @param  Collection<string, mixed>  $cadastro
     * @param  array<int, string>  $problemas
     */
    private function achar($cadastro, string $nome, string $rotulo, bool $criarFaltantes, array &$problemas): mixed
    {
        if ($nome === '') {
            return null;
        }

        $achado = $cadastro->get(CatalogoPecas::normalizar($nome));

        if ($achado) {
            return $achado;
        }

        if ($criarFaltantes) {
            return $nome;
        }

        $problemas[] = "{$rotulo} \"{$nome}\" não está cadastrado";

        return null;
    }

    /** @param  array<int, string>  $problemas */
    private function momento(string $data, string $hora, string $horaPadrao, string $campoData, string $campoHora, array &$problemas): ?Carbon
    {
        if ($data === '') {
            $problemas[] = "{$campoData} vazia";

            return null;
        }

        $dia = null;

        foreach (['d/m/Y', 'd/m/y', 'Y-m-d', 'd-m-Y'] as $formato) {
            try {
                $tentativa = Carbon::createFromFormat('!' . $formato, $data);
            } catch (Throwable) {
                continue;
            }

            // Rejeita o que o PHP "corrige" (31/02 virar março): a data volta igual à escrita.
            if ($tentativa && $tentativa->format($formato) === $data) {
                $dia = $tentativa;

                break;
            }
        }

        if (!$dia) {
            $problemas[] = "{$campoData} \"{$data}\" inválida (use 15/10/2026 ou 2026-10-15)";

            return null;
        }

        $hora = $hora !== '' ? $hora : $horaPadrao;

        if (!preg_match('/^([01]?\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', $hora, $m)) {
            $problemas[] = "{$campoHora} \"{$hora}\" inválida (use 08:30)";

            return null;
        }

        return $dia->setTime((int) $m[1], (int) $m[2]);
    }

    /** "45.200", "45200" ou "45200,4": inteiro de hodômetro. */
    private function km(string $valor, string $campo, array &$problemas): ?int
    {
        if ($valor === '') {
            $problemas[] = "{$campo} vazio";

            return null;
        }

        $limpo = str_replace([' ', '.'], '', $valor);
        $limpo = str_replace(',', '.', $limpo);

        if (!is_numeric($limpo) || (float) $limpo < 0 || (float) $limpo > 9_999_999) {
            $problemas[] = "{$campo} \"{$valor}\" inválido";

            return null;
        }

        return (int) round((float) $limpo);
    }

    /** @param  array<int, string|null>|false  $campos */
    private function vazia($campos): bool
    {
        return $campos === [null] || !array_filter((array) $campos, fn ($c) => trim((string) $c) !== '');
    }
}
