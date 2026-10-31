<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Motivos cadastrados pela frota para trocar o centro de custo de um uso.
        Schema::create('motivos_troca_centro', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conta_id')->constrained('contas')->cascadeOnDelete();
            $table->string('nome', 120);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['conta_id', 'nome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('motivos_troca_centro');
    }
};
