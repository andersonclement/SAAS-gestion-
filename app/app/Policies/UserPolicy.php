<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isPatron();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        return $user->tenant_id === $model->tenant_id
            && ($user->isPatron() || $user->id === $model->id);
    }

    /**
     * Determine whether the user can create models.
     *
     * Seul le patron crée des sous-comptes (gérant, vendeur, comptable) —
     * §4.1 du cahier des charges.
     */
    public function create(User $user): bool
    {
        return $user->isPatron();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        return $user->tenant_id === $model->tenant_id
            && ($user->isPatron() || $user->id === $model->id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->isPatron() && $user->tenant_id === $model->tenant_id && $user->id !== $model->id;
    }
}
