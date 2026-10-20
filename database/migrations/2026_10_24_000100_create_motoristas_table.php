<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('motoristas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conta_id')->constrained('contas')->cascadeOnDelete();

            $table->string('nome', 120);
            $table->string('telefone', 30)->nullable();
            $table->string('cnh_numero', 20)->nullable();
            $table->date('cnh_validade')->nullable();
            // Motorista com login no sistema (o convite vem depois): fica vazio para quem é só cadastro.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('ativo')->default(true);

            $table->timestamps();

            $table->unique(['conta_id', 'nome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('motoristas');
    }
};
