<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Um token por aparelho/navegador: entrar no computador não derruba o celular.
        // O campo users.api_token continua valendo para as sessões abertas antes desta
        // mudança (somem no próximo login ou logout).
        Schema::create('tokens_acesso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('dispositivo', 150)->nullable();
            $table->timestamp('ultimo_uso_em')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'ultimo_uso_em']);
        });

        // Foto de perfil: imagem pequena (data URI) guardada junto do usuário.
        Schema::table('users', function (Blueprint $table) {
            $table->longText('foto')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('foto');
        });

        Schema::dropIfExists('tokens_acesso');
    }
};
