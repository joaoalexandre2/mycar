<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Convite para um gestor ou administrador entrar na frota. Mesmo link/token da
 * redefinição de senha, mas com texto de convite: quem recebe nunca pediu nada,
 * então "se não pediu, ignore" faria a pessoa descartar o convite.
 */
class ConvitePessoaEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** O que cada papel faz, em linguagem simples, para quem nunca usou o sistema. */
    private const PAPEIS = [
        'administrador' => 'como administrador. Você acompanha os veículos, as reservas e os usos da frota e também decide quem tem acesso a ela',
        'gestor' => 'como gestor. Você acompanha os veículos, as reservas e os usos da frota e cuida dos motoristas',
    ];

    public function __construct(
        public User $convidado,
        public string $urlDefinirSenha,
        public string $quemConvidou,
        public string $papel,
        public int $minutosParaExpirar,
    ) {}

    public function build(): self
    {
        return $this->subject('Você foi convidado para o MyCar Frota')
            ->markdown('emails.convite-pessoa', [
                'nome' => $this->convidado->name,
                'quemConvidou' => $this->quemConvidou,
                'comoPapel' => self::PAPEIS[$this->papel] ?? self::PAPEIS['gestor'],
                'url' => $this->urlDefinirSenha,
                'minutos' => $this->minutosParaExpirar,
            ]);
    }
}
