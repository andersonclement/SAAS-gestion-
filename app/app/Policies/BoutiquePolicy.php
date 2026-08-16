<?php

namespace App\Policies;

use App\Models\Boutique;
use App\Models\User;

class BoutiquePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Boutique $boutique): bool
    {
        if ($user->tenant_id !== $boutique->tenant_id) {
            return false;
        }

        return $user->isPatron() || $user->isComptable() || $user->boutique_id === $boutique->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isPatron();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Boutique $boutique): bool
    {
        return $user->isPatron() && $user->tenant_id === $boutique->tenant_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Boutique $boutique): bool
    {
        return $user->isPatron() && $user->tenant_id === $boutique->tenant_id;
    }
}
