<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('manutencoes:alertar')->daily();
Schedule::command('veiculos:alertar-licenciamento')->daily();
Schedule::command('veiculos:alertar-ipva')->daily();

// Resumo para a própria oficina, segunda-feira de manhã (horário de Brasília,
// já que o fuso do app é UTC).
Schedule::command('oficinas:resumo-semanal')
    ->weeklyOn(1, '08:00')
    ->timezone('America/Sao_Paulo');
