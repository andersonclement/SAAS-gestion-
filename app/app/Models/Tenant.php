<?php

namespace App\Models;

use App\Enums\Plan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom',
        'email_contact',
        'telephone',
        'plan',
        'abonnement_expire_le',
        'notes_internes',
        'actif',
    ];

    protected function casts(): array
    {
        return [
            'plan' => Plan::class,
            'abonnement_expire_le' => 'date',
            'actif' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Notifications internes de tous les utilisateurs du client. Utilisée par
     * l'espace superadmin pour repérer les comptes qui décrochent.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(NotificationInterne::class);
    }

    public function boutiques(): HasMany
    {
        return $this->hasMany(Boutique::class);
    }

    public function codesActivation(): HasMany
    {
        return $this->hasMany(CodeActivation::class);
    }

    public function abonnementActif(): bool
    {
        return $this->abonnement_expire_le !== null && ! $this->abonnement_expire_le->isPast();
    }

    /**
     * Jours restants avant expiration (négatif si déjà expiré), ou null si
     * le tenant n'a jamais activé d'abonnement.
     */
    public function joursAvantExpiration(): ?int
    {
        if ($this->abonnement_expire_le === null) {
            return null;
        }

        return (int) Carbon::today()->diffInDays($this->abonnement_expire_le, false);
    }

    public function boutiquesMax(): ?int
    {
        return $this->plan?->boutiquesMax();
    }

    public function peutAjouterBoutique(): bool
    {
        $max = $this->boutiquesMax();

        return $max === null || $this->boutiques()->count() < $max;
    }
}
