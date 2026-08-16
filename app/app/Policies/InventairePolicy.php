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
     * L'inventaire ajuste directement les quantités en stock sans passer
     * par une facture ou une transaction (vente, achat, transfert) : pour
     * éviter toute modification de stock non tracée par un gérant, seul
     * le patron peut réaliser un inventaire.
     */
    public function create(User $user): bool
    {
        return $user->isPatron();
    }
}
