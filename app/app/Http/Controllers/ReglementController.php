<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReglementRequest;
use App\Models\Paiement;
use App\Models\Vente;
use App\Support\Journal;
use Illuminate\Http\RedirectResponse;

class ReglementController extends Controller
{
    /**
     * Enregistre un règlement partiel ou total sur une vente à crédit
     * (§4.9 : historique des règlements partiels).
     */
    public function store(StoreReglementRequest $request, Vente $vente): RedirectResponse
    {
        Paiement::create([
            'vente_id' => $vente->id,
            'mode' => $request->validated('mode'),
            'montant' => $request->validated('montant'),
        ]);

        Journal::enregistrer('vente.reglement', __('Règlement de :montant FCFA sur la vente :numero.', [
            'montant' => number_format($request->validated('montant'), 0, ',', ' '),
            'numero' => $vente->numero,
        ]), $vente->boutique_id);

        return redirect()->route('ventes.show', $vente)->with('status', __('Règlement enregistré.'));
    }
}
