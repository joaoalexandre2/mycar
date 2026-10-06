<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seguros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conta_id')->constrained('contas')->cascadeOnDelete();
            $table->foreignId('veiculo_conta_id')->constrained('veiculos_conta')->cascadeOnDelete();

            // apolice = o seguro que a pessoa tem; proposta = cotação que recebeu.
            $table->string('tipo', 10);
            $table->string('seguradora', 60);
            $table->decimal('valor_anual', 10, 2);
            $table->decimal('franquia', 10, 2)->nullable();
            $table->date('vigencia_fim')->nullable();
            $table->string('observacoes', 255)->nullable();

            // Controle do lembrete de renovação (só apólice).
            $table->date('alertado_em')->nullable();

            $table->timestamps();

            $table->index(['veiculo_conta_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seguros');
    }
};
