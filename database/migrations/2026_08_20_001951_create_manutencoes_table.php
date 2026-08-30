<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manutencoes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('veiculo_id')
                ->constrained('veiculos')
                ->cascadeOnDelete();

            $table->string('tipo');

            $table->text('descricao')->nullable();

            $table->decimal('valor', 10, 2)->nullable();

            $table->date('data_manutencao');

            $table->unsignedInteger('quilometragem')->nullable();

            $table->date('proxima_data')->nullable();

            $table->unsignedInteger('proxima_quilometragem')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manutencoes');
    }
};