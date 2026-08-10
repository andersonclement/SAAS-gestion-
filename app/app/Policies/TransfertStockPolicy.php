<?php

namespace App\Policies;

use App\Models\User;

class TransfertStockPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Seuls le patron et les gérants transfèrent du stock, un gérant
     * uniquement depuis sa propre boutique (vérifié dans la requête).
     */
    public function create(User $user): bool
    {
        return $user->isPatron() || $user->isGerant();
    }
}
