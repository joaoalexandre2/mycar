<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Acidente, avaria, dano, problema mecânico ou problema durante o uso. O checklist não substitui isto.
        Schema::create('ocorrencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conta_id')->constrained('contas')->cascadeOnDelete();
            $table->foreignId('veiculo_conta_id')->nullable()->constrained('veiculos_conta')->nullOnDelete();
            $table->foreignId('motorista_id')->nullable()->constrained('motoristas')->nullOnDelete();
            $table->foreignId('uso_veiculo_id')->nullable()->constrained('usos_veiculo')->nullOnDelete();
            $table->foreignId('registrado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tipo', 20);
            $table->dateTime('data_hora');
            $table->string('local', 160)->nullable();
            $table->text('descricao');
            $table->decimal('prejuizo_estimado', 12, 2)->nullable();
            $table->decimal('valor_gasto', 12, 2)->nullable();
            $table->string('status', 20)->default('aberta');
            // Quando há valor gasto, a ocorrência gera uma despesa na gestão financeira. Este vínculo evita duplicar.
            $table->foreignId('despesa_id')->nullable()->constrained('despesas')->nullOnDelete();
            $table->timestamps();

            $table->index(['conta_id', 'status']);
            $table->index(['veiculo_conta_id', 'data_hora']);
        });

        Schema::create('ocorrencia_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conta_id')->constrained('contas')->cascadeOnDelete();
            $table->foreignId('ocorrencia_id')->constrained('ocorrencias')->cascadeOnDelete();
            $table->string('momento', 10)->default('antes');
            $table->string('caminho', 255);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ocorrencia_fotos');
        Schema::dropIfExists('ocorrencias');
    }
};
