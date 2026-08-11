<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReglementRequest;
use App\Models\Paiement;
use App\Models\Vente;
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

        return redirect()->route('ventes.show', $vente)->with('status', __('Règlement enregistré.'));
    }
}
