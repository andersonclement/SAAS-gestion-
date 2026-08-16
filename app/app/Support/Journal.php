<?php

namespace App\Support;

use App\Models\JournalActivite;
use Illuminate\Support\Facades\Auth;

/**
 * Journal d'activité (§4.12 du cahier des charges) : trace les actions
 * sensibles de chaque utilisateur pour prévenir les erreurs et les
 * fraudes (connexions, ventes, mouvements de stock, dépenses...).
 * Le nom de l'utilisateur est capturé au moment de l'action pour rester
 * lisible même si le compte est supprimé par la suite.
 */
class Journal
{
    public static function enregistrer(string $action, string $description, ?int $boutiqueId = null): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        JournalActivite::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'utilisateur_nom' => $user->name,
            'boutique_id' => $boutiqueId,
            // Quand l'action est faite sous couvert d'un code d'accès, elle est
            // rattachée à ce code : le patron retrouve ainsi tout ce qu'a permis
            // chaque délégation qu'il a accordée.
            'code_acces_id' => AccesPrivilegie::actif()?->id,
            'action' => $action,
            'description' => $description,
        ]);
    }
}
