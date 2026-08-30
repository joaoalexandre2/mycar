<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            // $table->id();
            // $table->timestamps();
            $table->id();

            $table->string('nome', 150);

            $table->string('cpf', 14)->unique();

            $table->string('telefone', 20)->unique();

            $table->boolean('ativo');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
