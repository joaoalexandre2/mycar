<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contas_sociais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provedor', 20);
            $table->string('provedor_id');
            $table->timestamps();

            $table->unique(['provedor', 'provedor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contas_sociais');
    }
};
