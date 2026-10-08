<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tabelas de veículos: clientes de oficina e pessoa/frota. */
    private const TABELAS = ['veiculos', 'veiculos_conta'];

    public function up(): void
    {
        // "ano" continua sendo o ano do MODELO (o da tabela FIPE). O de fabricação é
        // opcional: o CRLV mostra os dois (ex.: 2018/2019).
        foreach (self::TABELAS as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->unsignedSmallInteger('ano_fabricacao')->nullable()->after('ano');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABELAS as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->dropColumn('ano_fabricacao');
            });
        }
    }
};
