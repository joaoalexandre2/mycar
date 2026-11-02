<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Modelos de grupo que a própria frota salva para reusar (além dos prontos do sistema).
        Schema::create('modelos_grupo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conta_id')->constrained('contas')->cascadeOnDelete();
            $table->string('nome', 80);
            $table->string('descricao', 255)->nullable();
            $table->json('permissoes');
            $table->json('menus_liberados');
            $table->foreignId('criado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['conta_id', 'nome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modelos_grupo');
    }
};
