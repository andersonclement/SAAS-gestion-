<?php

namespace App\Policies;

use App\Models\User;

class VersementCaissePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Seul le patron collecte physiquement l'argent d'une boutique et
     * peut donc enregistrer un versement de caisse.
     */
    public function create(User $user): bool
    {
        return $user->isPatron();
    }
}
