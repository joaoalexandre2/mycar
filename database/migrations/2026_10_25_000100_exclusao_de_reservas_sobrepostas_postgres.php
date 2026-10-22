<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Só no PostgreSQL: o banco passa a recusar duas reservas ativas do mesmo
 * veículo (ou do mesmo motorista) com horários que se sobrepõem, mesmo que
 * duas requisições cheguem ao mesmo tempo. No SQLite (desenvolvimento e
 * testes) a garantia continua só no código do controller.
 *
 * Se já houver reservas sobrepostas gravadas, a migration falha: corrija-as
 * antes de rodar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        DB::statement(<<<'SQL'
            ALTER TABLE usos_veiculo
            ADD CONSTRAINT usos_veiculo_sem_sobreposicao_veiculo
            EXCLUDE USING gist (
                veiculo_conta_id WITH =,
                tsrange(inicio_previsto, fim_previsto, '[)') WITH &&
            ) WHERE (status IN ('agendado', 'em_uso'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE usos_veiculo
            ADD CONSTRAINT usos_veiculo_sem_sobreposicao_motorista
            EXCLUDE USING gist (
                motorista_id WITH =,
                tsrange(inicio_previsto, fim_previsto, '[)') WITH &&
            ) WHERE (status IN ('agendado', 'em_uso') AND motorista_id IS NOT NULL)
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE usos_veiculo DROP CONSTRAINT IF EXISTS usos_veiculo_sem_sobreposicao_veiculo');
        DB::statement('ALTER TABLE usos_veiculo DROP CONSTRAINT IF EXISTS usos_veiculo_sem_sobreposicao_motorista');
    }
};
