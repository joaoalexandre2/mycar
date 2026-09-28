<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Cria uma "Oficina Padrão" e move para ela qualquer usuário e dado
     * (cliente, veículo, ordem de serviço, manutenção) que já existisse
     * antes desta versão multi-oficina, para nada ficar sem dono.
     *
     * Também confirma o e-mail desses usuários antigos: eles existiam antes
     * da exigência de confirmação por e-mail, então travar o login deles
     * seria um bug, não uma proteção.
     */
    public function up(): void
    {
        $existemOrfaos = DB::table('users')->whereNull('oficina_id')->exists()
            || DB::table('clientes')->whereNull('oficina_id')->exists();

        if (!$existemOrfaos) {
            return;
        }

        $oficinaId = DB::table('oficinas')->insertGetId([
            'nome' => 'Oficina Padrão',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->whereNull('oficina_id')->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
        DB::table('users')->whereNull('oficina_id')->update(['oficina_id' => $oficinaId]);
        DB::table('clientes')->whereNull('oficina_id')->update(['oficina_id' => $oficinaId]);
        DB::table('veiculos')->whereNull('oficina_id')->update(['oficina_id' => $oficinaId]);
        DB::table('ordens_servico')->whereNull('oficina_id')->update(['oficina_id' => $oficinaId]);
        DB::table('manutencoes')->whereNull('oficina_id')->update(['oficina_id' => $oficinaId]);
    }

    public function down(): void
    {
        // Não reverte: apagar a Oficina Padrão desfaria a atribuição de dados
        // reais. Se precisar reverter, faça manualmente.
    }
};
