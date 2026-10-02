<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Veículos que já tinham valor FIPE antes da tabela de histórico existir
    // ganham uma primeira linha, para a evolução não começar vazia.
    public function up(): void
    {
        $veiculos = DB::table('veiculos')
            ->whereNotNull('fipe_valor')
            ->whereNotNull('fipe_consultado_em')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('fipe_historicos')
                    ->whereColumn('fipe_historicos.veiculo_id', 'veiculos.id');
            })
            ->get(['id', 'oficina_id', 'fipe_valor', 'fipe_consultado_em']);

        foreach ($veiculos as $veiculo) {
            DB::table('fipe_historicos')->insert([
                'veiculo_id' => $veiculo->id,
                'oficina_id' => $veiculo->oficina_id,
                'valor' => $veiculo->fipe_valor,
                'consultado_em' => $veiculo->fipe_consultado_em,
            ]);
        }
    }

    public function down(): void
    {
    }
};
