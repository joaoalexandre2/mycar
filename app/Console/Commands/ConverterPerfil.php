<?php

namespace App\Console\Commands;

use App\Models\Conta;
use App\Models\Oficina;
use App\Models\User;
use App\Models\Veiculo;
use App\Models\VeiculoConta;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Migra um usuário de oficina para o perfil pessoa ("Cuidados com seu carro")
 * ou frota, sem perder nada:
 *
 * - os veículos da oficina são COPIADOS para a conta nova, com os códigos e o
 *   valor FIPE, então IPVA, licenciamento e vencimentos (calculados da placa,
 *   do estado e do valor FIPE) continuam iguais;
 * - clientes, ordens de serviço, manutenções, peças e fichas NÃO são apagados:
 *   a oficina fica arquivada no banco, escondida e sem receber avisos;
 * - com --reverter o usuário volta a ser oficina, com tudo como estava.
 */
class ConverterPerfil extends Command
{
    protected $signature = 'admin:converter-perfil
        {email : E-mail do usuário}
        {--para=pessoa : Perfil de destino: pessoa ou frota}
        {--nome-conta= : Nome da conta (obrigatório para frota; para pessoa o padrão é o nome do usuário)}
        {--reverter : Volta o usuário para o perfil oficina}
        {--dry-run : Mostra o que seria feito, sem gravar nada}';

    protected $description = 'Migra um usuário de oficina para Cuidados com seu carro (pessoa) ou Frota, mantendo os veículos, a FIPE e os impostos';

    public function handle(): int
    {
        $usuario = User::where('email', $this->argument('email'))->first();

        if (!$usuario) {
            $this->error('Nenhum usuário com esse e-mail.');

            return self::FAILURE;
        }

        return $this->option('reverter')
            ? $this->reverter($usuario)
            : $this->converter($usuario);
    }

    private function converter(User $usuario): int
    {
        $para = (string) $this->option('para');

        if (!in_array($para, Conta::TIPOS, true)) {
            $this->error('Use --para=pessoa ou --para=frota.');

            return self::FAILURE;
        }

        if ($usuario->perfil !== 'oficina' || $usuario->oficina_id === null) {
            $this->error("{$usuario->email} não é um usuário de oficina (perfil atual: {$usuario->perfil}).");

            return self::FAILURE;
        }

        $nomeConta = trim((string) $this->option('nome-conta'));

        if ($para === Conta::TIPO_FROTA && $nomeConta === '') {
            $this->error('Para frota informe o nome da empresa com --nome-conta="...".');

            return self::FAILURE;
        }

        $nomeConta = $nomeConta !== '' ? $nomeConta : $usuario->name;
        $oficina = Oficina::find($usuario->oficina_id);
        $veiculos = Veiculo::withoutGlobalScopes()->where('oficina_id', $usuario->oficina_id)->get();

        $this->line("Usuário: {$usuario->name} <{$usuario->email}>");
        $this->line("Oficina: {$oficina?->nome} ({$veiculos->count()} veículo(s) para copiar)");
        $this->line("Destino: perfil {$para}, conta \"{$nomeConta}\"");

        if ($this->option('dry-run')) {
            $this->info('Teste: nada foi alterado.');

            return self::SUCCESS;
        }

        $copiados = 0;

        DB::transaction(function () use ($usuario, $para, $nomeConta, $veiculos, $oficina, &$copiados) {
            $conta = $usuario->conta_id
                ? Conta::find($usuario->conta_id)
                : null;

            if ($conta) {
                $conta->update(['tipo' => $para, 'nome' => $nomeConta]);
            } else {
                $conta = Conta::create(['tipo' => $para, 'nome' => $nomeConta]);
            }

            foreach ($veiculos as $veiculo) {
                // Rodar de novo não duplica: o que já foi copiado fica como está.
                $jaExiste = VeiculoConta::withoutGlobalScopes()
                    ->where('conta_id', $conta->id)
                    ->where('placa', $veiculo->placa)
                    ->exists();

                if ($jaExiste) {
                    continue;
                }

                (new VeiculoConta())->forceFill([
                    'conta_id' => $conta->id,
                    'placa' => $veiculo->placa,
                    'marca' => $veiculo->marca,
                    'modelo' => $veiculo->modelo,
                    'ano' => $veiculo->ano,
                    'uf' => $veiculo->uf,
                    'fipe_marca_id' => $veiculo->fipe_marca_id,
                    'fipe_modelo_id' => $veiculo->fipe_modelo_id,
                    'fipe_ano' => $veiculo->fipe_ano,
                    'fipe_valor' => $veiculo->fipe_valor,
                    'fipe_consultado_em' => $veiculo->fipe_consultado_em,
                ])->save();

                $copiados++;
            }

            // Fica com oficina_id para a conversão poder ser revertida.
            $usuario->forceFill(['perfil' => $para, 'conta_id' => $conta->id])->save();

            // Só arquiva a oficina se ninguém mais a usa como oficina.
            $outrosDaOficina = User::where('oficina_id', $oficina->id)
                ->where('id', '!=', $usuario->id)
                ->where('perfil', 'oficina')
                ->exists();

            if (!$outrosDaOficina) {
                $oficina->forceFill(['arquivada_em' => now()])->save();
            }
        });

        $this->info("Pronto: {$usuario->email} agora é {$para}. {$copiados} veículo(s) copiado(s).");
        $this->line('Os dados da oficina (clientes, OS, manutenções, peças) continuam guardados; use --reverter para voltar.');

        return self::SUCCESS;
    }

    private function reverter(User $usuario): int
    {
        if ($usuario->perfil === 'oficina') {
            $this->error("{$usuario->email} já é um usuário de oficina.");

            return self::FAILURE;
        }

        if ($usuario->oficina_id === null) {
            $this->error('Este usuário nunca teve uma oficina: não há o que reverter.');

            return self::FAILURE;
        }

        $this->line("Usuário: {$usuario->name} <{$usuario->email}> (perfil atual: {$usuario->perfil})");

        if ($this->option('dry-run')) {
            $this->info('Teste: nada foi alterado.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($usuario) {
            $usuario->forceFill(['perfil' => 'oficina'])->save();
            Oficina::whereKey($usuario->oficina_id)->update(['arquivada_em' => null]);
        });

        $this->info("Pronto: {$usuario->email} voltou a ser oficina. Os veículos da conta ficam guardados.");

        return self::SUCCESS;
    }
}
