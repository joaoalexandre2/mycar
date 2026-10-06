<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contas', function (Blueprint $table) {
            // Lembretes por e-mail de IPVA, licenciamento e revisão. Ligado por
            // padrão; a conta desliga em Configurações.
            $table->boolean('lembretes_email')->default(true)->after('nome');
        });

        Schema::table('veiculos_conta', function (Blueprint $table) {
            // Próxima revisão, informada pelo dono (a conta ainda não registra manutenções).
            $table->date('revisao_prevista_em')->nullable()->after('uf');

            // Controle para não repetir o mesmo aviso: ano do ciclo de IPVA e de
            // licenciamento já avisado, e a revisão já avisada.
            $table->unsignedSmallInteger('ipva_alertado_ano')->nullable();
            $table->unsignedSmallInteger('licenciamento_alertado_ano')->nullable();
            $table->date('revisao_alertada_em')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('veiculos_conta', function (Blueprint $table) {
            $table->dropColumn([
                'revisao_prevista_em',
                'ipva_alertado_ano',
                'licenciamento_alertado_ano',
                'revisao_alertada_em',
            ]);
        });

        Schema::table('contas', function (Blueprint $table) {
            $table->dropColumn('lembretes_email');
        });
    }
};
