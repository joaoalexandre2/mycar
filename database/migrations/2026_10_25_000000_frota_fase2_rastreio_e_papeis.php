<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Papel dentro da frota: administrador, gestor ou motorista (só vale no perfil frota).
        Schema::table('users', function (Blueprint $table) {
            $table->string('papel_frota', 15)->nullable()->after('perfil');
        });

        // Quem já tinha conta de frota segue com acesso total.
        DB::table('users')->where('perfil', 'frota')->update(['papel_frota' => 'administrador']);

        Schema::table('usos_veiculo', function (Blueprint $table) {
            // Quem registrou a saída e a chegada, e de onde veio o km (foto ou digitado).
            $table->foreignId('saida_por_user_id')->nullable()->after('chegada_em')->constrained('users')->nullOnDelete();
            $table->foreignId('chegada_por_user_id')->nullable()->after('saida_por_user_id')->constrained('users')->nullOnDelete();
            $table->string('km_saida_origem', 10)->nullable()->after('km_saida');
            $table->string('km_chegada_origem', 10)->nullable()->after('km_chegada');
        });

        // Trilha de auditoria: cada passo e cada correção ficam registrados.
        Schema::create('usos_veiculo_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conta_id')->constrained('contas')->cascadeOnDelete();
            // Sem cascata: o evento sobrevive ao uso apagado.
            $table->foreignId('uso_veiculo_id')->nullable()->constrained('usos_veiculo')->nullOnDelete();
            $table->foreignId('veiculo_conta_id')->nullable()->constrained('veiculos_conta')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('acao', 30);
            $table->json('dados')->nullable();
            $table->string('motivo', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['uso_veiculo_id', 'created_at']);
            $table->index(['conta_id', 'created_at']);
        });

        // Todas as versões da foto do hodômetro: trocar a foto não apaga a anterior.
        Schema::create('usos_veiculo_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conta_id')->constrained('contas')->cascadeOnDelete();
            $table->foreignId('uso_veiculo_id')->constrained('usos_veiculo')->cascadeOnDelete();
            $table->string('momento', 10);
            $table->string('caminho');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('substituida_em')->nullable();
            // Retenção de 90 dias: o arquivo sai do disco, o registro fica.
            $table->timestamp('apagada_em')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['uso_veiculo_id', 'momento']);
            $table->index('created_at');
        });

        // Fotos já existentes entram na tabela de versões.
        $agora = now();

        foreach (['saida' => 'foto_saida', 'chegada' => 'foto_chegada'] as $momento => $coluna) {
            DB::table('usos_veiculo')->whereNotNull($coluna)->orderBy('id')->each(function ($uso) use ($momento, $coluna, $agora) {
                DB::table('usos_veiculo_fotos')->insert([
                    'conta_id' => $uso->conta_id,
                    'uso_veiculo_id' => $uso->id,
                    'momento' => $momento,
                    'caminho' => $uso->{$coluna},
                    'created_at' => $uso->updated_at ?? $agora,
                ]);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('usos_veiculo_fotos');
        Schema::dropIfExists('usos_veiculo_eventos');

        Schema::table('usos_veiculo', function (Blueprint $table) {
            $table->dropConstrainedForeignId('saida_por_user_id');
            $table->dropConstrainedForeignId('chegada_por_user_id');
            $table->dropColumn(['km_saida_origem', 'km_chegada_origem']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('papel_frota');
        });
    }
};
