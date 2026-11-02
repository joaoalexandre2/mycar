<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Categorias de despesa avulsa, por conta. Desativar em vez de apagar: as despesas antigas continuam com a sua.
        Schema::create('categorias_despesa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conta_id')->constrained('contas')->cascadeOnDelete();
            $table->string('nome', 60);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['conta_id', 'nome']);
        });

        // Despesas que não têm outro registro no sistema (pedágio, estacionamento, lavagem...).
        // Combustível, serviços e multas continuam nas suas tabelas e entram na consolidação sem duplicar.
        Schema::create('despesas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conta_id')->constrained('contas')->cascadeOnDelete();
            $table->foreignId('categoria_id')->constrained('categorias_despesa')->restrictOnDelete();
            $table->foreignId('veiculo_conta_id')->nullable()->constrained('veiculos_conta')->nullOnDelete();
            $table->foreignId('motorista_id')->nullable()->constrained('motoristas')->nullOnDelete();
            $table->foreignId('centro_custo_id')->nullable()->constrained('centros_custo')->nullOnDelete();
            $table->date('data');
            $table->decimal('valor', 12, 2);
            $table->string('descricao', 160)->nullable();
            // Número ou referência do comprovante (nota, recibo). O anexo em si fica para uma etapa seguinte.
            $table->string('comprovante', 80)->nullable();
            $table->timestamps();

            $table->index(['conta_id', 'data']);
            $table->index(['veiculo_conta_id', 'data']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('despesas');
        Schema::dropIfExists('categorias_despesa');
    }
};
