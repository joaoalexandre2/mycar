<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abastecimentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conta_id')->constrained('contas')->cascadeOnDelete();
            $table->foreignId('veiculo_conta_id')->constrained('veiculos_conta')->cascadeOnDelete();

            $table->date('data');
            $table->unsignedInteger('km');
            $table->decimal('litros', 8, 3);
            $table->decimal('valor_total', 10, 2);
            // Só os abastecimentos com tanque cheio fecham um intervalo de consumo.
            $table->boolean('tanque_cheio')->default(true);
            $table->string('combustivel', 20)->nullable();
            $table->string('posto', 80)->nullable();

            $table->timestamps();

            $table->index(['veiculo_conta_id', 'data']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abastecimentos');
    }
};
