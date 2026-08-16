<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vente;

class VentePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Vente $vente): bool
    {
        if ($user->tenant_id !== $vente->tenant_id) {
            return false;
        }

        return $user->boutique_id === null || $user->boutique_id === $vente->boutique_id;
    }

    /**
     * Le patron, les gérants et les vendeurs enregistrent des ventes ;
     * le comptable est en consultation seule (§3 du cahier des charges).
     */
    public function create(User $user): bool
    {
        return ! $user->isComptable();
    }
}
