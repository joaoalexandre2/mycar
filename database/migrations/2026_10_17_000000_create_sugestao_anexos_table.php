<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Imagens que o usuário anexa à sugestão (print de tela, foto do problema).
        // Arquivos no disco privado; só quem enviou e a equipe veem, por link assinado.
        Schema::create('sugestao_anexos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sugestao_id')->constrained('sugestoes')->cascadeOnDelete();
            $table->string('caminho', 255);
            $table->string('mime', 30);
            $table->unsignedInteger('tamanho');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sugestao_anexos');
    }
};
