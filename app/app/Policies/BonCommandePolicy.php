<?php

namespace App\Policies;

use App\Models\BonCommande;
use App\Models\User;

class BonCommandePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, BonCommande $bonCommande): bool
    {
        if ($user->tenant_id !== $bonCommande->tenant_id) {
            return false;
        }

        return $user->boutique_id === null || $user->boutique_id === $bonCommande->boutique_id;
    }

    /**
     * Seuls le patron et les gérants passent commande, un gérant uniquement
     * pour sa propre boutique.
     */
    public function create(User $user): bool
    {
        return $user->isPatron() || $user->isGerant();
    }

    public function receptionner(User $user, BonCommande $bonCommande): bool
    {
        return $this->view($user, $bonCommande)
            && ! $bonCommande->estRecu()
            && ($user->isPatron() || $user->isGerant());
    }
}
