<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servicos_conta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conta_id')->constrained('contas')->cascadeOnDelete();
            $table->foreignId('veiculo_conta_id')->constrained('veiculos_conta')->cascadeOnDelete();

            $table->string('tipo', 16);               // ver config/servicos.php
            $table->string('titulo', 80)->nullable(); // obrigatório quando tipo = outro
            $table->date('realizado_em');
            $table->unsignedInteger('km')->nullable(); // km do veículo no dia do serviço
            $table->decimal('valor', 10, 2)->nullable();
            $table->string('observacoes', 255)->nullable();

            // Quando avisar a próxima vez: por prazo, por quilometragem, ou os dois.
            $table->unsignedSmallInteger('intervalo_meses')->nullable();
            $table->unsignedInteger('intervalo_km')->nullable();
            $table->date('proximo_em')->nullable();
            $table->unsignedInteger('proxima_km')->nullable();

            // Controle dos lembretes (um por critério).
            $table->date('alerta_data_em')->nullable();
            $table->date('alerta_km_em')->nullable();

            $table->timestamps();

            $table->index(['veiculo_conta_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servicos_conta');
    }
};
