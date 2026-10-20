<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usos_veiculo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conta_id')->constrained('contas')->cascadeOnDelete();
            $table->foreignId('veiculo_conta_id')->constrained('veiculos_conta')->cascadeOnDelete();
            // Apagar o motorista ou o centro não apaga o histórico de uso.
            $table->foreignId('motorista_id')->nullable()->constrained('motoristas')->nullOnDelete();
            $table->foreignId('centro_custo_id')->nullable()->constrained('centros_custo')->nullOnDelete();

            // Janela reservada na agenda (ou, na planilha, o período realizado).
            $table->dateTime('inicio_previsto');
            $table->dateTime('fim_previsto');
            $table->string('finalidade', 160)->nullable();

            // agendado -> em_uso (saída) -> concluido (chegada); cancelado sai da agenda.
            $table->string('status', 15)->default('agendado');

            $table->unsignedInteger('km_saida')->nullable();
            $table->unsignedInteger('km_chegada')->nullable();
            $table->dateTime('saida_em')->nullable();
            $table->dateTime('chegada_em')->nullable();
            // Foto do hodômetro: arquivo no disco privado (link assinado para ver).
            $table->string('foto_saida')->nullable();
            $table->string('foto_chegada')->nullable();

            // agenda: reserva feita no app; planilha: uso importado de uma planilha.
            $table->string('origem', 15)->default('agenda');
            $table->text('observacoes')->nullable();

            $table->timestamps();

            // Conflito de horário: a busca é sempre por veículo e janela.
            $table->index(['veiculo_conta_id', 'inicio_previsto', 'fim_previsto']);
            $table->index(['motorista_id', 'inicio_previsto']);
            $table->index(['conta_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usos_veiculo');
    }
};
