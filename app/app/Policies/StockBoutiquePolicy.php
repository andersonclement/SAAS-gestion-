<?php

namespace App\Policies;

use App\Enums\PorteeCodeAcces;
use App\Models\User;
use App\Support\AccesPrivilegie;

/**
 * Entrée directe de stock en boutique (approvisionnement).
 *
 * L'opération est volontairement en ajout seul : ni modification ni
 * suppression après coup. Corriger une quantité passe par un inventaire, que
 * seul le patron peut réaliser (voir InventairePolicy) — un gérant ne peut
 * donc jamais revenir sur un stock qu'il a saisi.
 */
class StockBoutiquePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Le patron approvisionne de plein droit ; le gérant seulement muni d'un
     * code d'accès en cours couvrant l'approvisionnement.
     */
    public function create(User $user): bool
    {
        if ($user->isPatron()) {
            return true;
        }

        return $user->isGerant() && AccesPrivilegie::couvre(PorteeCodeAcces::Approvisionnement);
    }
}
