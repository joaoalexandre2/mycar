<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Resposta de "este veículo já tem seguro?". Nulo = ainda não respondeu.
        // Quem tem apólice cadastrada conta como "tem" sem precisar desta coluna.
        Schema::table('veiculos_conta', function (Blueprint $table) {
            $table->boolean('possui_seguro')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('veiculos_conta', function (Blueprint $table) {
            $table->dropColumn('possui_seguro');
        });
    }
};
