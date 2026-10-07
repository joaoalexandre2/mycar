<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sugestões dos usuários para a equipe do MyCar. Não são dados de uma
        // oficina ou conta: pertencem a quem enviou e à equipe da plataforma.
        Schema::create('sugestoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('categoria', 20);          // melhoria | nova_funcao | problema | outro
            $table->string('titulo', 120);
            $table->text('descricao');
            $table->string('perfil', 10)->nullable();  // oficina | pessoa | frota, na hora do envio

            $table->string('status', 20)->default('nova'); // nova | em_analise | planejada | feita | recusada
            $table->text('resposta')->nullable();           // retorno da equipe ao usuário

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sugestoes');
    }
};
