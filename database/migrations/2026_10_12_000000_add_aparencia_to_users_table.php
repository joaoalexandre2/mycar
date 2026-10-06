<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Preferência de aparência de cada usuário. Null = nunca escolheu (visual padrão).
            $table->string('tema', 10)->nullable()->after('is_super_admin');
            $table->string('cor', 10)->nullable()->after('tema');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['tema', 'cor']);
        });
    }
};
