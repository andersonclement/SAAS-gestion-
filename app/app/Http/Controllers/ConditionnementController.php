<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConditionnementRequest;
use App\Models\Conditionnement;
use App\Models\Produit;
use App\Support\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ConditionnementController extends Controller
{
    public function store(StoreConditionnementRequest $request, Produit $produit): RedirectResponse
    {
        $donnees = $request->validated();

        DB::transaction(function () use ($produit, $donnees) {
            if ($donnees['par_defaut'] ?? false) {
                $produit->conditionnements()->update(['par_defaut' => false]);
            }

            Conditionnement::create([
                'produit_id' => $produit->id,
                'libelle' => $donnees['libelle'],
                'facteur' => $donnees['facteur'],
                'prix_vente' => $donnees['prix_vente'],
                'par_defaut' => (bool) ($donnees['par_defaut'] ?? false),
            ]);
        });

        Journal::enregistrer('produit.conditionnement_ajoute', __(
            ':produit : conditionnement « :libelle » (:facteur :unite) à :prix FCFA.',
            [
                'produit' => $produit->nom,
                'libelle' => $donnees['libelle'],
                'facteur' => $donnees['facteur'],
                'unite' => $produit->unite_mesure->value,
                'prix' => number_format($donnees['prix_vente'], 0, ',', ' '),
            ]
        ));

        return back()->with('status', __('Conditionnement ajouté.'));
    }

    /**
     * Un conditionnement retiré du catalogue reste attaché aux ventes déjà
     * enregistrées : on le désactive au lieu de le supprimer, pour ne pas
     * amputer l'historique et les factures déjà remises aux clients.
     */
    public function destroy(Conditionnement $conditionnement): RedirectResponse
    {
        $this->authorize('delete', $conditionnement);

        $conditionnement->update(['actif' => false, 'par_defaut' => false]);

        Journal::enregistrer('produit.conditionnement_retire', __(
            ':produit : conditionnement « :libelle » retiré de la vente.',
            ['produit' => $conditionnement->produit->nom, 'libelle' => $conditionnement->libelle]
        ));

        return back()->with('status', __('Conditionnement retiré de la vente.'));
    }
}
