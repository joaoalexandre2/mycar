<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('veiculo_pecas', function (Blueprint $table) {
            // Peça registrada ao salvar uma manutenção. Ao apagar a manutenção,
            // as peças que vieram dela vão junto (a manutenção é a origem do
            // registro); as peças lançadas direto no veículo ficam como estão.
            $table->foreignId('manutencao_id')
                ->nullable()
                ->after('veiculo_id')
                ->constrained('manutencoes')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('veiculo_pecas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manutencao_id');
        });
    }
};
