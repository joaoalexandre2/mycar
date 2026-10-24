<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Um mesmo e-mail pode ter mais de um MyCar (oficina, pessoa e frota).
        // O MyCar "principal" continua nas colunas do próprio usuário
        // (perfil, oficina_id, conta_id, papel_frota); esta tabela guarda os
        // outros, criados depois pela própria pessoa.
        Schema::create('acessos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('perfil', 20);
            $table->foreignId('oficina_id')->nullable()->constrained('oficinas')->cascadeOnDelete();
            $table->foreignId('conta_id')->nullable()->constrained('contas')->cascadeOnDelete();
            $table->string('papel_frota', 20)->nullable();
            $table->timestamps();

            // No máximo um MyCar de cada tipo por pessoa.
            $table->unique(['user_id', 'perfil']);
        });

        // Qual MyCar está aberto em cada aparelho: o celular pode estar na frota
        // enquanto o computador está na oficina. Nulo = o principal.
        Schema::table('tokens_acesso', function (Blueprint $table) {
            $table->foreignId('acesso_id')->nullable()->constrained('acessos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tokens_acesso', function (Blueprint $table) {
            $table->dropConstrainedForeignId('acesso_id');
        });

        Schema::dropIfExists('acessos');
    }
};
