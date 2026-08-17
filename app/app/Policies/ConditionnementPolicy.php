<?php

namespace App\Policies;

use App\Models\Conditionnement;
use App\Models\Produit;
use App\Models\User;

/**
 * Un conditionnement fixe un prix de vente : il relève donc du catalogue, et
 * suit exactement les mêmes droits que le produit qui le porte — patron de
 * plein droit, gérant muni d'un code d'accès catalogue en cours.
 */
class ConditionnementPolicy
{
    public function __construct(private readonly ProduitPolicy $produits = new ProduitPolicy) {}

    public function create(User $user, Produit $produit): bool
    {
        return $user->tenant_id === $produit->tenant_id && $this->produits->create($user);
    }

    public function update(User $user, Conditionnement $conditionnement): bool
    {
        return $user->tenant_id === $conditionnement->tenant_id && $this->produits->create($user);
    }

    public function delete(User $user, Conditionnement $conditionnement): bool
    {
        return $this->update($user, $conditionnement);
    }
}
