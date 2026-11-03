<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('abastecimentos', function (Blueprint $table) {
            // Quem abasteceu, e a hora (o registro antes tinha só a data).
            $table->foreignId('motorista_id')->nullable()->constrained('motoristas')->nullOnDelete();
            $table->time('hora')->nullable();

            // Justificativa de quem registrou um dado que parece fora do padrão (km menor, possível duplicado).
            $table->string('justificativa', 255)->nullable();

            // Alertas de inconsistência. Não bloqueiam: ficam visíveis para quem corrige.
            $table->json('alertas')->nullable();

            // Caminhos das fotos (comprovante e hodômetro), guardados no disco local.
            $table->string('comprovante_caminho', 255)->nullable();
            $table->string('hodometro_caminho', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('abastecimentos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('motorista_id');
            $table->dropColumn(['hora', 'justificativa', 'alertas', 'comprovante_caminho', 'hodometro_caminho']);
        });
    }
};
