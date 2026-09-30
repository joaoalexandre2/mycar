<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('veiculos', function (Blueprint $table) {
            $table->unsignedInteger('fipe_marca_id')->nullable()->after('ano');
            $table->unsignedInteger('fipe_modelo_id')->nullable()->after('fipe_marca_id');
            $table->string('fipe_ano', 10)->nullable()->after('fipe_modelo_id');
            $table->decimal('fipe_valor', 12, 2)->nullable()->after('fipe_ano');
            $table->timestamp('fipe_consultado_em')->nullable()->after('fipe_valor');
        });
    }

    public function down(): void
    {
        Schema::table('veiculos', function (Blueprint $table) {
            $table->dropColumn([
                'fipe_marca_id',
                'fipe_modelo_id',
                'fipe_ano',
                'fipe_valor',
                'fipe_consultado_em',
            ]);
        });
    }
};
