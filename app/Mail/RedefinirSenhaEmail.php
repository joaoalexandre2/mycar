<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RedefinirSenhaEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $urlRedefinir,
    ) {
    }

    public function build(): self
    {
        return $this->subject('Redefinir senha do MyCar')
            ->markdown('emails.redefinir-senha', [
                'nome' => $this->user->name,
                'url' => $this->urlRedefinir,
            ]);
    }
}
