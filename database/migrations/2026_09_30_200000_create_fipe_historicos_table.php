<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fipe_historicos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('veiculo_id')
                ->constrained('veiculos')
                ->cascadeOnDelete();

            $table->foreignId('oficina_id')
                ->constrained('oficinas')
                ->cascadeOnDelete();

            $table->decimal('valor', 10, 2);

            $table->timestamp('consultado_em');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fipe_historicos');
    }
};
