<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CPF, telefone e placa eram únicos no sistema inteiro: uma oficina não podia
 * cadastrar um cliente ou carro que outra já tinha (e o erro revelava que o
 * dado existia lá). Passam a ser únicos dentro de cada oficina.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropUnique('clientes_cpf_unique');
            $table->dropUnique('clientes_telefone_unique');
            $table->unique(['oficina_id', 'cpf']);
            $table->unique(['oficina_id', 'telefone']);
        });

        Schema::table('veiculos', function (Blueprint $table) {
            $table->dropUnique('veiculos_placa_unique');
            $table->unique(['oficina_id', 'placa']);
        });
    }

    public function down(): void
    {
        Schema::table('veiculos', function (Blueprint $table) {
            $table->dropUnique(['oficina_id', 'placa']);
            $table->unique('placa');
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->dropUnique(['oficina_id', 'telefone']);
            $table->dropUnique(['oficina_id', 'cpf']);
            $table->unique('cpf');
            $table->unique('telefone');
        });
    }
};
