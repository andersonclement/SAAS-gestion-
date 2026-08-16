<?php

namespace App\Models;

use App\Enums\PorteeCodeAcces;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Code d'accès délivré par le patron à l'un de ses gérants pour lui confier,
 * pour un temps limité et sur une seule boutique, une opération qu'il ne peut
 * pas faire seul : créer des produits ou approvisionner le stock.
 *
 * Le statut n'est pas stocké mais déduit des dates : un code stocké « actif »
 * qu'une tâche aurait oublié d'expirer resterait valable à tort.
 */
class CodeAcces extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'codes_acces';

    protected $fillable = [
        'tenant_id',
        'boutique_id',
        'code',
        'portee',
        'genere_par_user_id',
        'destinataire_user_id',
        'expire_le',
        'active_le',
        'revoque_le',
        'motif',
    ];

    protected function casts(): array
    {
        return [
            'portee' => PorteeCodeAcces::class,
            'expire_le' => 'datetime',
            'active_le' => 'datetime',
            'revoque_le' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CodeAcces $code): void {
            $code->code ??= static::genererCode();
        });
    }

    /**
     * Alphabet sans caractères ambigus : le code est recopié à la main par le
     * gérant depuis un SMS ou un message vocal.
     */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public static function genererCode(): string
    {
        do {
            $segments = collect(range(1, 8))
                ->map(fn () => self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)])
                ->join('');

            $code = 'ACC-'.substr($segments, 0, 4).'-'.substr($segments, 4, 4);
        } while (static::withoutGlobalScopes()->where('code', $code)->exists());

        return $code;
    }

    public function boutique(): BelongsTo
    {
        return $this->belongsTo(Boutique::class);
    }

    public function generePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'genere_par_user_id');
    }

    public function destinataire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'destinataire_user_id');
    }

    /**
     * Opérations réalisées sous couvert de ce code.
     */
    public function operations(): HasMany
    {
        return $this->hasMany(JournalActivite::class)->latest('created_at');
    }

    public function estRevoque(): bool
    {
        return $this->revoque_le !== null;
    }

    public function estExpire(): bool
    {
        return ! $this->estRevoque() && $this->expire_le->isPast();
    }

    public function estValide(): bool
    {
        return ! $this->estRevoque() && ! $this->estExpire();
    }

    /**
     * Un code n'ouvre l'accès qu'au gérant nommément désigné.
     */
    public function utilisablePar(User $user): bool
    {
        return $this->estValide()
            && $this->tenant_id === $user->tenant_id
            && $this->destinataire_user_id === $user->id;
    }

    public function statut(): string
    {
        return match (true) {
            $this->estRevoque() => __('Révoqué'),
            $this->estExpire() => __('Expiré'),
            $this->active_le !== null => __('En cours'),
            default => __('Non utilisé'),
        };
    }
}
