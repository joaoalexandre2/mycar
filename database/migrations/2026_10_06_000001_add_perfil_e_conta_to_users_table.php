<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // oficina | pessoa | frota. Todo usuário que já existe é de oficina.
            $table->string('perfil', 10)->default('oficina')->after('oficina_id');

            $table->foreignId('conta_id')
                ->nullable()
                ->after('perfil')
                ->constrained('contas')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('conta_id');
            $table->dropColumn('perfil');
        });
    }
};
