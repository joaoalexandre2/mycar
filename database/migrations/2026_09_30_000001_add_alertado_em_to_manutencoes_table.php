<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manutencoes', function (Blueprint $table) {
            $table->timestamp('alertado_em')->nullable()->after('proxima_quilometragem');
        });
    }

    public function down(): void
    {
        Schema::table('manutencoes', function (Blueprint $table) {
            $table->dropColumn('alertado_em');
        });
    }
};
