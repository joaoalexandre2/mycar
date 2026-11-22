<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Convite para o motorista entrar no MyCar. Usa o mesmo link/token da
 * redefinição de senha (a tela de definir senha é a mesma), mas com texto
 * próprio: quem recebe nunca pediu nada, então "se não pediu, ignore" faria
 * a pessoa descartar o convite.
 */
class ConviteMotoristaEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $convidado,
        public string $urlDefinirSenha,
        public string $quemConvidou,
        public int $minutosParaExpirar,
    ) {}

    public function build(): self
    {
        return $this->subject('Você foi convidado para o MyCar Frota')
            ->markdown('emails.convite-motorista', [
                'nome' => $this->convidado->name,
                'quemConvidou' => $this->quemConvidou,
                'url' => $this->urlDefinirSenha,
                'minutos' => $this->minutosParaExpirar,
            ]);
    }
}
