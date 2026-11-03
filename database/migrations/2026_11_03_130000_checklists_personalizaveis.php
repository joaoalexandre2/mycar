<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Modelo de checklist de uma empresa: o que o motorista confere, em saída, devolução ou nos dois.
        Schema::create('checklist_modelos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conta_id')->constrained('contas')->cascadeOnDelete();
            $table->string('nome', 80);
            $table->string('momento', 10)->default('ambos');
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['conta_id', 'nome']);
        });

        Schema::create('checklist_modelo_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conta_id')->constrained('contas')->cascadeOnDelete();
            $table->foreignId('checklist_modelo_id')->constrained('checklist_modelos')->cascadeOnDelete();
            // A chave é o que vai para o banco (estável); o rótulo é o que o motorista lê, e pode mudar.
            $table->string('chave', 40);
            $table->string('rotulo', 80);
            $table->boolean('obrigatorio')->default(true);
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();

            $table->unique(['checklist_modelo_id', 'chave']);
        });

        Schema::table('checklists_uso', function (Blueprint $table) {
            // Qual modelo foi usado. Nulo nos checklists antigos, que seguem os itens fixos.
            $table->foreignId('checklist_modelo_id')->nullable()->constrained('checklist_modelos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('checklists_uso', function (Blueprint $table) {
            $table->dropConstrainedForeignId('checklist_modelo_id');
        });

        Schema::dropIfExists('checklist_modelo_itens');
        Schema::dropIfExists('checklist_modelos');
    }
};
