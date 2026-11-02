<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Serviços do estabelecimento. Com dias_previstos > 0 é um serviço longo (vitrificação, PPF).
        Schema::create('servicos_estetica', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conta_id')->constrained('contas')->cascadeOnDelete();
            $table->string('nome', 120);
            $table->decimal('preco', 10, 2)->default(0);
            $table->unsignedSmallInteger('dias_previstos')->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['conta_id', 'nome']);
        });

        // Pacotes: combinações de serviços com preço próprio.
        Schema::create('pacotes_estetica', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conta_id')->constrained('contas')->cascadeOnDelete();
            $table->string('nome', 120);
            $table->decimal('preco', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('pacote_servico_estetica', function (Blueprint $table) {
            $table->foreignId('pacote_id')->constrained('pacotes_estetica')->cascadeOnDelete();
            $table->foreignId('servico_id')->constrained('servicos_estetica')->cascadeOnDelete();
            $table->primary(['pacote_id', 'servico_id']);
        });

        // Atendimento de um carro no pátio: entrada, em atendimento, entrega e entregue.
        Schema::create('atendimentos_estetica', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conta_id')->constrained('contas')->cascadeOnDelete();
            $table->foreignId('veiculo_conta_id')->nullable()->constrained('veiculos_conta')->nullOnDelete();
            $table->foreignId('pacote_id')->nullable()->constrained('pacotes_estetica')->nullOnDelete();
            $table->string('placa', 10);
            $table->string('modelo', 120)->nullable();
            $table->string('cliente_nome', 120)->nullable();
            $table->string('cliente_telefone', 30)->nullable();
            $table->string('etapa', 20)->default('entrada');
            $table->decimal('valor', 10, 2)->default(0);
            $table->string('forma_pagamento', 20)->nullable();
            $table->boolean('pago')->default(false);
            $table->dateTime('entrada_em');
            $table->dateTime('previsao_entrega')->nullable();
            $table->dateTime('entregue_em')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index(['conta_id', 'placa']);
            $table->index(['conta_id', 'etapa']);
        });

        // Serviços que entraram no atendimento, com o preço cobrado naquela hora.
        Schema::create('atendimento_servicos_estetica', function (Blueprint $table) {
            $table->id();
            $table->foreignId('atendimento_id')->constrained('atendimentos_estetica')->cascadeOnDelete();
            $table->foreignId('servico_id')->nullable()->constrained('servicos_estetica')->nullOnDelete();
            $table->string('nome', 120);
            $table->decimal('preco', 10, 2)->default(0);
        });

        // Fotos de check-in (entrada, avarias) e de checkout (entrega). Ficam no disco local, como as da frota.
        Schema::create('fotos_estetica', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conta_id')->constrained('contas')->cascadeOnDelete();
            $table->foreignId('atendimento_id')->constrained('atendimentos_estetica')->cascadeOnDelete();
            $table->string('momento', 12);
            $table->string('caminho');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['atendimento_id', 'momento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fotos_estetica');
        Schema::dropIfExists('atendimento_servicos_estetica');
        Schema::dropIfExists('atendimentos_estetica');
        Schema::dropIfExists('pacote_servico_estetica');
        Schema::dropIfExists('pacotes_estetica');
        Schema::dropIfExists('servicos_estetica');
    }
};
