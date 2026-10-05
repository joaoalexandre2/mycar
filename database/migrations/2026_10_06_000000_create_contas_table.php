<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Conta = inquilino dos perfis "Cuidados com seu carro" (pessoa) e
        // "Frota". A oficina continua sendo a tabela oficinas: os dois mundos
        // não compartilham dados.
        Schema::create('contas', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 10); // pessoa | frota
            $table->string('nome', 150);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contas');
    }
};
