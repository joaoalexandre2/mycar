<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ficha de manutenção dos veículos de uma conta (pessoa/frota): os mesmos
        // campos da ficha da oficina, preenchidos pelo dono do carro.
        Schema::create('fichas_tecnicas_conta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conta_id')->constrained('contas')->cascadeOnDelete();
            $table->foreignId('veiculo_conta_id')->unique()->constrained('veiculos_conta')->cascadeOnDelete();

            $table->string('oleo_viscosidade', 20)->nullable();
            $table->string('oleo_especificacao', 60)->nullable();
            $table->decimal('oleo_capacidade_litros', 4, 1)->nullable();
            $table->string('filtro_oleo', 60)->nullable();
            $table->string('filtro_ar', 60)->nullable();
            $table->string('filtro_combustivel', 60)->nullable();
            $table->string('pneu_medida', 30)->nullable();
            $table->unsignedSmallInteger('pneu_pressao_dianteira')->nullable();
            $table->unsignedSmallInteger('pneu_pressao_traseira')->nullable();
            $table->text('observacoes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fichas_tecnicas_conta');
    }
};
