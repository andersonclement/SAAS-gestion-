<?php

namespace App\Policies;

use App\Models\Retour;
use App\Models\User;

class RetourPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Retour $retour): bool
    {
        if ($user->tenant_id !== $retour->tenant_id) {
            return false;
        }

        return $user->boutique_id === null || $user->boutique_id === $retour->boutique_id;
    }

    /**
     * Le patron, les gérants et les vendeurs enregistrent des retours ;
     * le comptable est en consultation seule (comme pour les ventes).
     */
    public function create(User $user): bool
    {
        return ! $user->isComptable();
    }
}
