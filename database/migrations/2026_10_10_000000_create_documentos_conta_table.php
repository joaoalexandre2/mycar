<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos_conta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conta_id')->constrained('contas')->cascadeOnDelete();
            $table->foreignId('veiculo_conta_id')->constrained('veiculos_conta')->cascadeOnDelete();

            $table->string('tipo', 12);              // crlv | vistoria | outro
            $table->string('titulo', 80)->nullable(); // obrigatório quando tipo = outro
            $table->date('vencimento')->nullable();
            $table->string('observacoes', 255)->nullable();

            // Controle do lembrete de vencimento.
            $table->date('alertado_em')->nullable();

            $table->timestamps();

            $table->index(['veiculo_conta_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_conta');
    }
};
