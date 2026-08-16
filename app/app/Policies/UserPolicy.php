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
     * Modification d'une fiche de compte. Réservée au patron, y compris pour
     * sa propre fiche : un gérant qui pourrait éditer son compte pourrait
     * aussi s'attribuer un autre rôle ou une autre boutique.
     */
    public function update(User $user, User $model): bool
    {
        return $user->isPatron() && $user->tenant_id === $model->tenant_id;
    }

    /**
     * Activation/désactivation d'un compte. Le patron ne peut pas se
     * désactiver lui-même : le tenant se retrouverait sans administrateur.
     */
    public function basculerActivation(User $user, User $model): bool
    {
        return $user->isPatron() && $user->tenant_id === $model->tenant_id && $user->id !== $model->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->isPatron() && $user->tenant_id === $model->tenant_id && $user->id !== $model->id;
    }
}
