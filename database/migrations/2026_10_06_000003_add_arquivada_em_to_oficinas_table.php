<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oficinas', function (Blueprint $table) {
            // Oficina cujo dono migrou para outro perfil (admin:converter-perfil).
            // Os dados ficam guardados, mas ela deixa de receber avisos e de
            // aparecer nas contagens.
            $table->timestamp('arquivada_em')->nullable()->after('resumo_semanal');
        });
    }

    public function down(): void
    {
        Schema::table('oficinas', function (Blueprint $table) {
            $table->dropColumn('arquivada_em');
        });
    }
};
