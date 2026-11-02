<?php

namespace App\Services;

use App\Models\Abastecimento;
use App\Models\CentroCusto;
use App\Models\Despesa;
use App\Models\Documento;
use App\Models\Motorista;
use App\Models\Multa;
use App\Models\Ocorrencia;
use App\Models\Seguro;
use App\Models\Servico;
use App\Models\UsoVeiculo;
use App\Models\VeiculoConta;
use App\Support\FusoDaFrota;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Relatórios gerenciais da frota, sempre com os dados reais da conta autenticada (isolamento por PertenceAConta).
 *
 * Cada relatório devolve colunas e linhas. A tela mostra as linhas em JSON; a exportação em CSV usa as mesmas linhas,
 * formatadas para o Excel brasileiro (separador ";" e vírgula decimal).
 */
class RelatoriosDaFrota
{
    /** Relatórios disponíveis: chave => nome e descrição. */
    public const CATALOGO = [
        'utilizacao' => ['nome' => 'Utilização dos veículos', 'descricao' => 'Quantos usos, quanto tempo e quantos km por veículo.'],
        'quilometragem' => ['nome' => 'Quilometragem', 'descricao' => 'Km atual de cada veículo e km rodados no período.'],
        'abastecimentos' => ['nome' => 'Abastecimentos', 'descricao' => 'Cada abastecimento, com valor por litro.'],
        'consumo' => ['nome' => 'Consumo médio', 'descricao' => 'Km por litro de cada veículo no período.'],
        'despesas' => ['nome' => 'Despesas', 'descricao' => 'Todos os gastos do período, de qualquer origem.'],
        'custos_veiculo' => ['nome' => 'Custos por veículo', 'descricao' => 'Quanto cada veículo custou, por tipo de gasto.'],
        'custos_motorista' => ['nome' => 'Custos por motorista', 'descricao' => 'Despesas avulsas e multas por motorista.'],
        'manutencoes' => ['nome' => 'Manutenções', 'descricao' => 'Serviços realizados nos veículos, com km e valor.'],
        'multas' => ['nome' => 'Multas', 'descricao' => 'Multas do período, com responsável e situação.'],
        'vencimentos' => ['nome' => 'Vencimentos de documentos', 'descricao' => 'Documentos e apólices com data de vencimento.'],
        'ocorrencias' => ['nome' => 'Ocorrências e avarias', 'descricao' => 'Acidentes, avarias e problemas, com prejuízo e gasto.'],
        'centros' => ['nome' => 'Utilização por centro de custo', 'descricao' => 'Usos e tempo por centro de custo.'],
    ];

    public function existe(string $tipo): bool
    {
        return array_key_exists($tipo, self::CATALOGO);
    }

    /**
     * @param  array{de?: ?string, ate?: ?string, veiculo_id?: ?int, motorista_id?: ?int, centro_custo_id?: ?int, categoria_id?: ?int}  $filtros
     * @return array{titulo: string, colunas: list<array{chave: string, rotulo: string, formato: string}>, linhas: list<array<string, mixed>>}
     */
    public function gerar(string $tipo, array $filtros, bool $gerencia): array
    {
        $f = $this->normalizar($filtros);

        $metodo = 'relatorio' . str_replace('_', '', ucwords($tipo, '_'));
        [$colunas, $linhas] = $this->{$metodo}($f, $gerencia);

        return [
            'titulo' => self::CATALOGO[$tipo]['nome'],
            'colunas' => $colunas,
            'linhas' => $linhas->values()->all(),
        ];
    }

    /** @return array{de: ?string, ate: ?string, veiculo: ?int, motorista: ?int, centro: ?int, categoria: ?int} */
    private function normalizar(array $filtros): array
    {
        return [
            'de' => $filtros['de'] ?? null,
            'ate' => $filtros['ate'] ?? null,
            'veiculo' => isset($filtros['veiculo_id']) ? (int) $filtros['veiculo_id'] : null,
            'motorista' => isset($filtros['motorista_id']) ? (int) $filtros['motorista_id'] : null,
            'centro' => isset($filtros['centro_custo_id']) ? (int) $filtros['centro_custo_id'] : null,
            'categoria' => isset($filtros['categoria_id']) ? (int) $filtros['categoria_id'] : null,
        ];
    }

    /** Limites de uma data-hora (saída, chegada, multa), em UTC, para o dia de Brasília. */
    private function limitesUtc(array $f, string $coluna, Builder $consulta): Builder
    {
        if ($f['de']) {
            $consulta->where($coluna, '>=', FusoDaFrota::dia($f['de'])->format('Y-m-d H:i:s'));
        }

        if ($f['ate']) {
            $consulta->where($coluna, '<=', FusoDaFrota::dia($f['ate'], true)->format('Y-m-d H:i:s'));
        }

        return $consulta;
    }

    private function nomeDoVeiculo(?VeiculoConta $v): ?string
    {
        return $v ? trim($v->apelido ?: "{$v->marca} {$v->modelo}") : null;
    }

    /** @return array{0: list<array{chave: string, rotulo: string, formato: string}>, 1: Collection} */
    private function relatorioUtilizacao(array $f, bool $gerencia): array
    {
        $usos = $this->usosConcluidos($f)->with('veiculo')->get();

        $linhas = $usos->groupBy('veiculo_conta_id')->map(function (Collection $grupo) {
            $veiculo = $grupo->first()->veiculo;

            return [
                'placa' => $veiculo?->placa,
                'veiculo' => $this->nomeDoVeiculo($veiculo),
                'usos' => $grupo->count(),
                'horas_em_uso' => round($grupo->sum(fn (UsoVeiculo $u) => $this->horasEntre($u)), 1),
                'km_rodados' => (int) $grupo->sum(fn (UsoVeiculo $u) => $this->kmDoUso($u)),
            ];
        })->sortBy('placa')->values();

        return [[
            ['chave' => 'placa', 'rotulo' => 'Placa', 'formato' => 'texto'],
            ['chave' => 'veiculo', 'rotulo' => 'Veículo', 'formato' => 'texto'],
            ['chave' => 'usos', 'rotulo' => 'Usos', 'formato' => 'inteiro'],
            ['chave' => 'horas_em_uso', 'rotulo' => 'Horas em uso', 'formato' => 'numero'],
            ['chave' => 'km_rodados', 'rotulo' => 'Km rodados', 'formato' => 'inteiro'],
        ], $linhas];
    }

    /** @return array{0: list<array{chave: string, rotulo: string, formato: string}>, 1: Collection} */
    private function relatorioQuilometragem(array $f, bool $gerencia): array
    {
        $veiculos = VeiculoConta::query()
            ->when($f['veiculo'], fn ($q) => $q->whereKey($f['veiculo']))
            ->orderBy('placa')
            ->get();

        $kmDosUsos = $this->usosConcluidos($f)->get()->groupBy('veiculo_conta_id')
            ->map(fn (Collection $g) => (int) $g->sum(fn (UsoVeiculo $u) => $this->kmDoUso($u)));

        $linhas = $veiculos->map(fn (VeiculoConta $v) => [
            'placa' => $v->placa,
            'veiculo' => $this->nomeDoVeiculo($v),
            'km_atual' => $v->km_atual,
            'km_rodados' => $kmDosUsos[$v->id] ?? 0,
        ]);

        return [[
            ['chave' => 'placa', 'rotulo' => 'Placa', 'formato' => 'texto'],
            ['chave' => 'veiculo', 'rotulo' => 'Veículo', 'formato' => 'texto'],
            ['chave' => 'km_atual', 'rotulo' => 'Km atual', 'formato' => 'inteiro'],
            ['chave' => 'km_rodados', 'rotulo' => 'Km rodados no período', 'formato' => 'inteiro'],
        ], $linhas];
    }

    /** @return array{0: list<array{chave: string, rotulo: string, formato: string}>, 1: Collection} */
    private function relatorioAbastecimentos(array $f, bool $gerencia): array
    {
        $abastecimentos = $this->abastecimentos($f)->with('veiculo')->orderByDesc('data')->orderByDesc('id')->get();

        $linhas = $abastecimentos->map(fn (Abastecimento $a) => [
            'data' => $a->data?->toDateString(),
            'placa' => $a->veiculo?->placa,
            'km' => $a->km,
            'litros' => (float) $a->litros,
            'combustivel' => $a->combustivel,
            'posto' => $a->posto,
            'valor_litro' => $a->litros > 0 ? round((float) $a->valor_total / (float) $a->litros, 3) : null,
            'valor_total' => (float) $a->valor_total,
        ]);

        return [[
            ['chave' => 'data', 'rotulo' => 'Data', 'formato' => 'data'],
            ['chave' => 'placa', 'rotulo' => 'Placa', 'formato' => 'texto'],
            ['chave' => 'km', 'rotulo' => 'Km', 'formato' => 'inteiro'],
            ['chave' => 'litros', 'rotulo' => 'Litros', 'formato' => 'numero'],
            ['chave' => 'combustivel', 'rotulo' => 'Combustível', 'formato' => 'texto'],
            ['chave' => 'posto', 'rotulo' => 'Posto', 'formato' => 'texto'],
            ['chave' => 'valor_litro', 'rotulo' => 'Valor por litro', 'formato' => 'moeda'],
            ['chave' => 'valor_total', 'rotulo' => 'Valor total', 'formato' => 'moeda'],
        ], $linhas];
    }

    /** @return array{0: list<array{chave: string, rotulo: string, formato: string}>, 1: Collection} */
    private function relatorioConsumo(array $f, bool $gerencia): array
    {
        $linhas = $this->abastecimentos($f)->with('veiculo')->get()
            ->groupBy('veiculo_conta_id')
            ->map(function (Collection $grupo) {
                $kmRodados = (int) $grupo->max('km') - (int) $grupo->min('km');
                $litros = (float) $grupo->sum('litros');

                return [
                    'placa' => $grupo->first()->veiculo?->placa,
                    'abastecimentos' => $grupo->count(),
                    'litros' => round($litros, 1),
                    'km_rodados' => $kmRodados,
                    'km_por_litro' => $kmRodados > 0 && $litros > 0 ? round($kmRodados / $litros, 2) : null,
                ];
            })
            ->sortBy('placa')
            ->values();

        return [[
            ['chave' => 'placa', 'rotulo' => 'Placa', 'formato' => 'texto'],
            ['chave' => 'abastecimentos', 'rotulo' => 'Abastecimentos', 'formato' => 'inteiro'],
            ['chave' => 'litros', 'rotulo' => 'Litros', 'formato' => 'numero'],
            ['chave' => 'km_rodados', 'rotulo' => 'Km entre o primeiro e o último abastecimento', 'formato' => 'inteiro'],
            ['chave' => 'km_por_litro', 'rotulo' => 'Km por litro', 'formato' => 'numero'],
        ], $linhas];
    }

    /**
     * Todos os gastos do período numa só lista. Cada um vem da sua tabela e aparece uma vez.
     *
     * @return array{0: list<array{chave: string, rotulo: string, formato: string}>, 1: Collection}
     */
    private function relatorioDespesas(array $f, bool $gerencia): array
    {
        $linhas = collect();

        if (!$f['categoria']) {
            $linhas = $linhas->concat($this->abastecimentos($f)->with('veiculo')->get()->map(fn (Abastecimento $a) => [
                'data' => $a->data?->toDateString(),
                'origem' => 'Combustível',
                'placa' => $a->veiculo?->placa,
                'descricao' => trim(($a->combustivel ?? '') . ' ' . ($a->posto ?? '')),
                'valor' => (float) $a->valor_total,
            ]));

            $linhas = $linhas->concat($this->servicosNoPeriodo($f)->with('veiculo')->get()->map(fn (Servico $s) => [
                'data' => $s->realizado_em?->toDateString(),
                'origem' => 'Manutenção',
                'placa' => $s->veiculo?->placa,
                'descricao' => $s->titulo ?: $s->rotulo,
                'valor' => (float) $s->valor,
            ]));

            $linhas = $linhas->concat($this->multasNoPeriodo($f)->with('veiculo')->get()->filter(fn (Multa $m) => $m->valor !== null)->map(fn (Multa $m) => [
                'data' => FusoDaFrota::local($m->data_hora)->toDateString(),
                'origem' => 'Multa',
                'placa' => $m->veiculo?->placa,
                'descricao' => $m->descricao,
                'valor' => (float) $m->valor,
            ]));
        }

        $linhas = $linhas->concat($this->avulsasNoPeriodo($f)->with(['veiculo', 'categoria'])->get()->map(fn (Despesa $d) => [
            'data' => $d->data?->toDateString(),
            'origem' => 'Avulsa',
            'placa' => $d->veiculo?->placa,
            'descricao' => trim(($d->categoria?->nome ?? '') . ($d->descricao ? ' — ' . $d->descricao : '')),
            'valor' => (float) $d->valor,
        ]));

        $linhas = $linhas->sortByDesc('data')->values();

        return [[
            ['chave' => 'data', 'rotulo' => 'Data', 'formato' => 'data'],
            ['chave' => 'origem', 'rotulo' => 'Origem', 'formato' => 'texto'],
            ['chave' => 'placa', 'rotulo' => 'Placa', 'formato' => 'texto'],
            ['chave' => 'descricao', 'rotulo' => 'Descrição', 'formato' => 'texto'],
            ['chave' => 'valor', 'rotulo' => 'Valor', 'formato' => 'moeda'],
        ], $linhas];
    }

    /** @return array{0: list<array{chave: string, rotulo: string, formato: string}>, 1: Collection} */
    private function relatorioCustosVeiculo(array $f, bool $gerencia): array
    {
        $consolidado = app(ConsolidacaoDeDespesas::class)->consolidar($f['de'], $f['ate'], $f['veiculo']);

        return [[
            ['chave' => 'placa', 'rotulo' => 'Placa', 'formato' => 'texto'],
            ['chave' => 'veiculo', 'rotulo' => 'Veículo', 'formato' => 'texto'],
            ['chave' => 'combustivel', 'rotulo' => 'Combustível', 'formato' => 'moeda'],
            ['chave' => 'servicos', 'rotulo' => 'Manutenção', 'formato' => 'moeda'],
            ['chave' => 'avulsas', 'rotulo' => 'Avulsas', 'formato' => 'moeda'],
            ['chave' => 'multas', 'rotulo' => 'Multas', 'formato' => 'moeda'],
            ['chave' => 'total', 'rotulo' => 'Total', 'formato' => 'moeda'],
            ['chave' => 'km_rodados', 'rotulo' => 'Km rodados', 'formato' => 'inteiro'],
            ['chave' => 'custo_por_km', 'rotulo' => 'Custo por km', 'formato' => 'moeda'],
        ], collect($consolidado['por_veiculo'])->map(fn ($v) => [
            'placa' => $v['placa'],
            'veiculo' => $v['veiculo'],
            'combustivel' => $v['combustivel'],
            'servicos' => $v['servicos'],
            'avulsas' => $v['avulsas'],
            'multas' => $v['multas'],
            'total' => $v['total'],
            'km_rodados' => $v['km_rodados'],
            'custo_por_km' => $v['custo_por_km'],
        ])];
    }

    /** @return array{0: list<array{chave: string, rotulo: string, formato: string}>, 1: Collection} */
    private function relatorioCustosMotorista(array $f, bool $gerencia): array
    {
        $consolidado = app(ConsolidacaoDeDespesas::class)->consolidar($f['de'], $f['ate'], $f['veiculo']);

        return [[
            ['chave' => 'nome', 'rotulo' => 'Motorista', 'formato' => 'texto'],
            ['chave' => 'avulsas', 'rotulo' => 'Avulsas', 'formato' => 'moeda'],
            ['chave' => 'multas', 'rotulo' => 'Multas', 'formato' => 'moeda'],
            ['chave' => 'total', 'rotulo' => 'Total', 'formato' => 'moeda'],
        ], collect($consolidado['por_motorista'])->map(fn ($m) => [
            'nome' => $m['nome'],
            'avulsas' => $m['avulsas'],
            'multas' => $m['multas'],
            'total' => $m['total'],
        ])];
    }

    /** @return array{0: list<array{chave: string, rotulo: string, formato: string}>, 1: Collection} */
    private function relatorioManutencoes(array $f, bool $gerencia): array
    {
        $linhas = $this->servicosNoPeriodo($f)->with('veiculo')->orderByDesc('realizado_em')->get()->map(fn (Servico $s) => [
            'data' => $s->realizado_em?->toDateString(),
            'placa' => $s->veiculo?->placa,
            'servico' => $s->titulo ?: $s->rotulo,
            'km' => $s->km,
            'valor' => $s->valor !== null ? (float) $s->valor : null,
        ]);

        return [[
            ['chave' => 'data', 'rotulo' => 'Data', 'formato' => 'data'],
            ['chave' => 'placa', 'rotulo' => 'Placa', 'formato' => 'texto'],
            ['chave' => 'servico', 'rotulo' => 'Serviço', 'formato' => 'texto'],
            ['chave' => 'km', 'rotulo' => 'Km', 'formato' => 'inteiro'],
            ['chave' => 'valor', 'rotulo' => 'Valor', 'formato' => 'moeda'],
        ], $linhas];
    }

    /** @return array{0: list<array{chave: string, rotulo: string, formato: string}>, 1: Collection} */
    private function relatorioMultas(array $f, bool $gerencia): array
    {
        $linhas = $this->multasNoPeriodo($f)->with(['veiculo', 'motorista'])->orderByDesc('data_hora')->get()->map(fn (Multa $m) => [
            'data' => FusoDaFrota::local($m->data_hora)->toDateString(),
            'placa' => $m->veiculo?->placa,
            'motorista' => $m->motorista?->nome,
            'descricao' => $m->descricao,
            'pontos' => $m->pontos,
            'valor' => $m->valor !== null ? (float) $m->valor : null,
            'situacao' => $m->situacao,
        ]);

        return [[
            ['chave' => 'data', 'rotulo' => 'Data', 'formato' => 'data'],
            ['chave' => 'placa', 'rotulo' => 'Placa', 'formato' => 'texto'],
            ['chave' => 'motorista', 'rotulo' => 'Responsável', 'formato' => 'texto'],
            ['chave' => 'descricao', 'rotulo' => 'Infração', 'formato' => 'texto'],
            ['chave' => 'pontos', 'rotulo' => 'Pontos', 'formato' => 'inteiro'],
            ['chave' => 'valor', 'rotulo' => 'Valor', 'formato' => 'moeda'],
            ['chave' => 'situacao', 'rotulo' => 'Situação', 'formato' => 'texto'],
        ], $linhas];
    }

    /** @return array{0: list<array{chave: string, rotulo: string, formato: string}>, 1: Collection} */
    private function relatorioVencimentos(array $f, bool $gerencia): array
    {
        $hoje = Carbon::today(FusoDaFrota::NOME);

        $documentos = Documento::query()->with('veiculo')->whereNotNull('vencimento')
            ->when($f['veiculo'], fn ($q) => $q->where('veiculo_conta_id', $f['veiculo']))
            ->when($f['de'], fn ($q) => $q->whereDate('vencimento', '>=', $f['de']))
            ->when($f['ate'], fn ($q) => $q->whereDate('vencimento', '<=', $f['ate']))
            ->get()
            ->map(fn (Documento $d) => $this->linhaDeVencimento($d->veiculo, $d->tipo === 'outro' ? ($d->titulo ?? 'Outro') : strtoupper($d->tipo), $d->vencimento?->toDateString(), $hoje));

        $apolices = Seguro::query()->with('veiculo')->where('tipo', 'apolice')->whereNotNull('vigencia_fim')
            ->when($f['veiculo'], fn ($q) => $q->where('veiculo_conta_id', $f['veiculo']))
            ->when($f['de'], fn ($q) => $q->whereDate('vigencia_fim', '>=', $f['de']))
            ->when($f['ate'], fn ($q) => $q->whereDate('vigencia_fim', '<=', $f['ate']))
            ->get()
            ->map(fn (Seguro $s) => $this->linhaDeVencimento($s->veiculo, 'Seguro (' . $s->seguradora . ')', $s->vigencia_fim?->toDateString(), $hoje));

        $linhas = $documentos->concat($apolices)->sortBy('vencimento')->values();

        return [[
            ['chave' => 'placa', 'rotulo' => 'Placa', 'formato' => 'texto'],
            ['chave' => 'veiculo', 'rotulo' => 'Veículo', 'formato' => 'texto'],
            ['chave' => 'documento', 'rotulo' => 'Documento', 'formato' => 'texto'],
            ['chave' => 'vencimento', 'rotulo' => 'Vencimento', 'formato' => 'data'],
            ['chave' => 'situacao', 'rotulo' => 'Situação', 'formato' => 'texto'],
        ], $linhas];
    }

    private function linhaDeVencimento(?VeiculoConta $veiculo, string $documento, ?string $vencimento, Carbon $hoje): array
    {
        // Dias inteiros de Brasília: o vencimento é uma data, e o "hoje" começa à meia-noite.
        $dias = $vencimento ? (int) $hoje->diffInDays(Carbon::parse($vencimento, FusoDaFrota::NOME)->startOfDay(), false) : null;

        $situacao = match (true) {
            $dias === null => null,
            $dias < 0 => 'vencido',
            $dias === 0 => 'vence hoje',
            default => "vence em {$dias} dia(s)",
        };

        return [
            'placa' => $veiculo?->placa,
            'veiculo' => $this->nomeDoVeiculo($veiculo),
            'documento' => $documento,
            'vencimento' => $vencimento,
            'situacao' => $situacao,
        ];
    }

    /** @return array{0: list<array{chave: string, rotulo: string, formato: string}>, 1: Collection} */
    private function relatorioOcorrencias(array $f, bool $gerencia): array
    {
        $consulta = $this->limitesUtc($f, 'data_hora', Ocorrencia::query()->with(['veiculo', 'motorista']))
            ->when($f['veiculo'], fn ($q) => $q->where('veiculo_conta_id', $f['veiculo']))
            ->when($f['motorista'], fn ($q) => $q->where('motorista_id', $f['motorista']));

        $linhas = $consulta->orderByDesc('data_hora')->get()->map(function (Ocorrencia $o) use ($gerencia) {
            $linha = [
                'data' => FusoDaFrota::local($o->data_hora)->toDateString(),
                'placa' => $o->veiculo?->placa,
                'tipo' => Ocorrencia::TIPOS[$o->tipo] ?? $o->tipo,
                'status' => Ocorrencia::STATUS[$o->status] ?? $o->status,
                'motorista' => $o->motorista?->nome,
                'local' => $o->local,
            ];

            if ($gerencia) {
                $linha['prejuizo'] = $o->prejuizo_estimado !== null ? (float) $o->prejuizo_estimado : null;
                $linha['gasto'] = $o->valor_gasto !== null ? (float) $o->valor_gasto : null;
            }

            return $linha;
        });

        $colunas = [
            ['chave' => 'data', 'rotulo' => 'Data', 'formato' => 'data'],
            ['chave' => 'placa', 'rotulo' => 'Placa', 'formato' => 'texto'],
            ['chave' => 'tipo', 'rotulo' => 'Tipo', 'formato' => 'texto'],
            ['chave' => 'status', 'rotulo' => 'Status', 'formato' => 'texto'],
            ['chave' => 'motorista', 'rotulo' => 'Quem registrou', 'formato' => 'texto'],
            ['chave' => 'local', 'rotulo' => 'Local', 'formato' => 'texto'],
        ];

        if ($gerencia) {
            $colunas[] = ['chave' => 'prejuizo', 'rotulo' => 'Prejuízo estimado', 'formato' => 'moeda'];
            $colunas[] = ['chave' => 'gasto', 'rotulo' => 'Gasto com reparo', 'formato' => 'moeda'];
        }

        return [$colunas, $linhas];
    }

    /** @return array{0: list<array{chave: string, rotulo: string, formato: string}>, 1: Collection} */
    private function relatorioCentros(array $f, bool $gerencia): array
    {
        $usos = $this->usosConcluidos($f)->get();
        $nomes = CentroCusto::all()->keyBy('id');

        $linhas = $usos->groupBy(fn (UsoVeiculo $u) => $u->centro_custo_id ?? 0)->map(function (Collection $grupo, $centroId) use ($nomes) {
            $id = (int) $centroId ?: null;

            return [
                'centro' => $id === null ? 'Sem centro de custo' : ($nomes->get($id)?->nome ?? 'Centro removido'),
                'usos' => $grupo->count(),
                'horas_em_uso' => round($grupo->sum(fn (UsoVeiculo $u) => $this->horasEntre($u)), 1),
                'km_rodados' => (int) $grupo->sum(fn (UsoVeiculo $u) => $this->kmDoUso($u)),
            ];
        })->sortBy('centro')->values();

        return [[
            ['chave' => 'centro', 'rotulo' => 'Centro de custo', 'formato' => 'texto'],
            ['chave' => 'usos', 'rotulo' => 'Usos', 'formato' => 'inteiro'],
            ['chave' => 'horas_em_uso', 'rotulo' => 'Horas em uso', 'formato' => 'numero'],
            ['chave' => 'km_rodados', 'rotulo' => 'Km rodados', 'formato' => 'inteiro'],
        ], $linhas];
    }

    /** Usos concluídos que saíram no período, com os filtros de veículo, motorista e centro. */
    private function usosConcluidos(array $f): Builder
    {
        return $this->limitesUtc($f, 'saida_em', UsoVeiculo::query()
            ->where('status', 'concluido')
            ->whereNotNull('saida_em')
            ->whereNotNull('chegada_em')
            ->when($f['veiculo'], fn ($q) => $q->where('veiculo_conta_id', $f['veiculo']))
            ->when($f['motorista'], fn ($q) => $q->where('motorista_id', $f['motorista']))
            ->when($f['centro'], fn ($q) => $q->where('centro_custo_id', $f['centro'])));
    }

    private function abastecimentos(array $f): Builder
    {
        return Abastecimento::query()
            ->when($f['de'], fn ($q) => $q->whereDate('data', '>=', $f['de']))
            ->when($f['ate'], fn ($q) => $q->whereDate('data', '<=', $f['ate']))
            ->when($f['veiculo'], fn ($q) => $q->where('veiculo_conta_id', $f['veiculo']));
    }

    private function servicosNoPeriodo(array $f): Builder
    {
        return Servico::query()
            ->whereNotNull('valor')
            ->when($f['de'], fn ($q) => $q->whereDate('realizado_em', '>=', $f['de']))
            ->when($f['ate'], fn ($q) => $q->whereDate('realizado_em', '<=', $f['ate']))
            ->when($f['veiculo'], fn ($q) => $q->where('veiculo_conta_id', $f['veiculo']));
    }

    private function multasNoPeriodo(array $f): Builder
    {
        return $this->limitesUtc($f, 'data_hora', Multa::query()
            ->when($f['veiculo'], fn ($q) => $q->where('veiculo_conta_id', $f['veiculo']))
            ->when($f['motorista'], fn ($q) => $q->where('motorista_id', $f['motorista'])));
    }

    private function avulsasNoPeriodo(array $f): Builder
    {
        return Despesa::query()
            ->when($f['de'], fn ($q) => $q->whereDate('data', '>=', $f['de']))
            ->when($f['ate'], fn ($q) => $q->whereDate('data', '<=', $f['ate']))
            ->when($f['veiculo'], fn ($q) => $q->where('veiculo_conta_id', $f['veiculo']))
            ->when($f['motorista'], fn ($q) => $q->where('motorista_id', $f['motorista']))
            ->when($f['centro'], fn ($q) => $q->where('centro_custo_id', $f['centro']))
            ->when($f['categoria'], fn ($q) => $q->where('categoria_id', $f['categoria']));
    }

    private function horasEntre(UsoVeiculo $u): float
    {
        return $u->saida_em && $u->chegada_em ? $u->saida_em->diffInMinutes($u->chegada_em) / 60 : 0.0;
    }

    private function kmDoUso(UsoVeiculo $u): int
    {
        return $u->km_saida !== null && $u->km_chegada !== null ? max(0, (int) $u->km_chegada - (int) $u->km_saida) : 0;
    }
}
