<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicos_conta', function (Blueprint $table) {
            // Preventiva (planejada) ou corretiva (quebrou e foi consertado). Nulo nos serviços antigos.
            $table->string('natureza', 12)->nullable();
            $table->string('oficina', 80)->nullable();
            // O total é valor_pecas + valor_mao_obra quando a divisão é informada. "valor" continua o total.
            $table->decimal('valor_pecas', 12, 2)->nullable();
            $table->decimal('valor_mao_obra', 12, 2)->nullable();
        });

        Schema::create('servico_pecas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conta_id')->constrained('contas')->cascadeOnDelete();
            $table->foreignId('servico_id')->constrained('servicos_conta')->cascadeOnDelete();
            $table->string('descricao', 120);
            $table->decimal('quantidade', 10, 2)->default(1);
            $table->decimal('valor_unitario', 12, 2)->default(0);
            $table->timestamps();
        });

        // Nota fiscal, recibo ou orçamento do serviço. O arquivo fica no disco local, servido por link assinado.
        Schema::create('servico_anexos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conta_id')->constrained('contas')->cascadeOnDelete();
            $table->foreignId('servico_id')->constrained('servicos_conta')->cascadeOnDelete();
            $table->string('nome', 120);
            $table->string('caminho', 255);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servico_anexos');
        Schema::dropIfExists('servico_pecas');

        Schema::table('servicos_conta', function (Blueprint $table) {
            $table->dropColumn(['natureza', 'oficina', 'valor_pecas', 'valor_mao_obra']);
        });
    }
};
