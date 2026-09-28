<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ConfirmeSeuEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $urlConfirmacao,
    ) {
    }

    public function build(): self
    {
        return $this->subject('Confirme seu e-mail no MyCar')
            ->markdown('emails.confirmar-email', [
                'nome' => $this->user->name,
                'url' => $this->urlConfirmacao,
            ]);
    }
}
