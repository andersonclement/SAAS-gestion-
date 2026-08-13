<?php

namespace App\Policies;

use App\Enums\PorteeCodeAcces;
use App\Models\Produit;
use App\Models\User;
use App\Support\AccesPrivilegie;

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
     * Le patron alimente le catalogue de plein droit. Un gérant ne le fait que
     * muni d'un code d'accès en cours, remis par son patron : c'est ce code qui
     * rend l'opération traçable et révocable.
     */
    public function create(User $user): bool
    {
        if ($user->isPatron()) {
            return true;
        }

        return $user->isGerant() && AccesPrivilegie::couvre(PorteeCodeAcces::Catalogue);
    }

    public function update(User $user, Produit $produit): bool
    {
        return $user->tenant_id === $produit->tenant_id && $this->create($user);
    }

    public function delete(User $user, Produit $produit): bool
    {
        return $user->tenant_id === $produit->tenant_id && $user->isPatron();
    }
}
