<?php

namespace App\Policies;

use App\Models\LigneVente;
use App\Models\Retour;
use App\Models\User;

class RetourPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Retour $retour): bool
    {
        if ($user->tenant_id !== $retour->tenant_id) {
            return false;
        }

        return $user->boutique_id === null || $user->boutique_id === $retour->boutique_id;
    }

    /**
     * Le patron, les gérants et les vendeurs enregistrent des retours ;
     * le comptable est en consultation seule (comme pour les ventes).
     *
     * Quand une ligne de vente est fournie, on vérifie en plus qu'elle
     * appartient bien au tenant (et à la boutique) de l'utilisateur :
     * LigneVente n'a pas de colonne tenant_id, elle échappe donc au scope
     * global multi-tenant et le liage de route l'exposerait sinon à
     * n'importe quel identifiant deviné.
     */
    public function create(User $user, ?LigneVente $ligneVente = null): bool
    {
        if ($user->isComptable()) {
            return false;
        }

        if ($ligneVente === null) {
            return true;
        }

        $vente = $ligneVente->vente()->withoutGlobalScopes()->first();

        if ($vente === null || $vente->tenant_id !== $user->tenant_id) {
            return false;
        }

        return $user->boutique_id === null || $user->boutique_id === $vente->boutique_id;
    }
}
