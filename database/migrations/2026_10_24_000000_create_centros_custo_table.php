<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('centros_custo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conta_id')->constrained('contas')->cascadeOnDelete();

            $table->string('nome', 80);
            // Código do centro no sistema contábil da empresa (opcional).
            $table->string('codigo', 30)->nullable();
            // Desativar em vez de apagar: usos antigos continuam apontando para ele.
            $table->boolean('ativo')->default(true);

            $table->timestamps();

            $table->unique(['conta_id', 'nome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('centros_custo');
    }
};
