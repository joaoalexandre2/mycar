<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Marca as correções de dados que já rodaram, para não aplicar a mesma duas vezes (ver frota:corrigir-fuso-antigo).
        Schema::create('correcoes_de_dados', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 60)->unique();
            $table->timestamp('executada_em');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correcoes_de_dados');
    }
};
