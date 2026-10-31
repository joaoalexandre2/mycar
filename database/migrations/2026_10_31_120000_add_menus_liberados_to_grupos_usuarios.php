<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Menus da frota que o grupo libera a quem pertence a ele. Vazio = libera nenhum.
        Schema::table('grupos_usuarios', function (Blueprint $table) {
            $table->json('menus_liberados')->nullable()->after('permissoes');
        });
    }

    public function down(): void
    {
        Schema::table('grupos_usuarios', function (Blueprint $table) {
            $table->dropColumn('menus_liberados');
        });
    }
};
