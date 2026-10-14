<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Foto escolhida pelo dono como capa do veículo (no máximo uma por veículo).
        Schema::table('fotos_veiculo_conta', function (Blueprint $table) {
            $table->boolean('capa')->default(false)->after('legenda');
        });
    }

    public function down(): void
    {
        Schema::table('fotos_veiculo_conta', function (Blueprint $table) {
            $table->dropColumn('capa');
        });
    }
};
