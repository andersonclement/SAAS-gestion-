<?php

namespace App\Policies;

use App\Models\Inventaire;
use App\Models\User;

class InventairePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Inventaire $inventaire): bool
    {
        if ($user->tenant_id !== $inventaire->tenant_id) {
            return false;
        }

        return $user->boutique_id === null || $user->boutique_id === $inventaire->boutique_id;
    }

    /**
     * Seuls le patron et les gérants réalisent un inventaire, un gérant
     * uniquement pour sa propre boutique (vérifié dans la requête).
     */
    public function create(User $user): bool
    {
        return $user->isPatron() || $user->isGerant();
    }
}
