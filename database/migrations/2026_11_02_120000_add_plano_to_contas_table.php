<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Plano comercial da conta. Nulo = sem limite de veículos, então as contas que já existem não mudam.
        Schema::table('contas', function (Blueprint $table) {
            $table->string('plano', 20)->nullable()->after('tipo');
            $table->unsignedSmallInteger('limite_veiculos')->nullable()->after('plano');
        });
    }

    public function down(): void
    {
        Schema::table('contas', function (Blueprint $table) {
            $table->dropColumn(['plano', 'limite_veiculos']);
        });
    }
};
