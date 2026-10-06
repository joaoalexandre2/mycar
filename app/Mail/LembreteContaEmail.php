<?php

namespace App\Mail;

use App\Models\Conta;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LembreteContaEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param array<int, array<string, mixed>> $itens saída de LembretesContaService::pendentes()
     */
    public function __construct(
        public Conta $conta,
        public array $itens,
    ) {
    }

    public function build(): self
    {
        $total = count($this->itens);

        $assunto = $total === 1
            ? 'Lembrete: ' . $this->rotulo($this->itens[0]) . ' - ' . $this->itens[0]['veiculo']
            : "Lembrete: {$total} vencimentos nos seus veículos";

        return $this->subject($assunto)
            ->markdown('emails.lembrete-conta', [
                // Chaves distintas das propriedades públicas (conta, itens).
                'nomeConta' => $this->conta->nome,
                'ehFrota' => $this->conta->tipo === Conta::TIPO_FROTA,
                'linhas' => $this->itens,
                'urlSistema' => rtrim((string) config('app.frontend_url'), '/'),
            ]);
    }

    private function rotulo(array $item): string
    {
        if (isset($item['rotulo'])) {
            return $item['rotulo'];
        }

        return match ($item['tipo']) {
            'ipva' => 'IPVA',
            'licenciamento' => 'licenciamento',
            'seguro' => 'renovação do seguro',
            default => 'revisão',
        };
    }
}
