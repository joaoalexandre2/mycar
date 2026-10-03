<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('veiculo_pecas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('veiculo_id')->constrained('veiculos')->cascadeOnDelete();
            $table->foreignId('oficina_id')->constrained('oficinas')->cascadeOnDelete();

            $table->string('tipo', 30);
            $table->string('especificacao', 120);
            $table->string('marca', 60)->nullable();
            // ficha = especificação de referência (manual/catálogo);
            // servico = peça que de fato foi instalada num serviço.
            $table->string('fonte', 10);
            $table->date('usado_em')->nullable();
            $table->string('observacao', 255)->nullable();

            $table->timestamps();

            $table->index(['veiculo_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('veiculo_pecas');
    }
};
