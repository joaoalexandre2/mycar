<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oficinas', function (Blueprint $table) {
            $table->string('cnpj', 18)->nullable()->after('nome');
            $table->string('telefone', 20)->nullable()->after('cnpj');
            $table->string('endereco', 200)->nullable()->after('telefone');
        });
    }

    public function down(): void
    {
        Schema::table('oficinas', function (Blueprint $table) {
            $table->dropColumn(['cnpj', 'telefone', 'endereco']);
        });
    }
};
