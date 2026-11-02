<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Token do QR Code colado no veículo: quem escaneia cai na tela do carro.
        Schema::table('veiculos_conta', function (Blueprint $table) {
            $table->string('qr_token', 40)->nullable()->unique()->after('km_atual');
        });

        // Checklist de saída e de devolução de cada reserva, com a resposta de cada item.
        Schema::create('checklists_uso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conta_id')->constrained('contas')->cascadeOnDelete();
            $table->foreignId('uso_veiculo_id')->constrained('usos_veiculo')->cascadeOnDelete();
            $table->string('momento', 10);
            $table->json('itens');
            $table->text('observacoes')->nullable();
            $table->foreignId('feito_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['uso_veiculo_id', 'momento']);
        });

        // Multas, com o uso (reserva) e o motorista que estavam com o veículo na data e hora.
        Schema::create('multas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conta_id')->constrained('contas')->cascadeOnDelete();
            $table->foreignId('veiculo_conta_id')->constrained('veiculos_conta')->cascadeOnDelete();
            $table->foreignId('uso_veiculo_id')->nullable()->constrained('usos_veiculo')->nullOnDelete();
            $table->foreignId('motorista_id')->nullable()->constrained('motoristas')->nullOnDelete();
            $table->dateTime('data_hora');
            $table->string('descricao', 160);
            $table->decimal('valor', 10, 2)->nullable();
            $table->unsignedTinyInteger('pontos')->nullable();
            $table->string('situacao', 20)->default('aberta');
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index(['conta_id', 'situacao']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('multas');
        Schema::dropIfExists('checklists_uso');

        Schema::table('veiculos_conta', function (Blueprint $table) {
            $table->dropColumn('qr_token');
        });
    }
};
