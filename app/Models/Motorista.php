<?php

namespace App\Models;

use App\Models\Concerns\PertenceAConta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Motorista da frota. Pode ser só um cadastro (user_id vazio) ou alguém com
 * login no sistema (user_id preenchido, quando o convite existir).
 */
class Motorista extends Model
{
    use PertenceAConta;

    public const SEM_ACESSO = 'sem_acesso';

    public const CONVITE_ENVIADO = 'convite_enviado';

    public const ACESSO_ATIVO = 'ativo';

    protected $table = 'motoristas';

    protected $fillable = ['nome', 'telefone', 'cnh_numero', 'cnh_validade', 'ativo', 'centro_custo_id'];

    protected function casts(): array
    {
        return ['cnh_validade' => 'date:Y-m-d', 'ativo' => 'boolean'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Login ligado a este motorista, só se for da mesma conta (defesa extra:
     * um user_id de outra conta nunca aparece nem conta como acesso).
     */
    public function loginDaConta(): ?User
    {
        $usuario = $this->usuario;

        return $usuario && (int) $usuario->conta_id === (int) $this->conta_id ? $usuario : null;
    }

    /**
     * Estado do acesso ao sistema, derivado do que já é guardado:
     * - sem_acesso: nenhum login ligado;
     * - convite_enviado: login criado, mas a pessoa ainda não entrou (ultimo_acesso_em vazio);
     * - ativo: a pessoa já entrou no sistema pelo menos uma vez.
     */
    public function estadoDeAcesso(): string
    {
        $usuario = $this->loginDaConta();

        if (!$usuario) {
            return self::SEM_ACESSO;
        }

        return $usuario->ultimo_acesso_em ? self::ACESSO_ATIVO : self::CONVITE_ENVIADO;
    }

    /** Dados do motorista para a tela de cadastros, com o estado do acesso e o e-mail do login. */
    public function paraLista(): array
    {
        $usuario = $this->loginDaConta();

        return array_merge($this->attributesToArray(), [
            'acesso' => $this->estadoDeAcesso(),
            'acesso_email' => $usuario?->email,
        ]);
    }

    public function centroCusto(): BelongsTo
    {
        return $this->belongsTo(CentroCusto::class);
    }

    public function usos(): HasMany
    {
        return $this->hasMany(UsoVeiculo::class);
    }

    /**
     * Motivo de não poder dirigir na data (null = liberado). Motorista sem CNH
     * cadastrada também fica bloqueado: não há como provar a validade.
     */
    public function impedimentoParaDirigir(\DateTimeInterface $em): ?string
    {
        if (!$this->ativo) {
            return "O motorista {$this->nome} está desativado.";
        }

        if ($this->cnh_validade === null) {
            return "O motorista {$this->nome} não tem a validade da CNH cadastrada.";
        }

        if ($this->cnh_validade->toDateString() < $em->format('Y-m-d')) {
            return "A CNH de {$this->nome} vence em {$this->cnh_validade->format('d/m/Y')}, antes do uso.";
        }

        return null;
    }

    public function emUso(): bool
    {
        return $this->usos()->exists();
    }
}
