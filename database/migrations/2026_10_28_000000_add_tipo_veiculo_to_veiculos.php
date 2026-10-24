<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // carro | moto | caminhao. Quem já estava cadastrado era carro.
        foreach (['veiculos', 'veiculos_conta'] as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->string('tipo_veiculo', 10)->default('carro');
            });
        }
    }

    public function down(): void
    {
        foreach (['veiculos', 'veiculos_conta'] as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->dropColumn('tipo_veiculo');
            });
        }
    }
};
