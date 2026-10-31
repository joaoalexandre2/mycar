<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Histórico dos centros de custo de cada uso: o que foi informado e cada
        // troca feita depois, para os relatórios saberem de onde veio o custo.
        // Guarda também o nome (o centro pode ser renomeado ou desativado) e
        // sobrevive ao uso apagado.
        Schema::create('usos_veiculo_centros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conta_id')->constrained('contas')->cascadeOnDelete();
            $table->foreignId('uso_veiculo_id')->nullable()->constrained('usos_veiculo')->nullOnDelete();
            $table->foreignId('veiculo_conta_id')->nullable()->constrained('veiculos_conta')->nullOnDelete();
            $table->foreignId('centro_anterior_id')->nullable()->constrained('centros_custo')->nullOnDelete();
            $table->string('centro_anterior_nome', 80)->nullable();
            $table->foreignId('centro_novo_id')->nullable()->constrained('centros_custo')->nullOnDelete();
            $table->string('centro_novo_nome', 80)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['uso_veiculo_id', 'created_at']);
            $table->index(['conta_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usos_veiculo_centros');
    }
};
