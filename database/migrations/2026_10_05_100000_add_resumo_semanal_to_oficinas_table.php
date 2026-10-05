<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oficinas', function (Blueprint $table) {
            // Resumo semanal por e-mail para a própria oficina (segunda-feira).
            // Ligado por padrão; a oficina desliga em Configurações > Oficina.
            $table->boolean('resumo_semanal')->default(true)->after('endereco');
        });
    }

    public function down(): void
    {
        Schema::table('oficinas', function (Blueprint $table) {
            $table->dropColumn('resumo_semanal');
        });
    }
};
