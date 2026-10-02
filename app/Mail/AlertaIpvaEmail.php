<?php

namespace App\Mail;

use App\Models\Veiculo;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class AlertaIpvaEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Veiculo $veiculo,
        public string $vencimento,
        public bool $atrasado,
    ) {
    }

    public function build(): self
    {
        $assunto = $this->atrasado
            ? "IPVA atrasado: {$this->veiculo->modelo} ({$this->veiculo->placa})"
            : "IPVA se aproxima: {$this->veiculo->modelo} ({$this->veiculo->placa})";

        return $this->subject($assunto)
            ->markdown('emails.alerta-ipva', [
                'nome' => $this->veiculo->cliente->nome,
                'veiculoNome' => "{$this->veiculo->marca} {$this->veiculo->modelo}",
                'placa' => $this->veiculo->placa,
                'vencimentoFormatado' => Carbon::parse($this->vencimento)->format('d/m/Y'),
                'valorIpva' => $this->veiculo->ipva_estimado,
            ]);
    }
}
