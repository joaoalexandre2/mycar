<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Centro de custo ao qual o motorista fica amarrado: as reservas dele saem nesse centro.
        Schema::table('motoristas', function (Blueprint $table) {
            $table->foreignId('centro_custo_id')->nullable()->after('ativo')->constrained('centros_custo')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('motoristas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('centro_custo_id');
        });
    }
};
