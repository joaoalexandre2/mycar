<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Operador da plataforma (dono do MyCar). Nunca é preenchido por
            // cadastro ou por API: só pelo comando `admin:promover`.
            $table->boolean('is_super_admin')->default(false)->after('oficina_id');

            // Atualizado a cada login, para o painel mostrar quem está usando.
            $table->timestamp('ultimo_acesso_em')->nullable()->after('is_super_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_super_admin', 'ultimo_acesso_em']);
        });
    }
};
