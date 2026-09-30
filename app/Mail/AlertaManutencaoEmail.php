<?php

namespace App\Mail;

use App\Models\Manutencao;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AlertaManutencaoEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Manutencao $manutencao,
        public bool $atrasada,
    ) {
    }

    public function build(): self
    {
        $veiculo = $this->manutencao->veiculo;

        $assunto = $this->atrasada
            ? "Manutenção atrasada: {$veiculo->modelo} ({$veiculo->placa})"
            : "Manutenção se aproxima: {$veiculo->modelo} ({$veiculo->placa})";

        return $this->subject($assunto)
            ->markdown('emails.alerta-manutencao', [
                'nome' => $veiculo->cliente->nome,
                'veiculo' => "{$veiculo->marca} {$veiculo->modelo}",
                'placa' => $veiculo->placa,
                'tipo' => $this->manutencao->tipo,
                'proximaData' => $this->manutencao->proxima_data->format('d/m/Y'),
                'atrasada' => $this->atrasada,
            ]);
    }
}
