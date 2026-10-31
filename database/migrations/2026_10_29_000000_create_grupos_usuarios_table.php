<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Grupo de usuários da frota: reúne pessoas e diz o que elas podem fazer,
        // somando-se ao que o papel (administrador, gestor, motorista) já permite.
        Schema::create('grupos_usuarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conta_id')->constrained('contas')->cascadeOnDelete();
            $table->string('nome', 80);
            $table->string('descricao', 255)->nullable();
            $table->json('permissoes');
            $table->timestamps();

            $table->unique(['conta_id', 'nome']);
        });

        Schema::create('grupo_usuario_membros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_usuario_id')->constrained('grupos_usuarios')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['grupo_usuario_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grupo_usuario_membros');
        Schema::dropIfExists('grupos_usuarios');
    }
};
