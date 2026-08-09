<?php

namespace App\Policies;

use App\Models\Produit;
use App\Models\User;

class ProduitPolicy
{
    /**
     * Le catalogue est commun à toutes les boutiques d'un même tenant :
     * tout utilisateur du tenant peut le consulter.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Produit $produit): bool
    {
        return $user->tenant_id === $produit->tenant_id;
    }

    /**
     * Seuls le patron et les gérants alimentent le catalogue.
     */
    public function create(User $user): bool
    {
        return $user->isPatron() || $user->isGerant();
    }

    public function update(User $user, Produit $produit): bool
    {
        return $user->tenant_id === $produit->tenant_id && ($user->isPatron() || $user->isGerant());
    }

    public function delete(User $user, Produit $produit): bool
    {
        return $user->tenant_id === $produit->tenant_id && $user->isPatron();
    }
}
