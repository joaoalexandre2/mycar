<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('codigos_pecas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conta_id')->constrained('contas')->cascadeOnDelete();
            $table->foreignId('veiculo_conta_id')->constrained('veiculos_conta')->cascadeOnDelete();

            // Identificador da peça no catálogo (slug do nome, ex.: amortecedor-dianteiro).
            $table->string('peca_id', 100);
            $table->string('marca', 60)->nullable();
            $table->string('codigo', 60);
            $table->string('observacoes', 255)->nullable();

            $table->timestamps();

            $table->index(['veiculo_conta_id', 'peca_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('codigos_pecas');
    }
};
