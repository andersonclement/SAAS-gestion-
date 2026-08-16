<?php

namespace App\Http\Controllers;

use App\Models\EcartSynchronisation;
use App\Support\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class EcartSynchronisationController extends Controller
{
    /**
     * Clôture un écart constaté à la synchronisation d'une vente hors-ligne.
     *
     * Marquer l'écart comme traité ne corrige pas le stock : la quantité
     * manquante n'a jamais existé en base. C'est un accusé de prise en charge,
     * qui laisse au gérant le soin de passer l'inventaire ou la perte
     * correspondante avec les outils existants.
     */
    public function resoudre(EcartSynchronisation $ecart): RedirectResponse
    {
        $this->authorize('resoudre', $ecart);

        $ecart->update([
            'resolu_le' => now(),
            'resolu_par' => Auth::id(),
        ]);

        Journal::enregistrer(
            'stock.ecart_resolu',
            __('Écart de synchronisation traité : :quantite :produit manquant(s) sur la vente :numero.', [
                'quantite' => $ecart->quantite_manquante,
                'produit' => $ecart->produit->nom,
                'numero' => $ecart->vente->numero,
            ]),
            $ecart->boutique_id
        );

        return back()->with('status', __('Écart marqué comme traité.'));
    }
}
