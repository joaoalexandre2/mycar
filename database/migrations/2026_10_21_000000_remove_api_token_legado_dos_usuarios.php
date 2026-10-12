<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * users.api_token guardava um único token por usuário (o hash). Desde que cada
 * aparelho passou a ter o seu em tokens_acesso, o campo só existia para
 * sessões abertas antes disso, e ficava como um segundo caminho de
 * autenticação sem expiração nem limite.
 *
 * Aqui as sessões ainda abertas viram sessões de aparelho (o valor já é o
 * hash, então o mesmo token continua funcionando) e o campo some.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'api_token')) {
            return;
        }

        DB::table('users')
            ->whereNotNull('api_token')
            ->orderBy('id')
            ->each(function ($usuario) {
                $jaMigrado = DB::table('tokens_acesso')
                    ->where('token_hash', $usuario->api_token)
                    ->exists();

                if ($jaMigrado) {
                    return;
                }

                DB::table('tokens_acesso')->insert([
                    'user_id' => $usuario->id,
                    'token_hash' => $usuario->api_token,
                    'dispositivo' => 'Sessão anterior',
                    // Quem não entra há mais de 90 dias perde a sessão pela regra
                    // de expiração normal.
                    'ultimo_uso_em' => $usuario->ultimo_acesso_em ?? now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['api_token']);
            $table->dropColumn('api_token');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'api_token')) {
            return;
        }

        // Os tokens migrados continuam em tokens_acesso; o campo volta vazio.
        Schema::table('users', function (Blueprint $table) {
            $table->string('api_token', 64)->unique()->nullable()->after('password');
        });
    }
};
