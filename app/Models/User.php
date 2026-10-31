<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Mail\RedefinirSenhaEmail;
use App\Support\PermissoesFrota;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

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
        'papel_frota',
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
        'verification_token',
    ];

    public function contasSociais(): HasMany
    {
        return $this->hasMany(ContaSocial::class);
    }

    /**
     * Abre uma sessão neste aparelho e devolve o token em texto (só o hash é
     * guardado). Um token por aparelho; passando do limite, sai o menos usado.
     */
    public function abrirSessao(?string $dispositivo = null): string
    {
        $token = Str::random(60);

        $this->tokensAcesso()
            ->where('ultimo_uso_em', '<', now()->subDays(TokenAcesso::DIAS_SEM_USO))
            ->delete();

        $excedente = $this->tokensAcesso()->count() - (TokenAcesso::LIMITE_POR_USUARIO - 1);

        if ($excedente > 0) {
            $this->tokensAcesso()->orderBy('ultimo_uso_em')->orderBy('id')->limit($excedente)->get()->each->delete();
        }

        $this->tokensAcesso()->create([
            'token_hash' => hash('sha256', $token),
            'dispositivo' => Str::limit((string) $dispositivo, 150, ''),
            'ultimo_uso_em' => now(),
        ]);

        $this->forceFill(['ultimo_acesso_em' => now()])->save();

        return $token;
    }

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
    }

    /**
     * MyCar principal da pessoa, como está gravado no usuário. Preenchido por
     * aplicarAcesso() antes de trocar os campos em memória; nulo quando a
     * sessão está no principal.
     *
     * @var array{perfil: ?string, oficina_id: ?int, conta_id: ?int, papel_frota: ?string}|null
     */
    public ?array $principalGravado = null;

    /** Outros MyCar da mesma pessoa (o principal fica nas colunas do usuário). */
    public function acessos(): HasMany
    {
        return $this->hasMany(Acesso::class);
    }

    /** @return array{perfil: ?string, oficina_id: ?int, conta_id: ?int, papel_frota: ?string} */
    public function principal(): array
    {
        return $this->principalGravado ?? [
            'perfil' => $this->perfil,
            'oficina_id' => $this->oficina_id,
            'conta_id' => $this->conta_id,
            'papel_frota' => $this->papel_frota,
        ];
    }

    /**
     * Passa esta requisição a valer como o MyCar do acesso informado (ou, com
     * null, o principal). Só muda o objeto em memória: o que está gravado no
     * usuário não é tocado (os campos são sincronizados para não ficarem
     * "sujos" e acabarem salvos por um update qualquer).
     */
    public function aplicarAcesso(?Acesso $acesso): void
    {
        $principal = $this->principal();
        $this->principalGravado = $principal;

        $campos = $acesso === null
            ? $principal
            : [
                'perfil' => $acesso->perfil,
                'oficina_id' => $acesso->oficina_id,
                'conta_id' => $acesso->conta_id,
                'papel_frota' => $acesso->papel_frota,
            ];

        $this->forceFill($campos)->syncOriginalAttributes(array_keys($campos));
        $this->unsetRelation('oficina');
        $this->unsetRelation('conta');
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
     * Papel do usuário dentro da própria conta. Na frota vale papel_frota
     * (quem é anterior aos papéis conta como administrador); pessoa é dona da
     * conta; oficina não tem papéis (null).
     */
    public function papelNaConta(): ?string
    {
        return match ($this->perfil ?? 'oficina') {
            'frota' => $this->papel_frota ?? 'administrador',
            'pessoa' => 'administrador',
            default => null,
        };
    }

    /** Permissões da frota: as do papel mais as de todos os grupos de usuários a que a pessoa pertence. */
    public function temPermissao(string $permissao): bool
    {
        $papel = $this->papelNaConta();

        if (in_array($permissao, PermissoesFrota::doPapel($papel), true)) {
            return true;
        }

        if (($this->perfil ?? 'oficina') !== 'frota' || !$this->conta_id) {
            return false;
        }

        return GrupoUsuario::withoutGlobalScope('conta')
            ->where('conta_id', $this->conta_id)
            ->whereHas('membros', fn ($q) => $q->where('users.id', $this->id))
            ->pluck('permissoes')
            ->flatten()
            ->contains($permissao);
    }

    /**
     * Menus da frota que algum grupo desta pessoa libera (união dos grupos). Fora
     * de grupo, a pessoa não vê esses menus; o administrador vê todos.
     *
     * @return list<string>
     */
    public function menusLiberados(): array
    {
        if (($this->perfil ?? 'oficina') !== 'frota' || !$this->conta_id) {
            return [];
        }

        return GrupoUsuario::withoutGlobalScope('conta')
            ->where('conta_id', $this->conta_id)
            ->whereHas('membros', fn ($q) => $q->where('users.id', $this->id))
            ->pluck('menus_liberados')
            ->flatten()
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Se o menu da frota aparece para esta pessoa. Na frota, os menus de gestão só
     * aparecem para o administrador ou para quem está em grupo que os libera.
     * Fora da frota, não se aplica.
     */
    public function podeVerMenu(string $menu): bool
    {
        if (($this->perfil ?? 'oficina') !== 'frota') {
            return true;
        }

        if ($this->papelNaConta() === 'administrador') {
            return true;
        }

        return in_array($menu, $this->menusLiberados(), true);
    }

    /** Cadastro de motorista ligado a este login (só para o papel motorista). */
    public function motoristaVinculado(): ?Motorista
    {
        return Motorista::where('user_id', $this->id)->first();
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
            // Papel dentro da frota (administrador, gestor, motorista); null fora do perfil frota.
            'papel' => $perfil === 'frota' ? $this->papelNaConta() : null,
            // O que a pessoa pode fazer na frota (papel + grupos); só mostra/esconde telas, o backend é quem barra.
            'permissoes' => $perfil === 'frota'
                ? array_values(array_filter(PermissoesFrota::chaves(), fn ($p) => $this->temPermissao($p)))
                : [],
            // Menus da frota que esta pessoa vê; a tela esconde os outros, e a API barra os que têm middleware `menu`.
            // null fora da frota: os menus da frota não se aplicam a essa pessoa.
            'menus' => $perfil === 'frota'
                ? array_values(array_filter(PermissoesFrota::chavesDeMenu(), fn ($m) => $this->podeVerMenu($m)))
                : null,
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
