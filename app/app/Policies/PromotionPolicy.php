<?php

namespace App\Policies;

use App\Models\User;

class PromotionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Les promotions s'appliquent à l'ensemble du tenant : seul le patron
     * les crée, pour éviter des remises contradictoires entre boutiques.
     */
    public function create(User $user): bool
    {
        return $user->isPatron();
    }
}
