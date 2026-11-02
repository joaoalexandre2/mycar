<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Corrige as datas da frota gravadas antes do fuso de Brasília entrar (ver App\Support\FusoDaFrota).
 * Antes, o horário digitado era guardado como se fosse UTC: 08:00 digitado ficava 08:00 UTC, quando é 11:00 UTC.
 * Brasília é UTC-3 o ano todo, então basta somar 3 horas nas linhas gravadas antes do corte.
 *
 * Sem --executar, só mostra o que mudaria. Roda uma vez: a marca em correcoes_de_dados impede a segunda.
 */
class CorrigirFusoAntigo extends Command
{
    private const NOME = 'fuso_frota_3h';

    private const HORAS = 3;

    protected $signature = 'frota:corrigir-fuso-antigo
        {--antes= : Momento do corte em UTC, quando o fuso novo entrou no ar (ex.: "2026-11-02 20:55:00")}
        {--executar : Grava de fato. Sem isso, só mostra o que mudaria}';

    protected $description = 'Soma 3 horas nas datas da frota gravadas antes do fuso de Brasília.';

    public function handle(): int
    {
        $corte = $this->corte();

        if (!$corte) {
            return self::FAILURE;
        }

        if (DB::table('correcoes_de_dados')->where('nome', self::NOME)->exists()) {
            $this->error('Esta correção já foi aplicada. Não rode de novo: somaria 3 horas outra vez.');

            return self::FAILURE;
        }

        $this->mostrarPlano($corte);

        if (!$this->option('executar')) {
            $this->warn('Nada foi gravado. Rode de novo com --executar para aplicar.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($corte) {
            $this->aplicar($corte);

            DB::table('correcoes_de_dados')->insert(['nome' => self::NOME, 'executada_em' => now()]);
        });

        $this->info('Correção aplicada.');

        return self::SUCCESS;
    }

    private function corte(): ?Carbon
    {
        $texto = (string) $this->option('antes');

        if ($texto === '') {
            $this->error('Informe o corte em UTC: --antes="2026-11-02 20:55:00".');

            return null;
        }

        try {
            return Carbon::parse($texto, 'UTC')->utc();
        } catch (Throwable) {
            $this->error("Data inválida em --antes: {$texto}.");

            return null;
        }
    }

    private function mostrarPlano(Carbon $corte): void
    {
        $usos = DB::table('usos_veiculo')->where('created_at', '<', $corte->format('Y-m-d H:i:s'));
        $multas = DB::table('multas')->where('created_at', '<', $corte->format('Y-m-d H:i:s'));

        $this->info(sprintf('Corte (UTC): %s', $corte->format('Y-m-d H:i:s')));
        $this->line(sprintf('Usos gravados antes do corte: %d (%d da planilha).', (clone $usos)->count(), (clone $usos)->where('origem', 'planilha')->count()));
        $this->line(sprintf('Multas gravadas antes do corte: %d.', (clone $multas)->count()));

        // Editados depois do corte: parte das datas pode já ter sido gravada certa. Vale conferir.
        $editados = (clone $usos)->where('updated_at', '>=', $corte->format('Y-m-d H:i:s'))->orderBy('id')->pluck('id');

        if ($editados->isNotEmpty()) {
            $this->warn(sprintf('Usos criados antes e editados depois do corte (%d): %s. Confira estes depois.', $editados->count(), $editados->take(50)->implode(', ')));
        }
    }

    private function aplicar(Carbon $corte): void
    {
        $antes = $corte->format('Y-m-d H:i:s');

        DB::table('usos_veiculo')
            ->where('created_at', '<', $antes)
            ->chunkById(500, function ($usos) {
                foreach ($usos as $uso) {
                    $campos = [
                        'inicio_previsto' => $this->somar($uso->inicio_previsto),
                        'fim_previsto' => $this->somar($uso->fim_previsto),
                    ];

                    // Na agenda, saída e chegada vieram de now() (já estavam certas). Na planilha, vieram digitadas.
                    if ($uso->origem === 'planilha') {
                        $campos['saida_em'] = $this->somar($uso->saida_em);
                        $campos['chegada_em'] = $this->somar($uso->chegada_em);
                    }

                    DB::table('usos_veiculo')->where('id', $uso->id)->update($campos);
                }
            });

        DB::table('multas')
            ->where('created_at', '<', $antes)
            ->chunkById(500, function ($multas) {
                foreach ($multas as $multa) {
                    DB::table('multas')->where('id', $multa->id)->update(['data_hora' => $this->somar($multa->data_hora)]);
                }
            });
    }

    private function somar(?string $valor): ?string
    {
        return $valor === null ? null : Carbon::parse($valor, 'UTC')->addHours(self::HORAS)->format('Y-m-d H:i:s');
    }
}
