<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Veículos de uma conta (pessoa ou frota). Diferente de "veiculos", que
        // são os carros dos clientes de uma oficina e exigem cliente_id.
        Schema::create('veiculos_conta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conta_id')->constrained('contas')->cascadeOnDelete();

            $table->string('apelido', 60)->nullable();
            $table->string('placa', 10);
            $table->string('marca', 100);
            $table->string('modelo', 100);
            $table->unsignedSmallInteger('ano');
            $table->string('uf', 2)->nullable();

            $table->unsignedInteger('fipe_marca_id')->nullable();
            $table->unsignedInteger('fipe_modelo_id')->nullable();
            $table->string('fipe_ano', 10)->nullable();
            $table->decimal('fipe_valor', 12, 2)->nullable();
            $table->timestamp('fipe_consultado_em')->nullable();

            $table->timestamps();

            // A mesma placa pode existir em contas diferentes (o carro muda de dono).
            $table->unique(['conta_id', 'placa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('veiculos_conta');
    }
};
