<?php

namespace App\Policies;

use App\Models\EcartSynchronisation;
use App\Models\User;

/**
 * Un écart de synchronisation constate un manque de marchandise : le traiter
 * revient à reconnaître une perte. Seuls le patron et le gérant de la boutique
 * concernée peuvent le faire — pas le vendeur, qui est précisément la personne
 * dont l'encaissement hors-ligne a créé l'écart.
 */
class EcartSynchronisationPolicy
{
    public function resoudre(User $user, EcartSynchronisation $ecart): bool
    {
        if ($user->tenant_id !== $ecart->tenant_id || $ecart->estResolu()) {
            return false;
        }

        if ($user->isPatron()) {
            return true;
        }

        return $user->isGerant() && $user->boutique_id === $ecart->boutique_id;
    }
}
