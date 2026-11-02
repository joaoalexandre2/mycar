<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Quantos itens do checklist têm problema, gravado na linha: o alerta filtra no banco, sem ler o JSON.
        Schema::table('checklists_uso', function (Blueprint $table) {
            $table->unsignedTinyInteger('problemas')->default(0)->after('observacoes');
            $table->index(['conta_id', 'problemas', 'created_at']);
        });

        DB::table('checklists_uso')->orderBy('id')->chunkById(200, function ($linhas) {
            foreach ($linhas as $linha) {
                $itens = json_decode($linha->itens, true) ?? [];
                $problemas = collect($itens)->where('ok', false)->count();

                DB::table('checklists_uso')->where('id', $linha->id)->update(['problemas' => $problemas]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('checklists_uso', function (Blueprint $table) {
            $table->dropIndex(['conta_id', 'problemas', 'created_at']);
            $table->dropColumn('problemas');
        });
    }
};
