<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('veiculos_conta', function (Blueprint $table) {
            // Quilometragem atual: sobe com cada saída ou chegada de uso (nunca desce sem correção manual).
            $table->unsignedInteger('km_atual')->nullable()->after('fipe_consultado_em');
            $table->timestamp('km_atualizado_em')->nullable()->after('km_atual');
            // Centro de custo padrão do veículo, usado quando o uso não informa outro.
            $table->foreignId('centro_custo_id')->nullable()->after('km_atualizado_em')
                ->constrained('centros_custo')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('veiculos_conta', function (Blueprint $table) {
            $table->dropConstrainedForeignId('centro_custo_id');
            $table->dropColumn(['km_atual', 'km_atualizado_em']);
        });
    }
};
