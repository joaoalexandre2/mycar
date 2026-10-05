<?php

namespace App\Mail;

use App\Models\Oficina;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResumoSemanalEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param array<string, mixed> $resumo saída de ResumoSemanalService::montar()
     */
    public function __construct(
        public Oficina $oficina,
        public array $resumo,
    ) {
    }

    public function build(): self
    {
        $atrasadas = $this->resumo['manutencoes_atrasadas']['total'];
        $proximas = $this->resumo['manutencoes_proximas']['total'];

        $partes = [];
        if ($atrasadas > 0) {
            $partes[] = "{$atrasadas} manutenç" . ($atrasadas === 1 ? 'ão atrasada' : 'ões atrasadas');
        }
        if ($proximas > 0) {
            $partes[] = "{$proximas} a vencer";
        }
        if ($partes === []) {
            $partes[] = 'vencimentos de IPVA e licenciamento';
        }

        return $this->subject("Resumo da semana - {$this->oficina->nome}: " . implode(', ', $partes))
            ->markdown('emails.resumo-semanal', [
                // Chaves distintas das propriedades públicas (oficina, resumo):
                // elas são injetadas na view e sobrescreveriam as de mesmo nome.
                'nomeOficina' => $this->oficina->nome,
                'secoes' => $this->resumo,
                'urlSistema' => rtrim((string) config('app.frontend_url'), '/'),
            ]);
    }
}
