<?php

namespace App\Mail;

use App\Models\Veiculo;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AlertaLicenciamentoEmail extends Mailable
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
            ? "Licenciamento atrasado: {$this->veiculo->modelo} ({$this->veiculo->placa})"
            : "Licenciamento se aproxima: {$this->veiculo->modelo} ({$this->veiculo->placa})";

        return $this->subject($assunto)
            ->markdown('emails.alerta-licenciamento', [
                'nome' => $this->veiculo->cliente->nome,
                'veiculoNome' => "{$this->veiculo->marca} {$this->veiculo->modelo}",
                'placa' => $this->veiculo->placa,
                'vencimentoFormatado' => \Illuminate\Support\Carbon::parse($this->vencimento)->format('d/m/Y'),
                'valorLicenciamento' => $this->veiculo->licenciamento_valor,
                'atrasado' => $this->atrasado,
            ]);
    }
}
