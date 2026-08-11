<?php

namespace App\Policies;

use App\Models\User;

class DepensePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Seuls le patron et les gérants enregistrent des dépenses, un gérant
     * uniquement pour sa propre boutique (vérifié dans la requête).
     */
    public function create(User $user): bool
    {
        return $user->isPatron() || $user->isGerant();
    }
}
