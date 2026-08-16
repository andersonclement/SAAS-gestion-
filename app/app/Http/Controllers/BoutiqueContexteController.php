<?php

namespace App\Http\Controllers;

use App\Models\Boutique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Permet au patron de consulter l'application filtrée sur une seule
 * boutique, exactement comme le ferait le gérant de cette boutique
 * (tableau de bord, stock, ventes, trésorerie, alertes, etc.), sans que
 * cela n'affecte ses propres droits d'action.
 */
class BoutiqueContexteController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->isPatron(), 403);

        $boutiqueId = $request->input('boutique_id');

        if (! $boutiqueId) {
            session()->forget('boutique_contexte_id');

            return back()->with('status', __('Retour à la vue de toutes les boutiques.'));
        }

        // Le scope global "tenant" de Boutique empêche toute fuite entre tenants.
        $boutique = Boutique::findOrFail($boutiqueId);

        session(['boutique_contexte_id' => $boutique->id]);

        return back()->with('status', __('Vous consultez maintenant :nom comme le ferait son gérant.', ['nom' => $boutique->nom]));
    }
}
