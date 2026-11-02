<?php

namespace App\Console\Commands;

use App\Models\Conta;
use Illuminate\Console\Command;

/**
 * Define o plano comercial de uma conta da frota. Quem opera a plataforma usa isto
 * enquanto não há cobrança automática. `livre` tira o limite de veículos.
 */
class DefinirPlanoConta extends Command
{
    protected $signature = 'conta:plano {conta : ID da conta} {plano : starter, basico, profissional, empresarial ou livre}';

    protected $description = 'Define o plano comercial (e o limite de veículos) de uma conta da frota';

    public function handle(): int
    {
        $conta = Conta::find($this->argument('conta'));

        if (!$conta || $conta->tipo !== Conta::TIPO_FROTA) {
            $this->error('Conta da frota não encontrada.');

            return self::FAILURE;
        }

        $plano = (string) $this->argument('plano');

        if ($plano === 'livre') {
            $conta->forceFill(['plano' => null, 'limite_veiculos' => null])->save();
            $this->info("Conta {$conta->id}: sem limite de veículos.");

            return self::SUCCESS;
        }

        if (!array_key_exists($plano, Conta::PLANOS)) {
            $this->error('Plano inválido. Use: ' . implode(', ', array_keys(Conta::PLANOS)) . ' ou livre.');

            return self::FAILURE;
        }

        $conta->forceFill(['plano' => $plano, 'limite_veiculos' => Conta::PLANOS[$plano]])->save();
        $this->info("Conta {$conta->id}: plano {$plano}, até " . Conta::PLANOS[$plano] . ' veículos.');

        return self::SUCCESS;
    }
}
