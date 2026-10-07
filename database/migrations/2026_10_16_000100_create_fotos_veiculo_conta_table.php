<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Álbum de fotos dos veículos de uma conta (pessoa/frota). Os arquivos ficam
        // no disco privado e são servidos por link assinado e temporário.
        Schema::create('fotos_veiculo_conta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conta_id')->constrained('contas')->cascadeOnDelete();
            $table->foreignId('veiculo_conta_id')->constrained('veiculos_conta')->cascadeOnDelete();

            $table->string('caminho', 255);
            $table->string('caminho_miniatura', 255);
            $table->string('mime', 30);
            $table->unsignedInteger('tamanho');
            $table->string('legenda', 120)->nullable();

            $table->timestamps();

            $table->index(['veiculo_conta_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fotos_veiculo_conta');
    }
};
