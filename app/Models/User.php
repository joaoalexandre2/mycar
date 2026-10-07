<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Mail\RedefinirSenhaEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'oficina_id',
        'perfil',
        'conta_id',
        'tema',
        'cor',
        'foto',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'api_token',
        'verification_token',
    ];

    public function tokensAcesso(): HasMany
    {
        return $this->hasMany(TokenAcesso::class);
    }

    /**
     * Encerra as sessões da pessoa em todos os aparelhos, menos (opcionalmente)
     * a que está em uso. Usado ao trocar ou redefinir a senha.
     */
    public function revogarSessoes(?string $manterHash = null): void
    {
        $this->tokensAcesso()
            ->when($manterHash, fn ($q) => $q->where('token_hash', '!=', $manterHash))
            ->delete();

        if ($this->api_token !== null && $this->api_token !== $manterHash) {
            $this->forceFill(['api_token' => null])->save();
        }
    }

    public function oficina(): BelongsTo
    {
        return $this->belongsTo(Oficina::class);
    }

    public function conta(): BelongsTo
    {
        return $this->belongsTo(Conta::class);
    }

    /**
     * Dados do usuário que o frontend guarda e usa. "admin" e "perfil" só
     * servem para escolher menus e telas: quem barra o acesso é o backend.
     *
     * @return array{id: int, name: string, email: string, perfil: string, oficina: ?string, conta: ?string, admin: bool}
     */
    public function dadosPublicos(): array
    {
        $perfil = $this->perfil ?? 'oficina';

        // Quem migrou de perfil mantém a oficina antiga arquivada (e quem
        // voltou mantém a conta): só aparece o que pertence ao perfil atual.
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'perfil' => $perfil,
            'oficina' => $perfil === 'oficina' ? $this->oficina?->nome : null,
            'conta' => $perfil === 'oficina' ? null : $this->conta?->nome,
            'admin' => (bool) $this->is_super_admin,
            // Aparência escolhida em Configurações; null = nunca escolheu.
            'tema' => $this->tema,
            'cor' => $this->cor,
            // Foto de perfil (imagem pequena em data URI); null = usa as iniciais.
            'foto' => $this->foto,
        ];
    }

    /**
     * Sobrescreve o e-mail padrão do Laravel (Password::sendResetLink) para
     * usar nosso template e apontar para a tela de redefinição no frontend,
     * em vez de uma rota do backend que não existe (API pura, sem views).
     */
    public function sendPasswordResetNotification($token): void
    {
        $frontend = rtrim(config('app.frontend_url'), '/');
        $url = "$frontend/redefinir-senha?token=$token&email=" . urlencode($this->email);

        Mail::to($this->email)->send(new RedefinirSenhaEmail($this, $url));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'ultimo_acesso_em' => 'datetime',
            'is_super_admin' => 'boolean',
            'password' => 'hashed',
        ];
    }
}
