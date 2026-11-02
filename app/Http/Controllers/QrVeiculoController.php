<?php

namespace App\Http\Controllers;

use App\Models\ChecklistUso;
use App\Models\UsoVeiculo;
use App\Models\VeiculoConta;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * QR Code de cada veículo. Quem escaneia cai na tela do carro e vê as reservas de hoje:
 * o motorista só as próprias, e administrador e gestor todas.
 */
class QrVeiculoController extends Controller
{
    /** Gera (uma vez) o token do QR do veículo e devolve o endereço que o QR abre. */
    public function gerar($id)
    {
        $veiculo = VeiculoConta::find($id);

        if (!$veiculo) {
            return response()->json(['message' => 'Veículo não encontrado.'], 404);
        }

        if (!$veiculo->qr_token) {
            do {
                $token = Str::lower(Str::random(32));
            } while (VeiculoConta::withoutGlobalScopes()->where('qr_token', $token)->exists());

            $veiculo->forceFill(['qr_token' => $token])->save();
        }

        return response()->json([
            'token' => $veiculo->qr_token,
            'url' => rtrim(config('app.frontend_url'), '/') . '/qr/' . $veiculo->qr_token,
        ]);
    }

    public function resolver(Request $request, string $token)
    {
        $veiculo = VeiculoConta::where('qr_token', $token)->first();

        if (!$veiculo) {
            return response()->json(['message' => 'QR Code não reconhecido para a sua frota.'], 404);
        }

        $ehMotorista = $request->user()->papelNaConta() === 'motorista';

        $reservas = UsoVeiculo::query()
            ->with('motorista')
            ->where('veiculo_conta_id', $veiculo->id)
            ->whereIn('status', ['agendado', 'em_uso'])
            ->where('inicio_previsto', '<=', now()->addHours(12))
            ->where('fim_previsto', '>=', now()->subHours(12))
            ->when($ehMotorista, fn ($q) => $q->where('motorista_id', $request->user()->motoristaVinculado()?->id ?? 0))
            ->orderBy('inicio_previsto')
            ->get()
            ->map(fn (UsoVeiculo $u) => [
                'id' => $u->id,
                'status' => $u->status,
                'momento' => $u->status === 'em_uso' ? ChecklistUso::DEVOLUCAO : ChecklistUso::SAIDA,
                'inicio' => $u->inicio_previsto?->toIso8601String(),
                'fim' => $u->fim_previsto?->toIso8601String(),
                'motorista' => $u->motorista?->nome,
                'finalidade' => $u->finalidade,
                'checklist_feito' => ChecklistUso::where('uso_veiculo_id', $u->id)
                    ->where('momento', $u->status === 'em_uso' ? ChecklistUso::DEVOLUCAO : ChecklistUso::SAIDA)
                    ->exists(),
            ])
            ->values();

        return response()->json([
            'veiculo' => [
                'id' => $veiculo->id,
                'placa' => $veiculo->placa,
                'nome' => trim(($veiculo->apelido ? $veiculo->apelido . ' · ' : '') . $veiculo->marca . ' ' . $veiculo->modelo),
                'km_atual' => $veiculo->km_atual,
            ],
            'reservas' => $reservas,
        ]);
    }
}
