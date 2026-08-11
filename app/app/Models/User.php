<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

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
        'tenant_id',
        'boutique_id',
        'name',
        'email',
        'telephone',
        'password',
        'role',
        'actif',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'actif' => 'boolean',
        ];
    }

    // Volontairement sans trait BelongsToTenant : la portée du User est
    // gérée explicitement dans les contrôleurs, car la recherche par email
    // lors de l'authentification doit pouvoir s'exécuter avant tout contexte
    // de tenant connu.

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function boutique(): BelongsTo
    {
        return $this->belongsTo(Boutique::class);
    }

    public function isPatron(): bool
    {
        return $this->role === UserRole::Patron;
    }

    public function isGerant(): bool
    {
        return $this->role === UserRole::Gerant;
    }

    public function isVendeur(): bool
    {
        return $this->role === UserRole::Vendeur;
    }

    public function isComptable(): bool
    {
        return $this->role === UserRole::Comptable;
    }

    /**
     * Boutique à laquelle limiter les vues consultées par cet utilisateur :
     * celle du gérant/vendeur, ou — pour un patron — la boutique qu'il a
     * choisi de consulter "comme le ferait son gérant" (voir
     * BoutiqueContexteController). Retourne null quand rien ne doit
     * restreindre la vue (patron/comptable sans boutique choisie).
     */
    public function effectiveBoutiqueId(): ?int
    {
        if ($this->boutique_id) {
            return $this->boutique_id;
        }

        if ($this->isPatron()) {
            return session('boutique_contexte_id');
        }

        return null;
    }
}
