<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Compte superadmin de la plateforme : gère les tenants et génère les
 * codes d'activation d'abonnement. Authentifié via le guard "admin",
 * entièrement séparé des comptes tenant (`users`).
 */
class Admin extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function codesActivationGeneres(): HasMany
    {
        return $this->hasMany(CodeActivation::class, 'genere_par_admin_id');
    }
}
