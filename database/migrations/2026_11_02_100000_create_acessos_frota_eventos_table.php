<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Trilha de quem mudou acesso na frota: grupos, membros e papéis. Só se acrescenta.
        Schema::create('acessos_frota_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conta_id')->constrained('contas')->cascadeOnDelete();
            // Quem fez a mudança. Sem cascata: o evento sobrevive se a pessoa sair.
            $table->foreignId('ator_id')->nullable()->constrained('users')->nullOnDelete();
            // grupo ou pessoa, e o id dele (sem FK: o alvo pode ser apagado e o evento fica).
            $table->string('alvo_tipo', 20);
            $table->unsignedBigInteger('alvo_id')->nullable();
            $table->string('acao', 30);
            $table->json('detalhes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['conta_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acessos_frota_eventos');
    }
};
