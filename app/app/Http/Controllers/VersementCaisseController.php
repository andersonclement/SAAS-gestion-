<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVersementCaisseRequest;
use App\Models\Boutique;
use App\Models\User;
use App\Models\VersementCaisse;
use App\Support\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class VersementCaisseController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', VersementCaisse::class);

        $versements = VersementCaisse::with(['boutique', 'remisPar', 'creePar'])->latest('date')->get();

        return view('versements.index', compact('versements'));
    }

    public function create(): View
    {
        $this->authorize('create', VersementCaisse::class);

        $boutiques = Boutique::with('utilisateurs')->orderBy('nom')->get();

        return view('versements.create', compact('boutiques'));
    }

    /**
     * Enregistre l'argent physiquement remis par un gérant au patron
     * (§4.10), pour que le solde de caisse théorique de la boutique
     * reste cohérent avec l'argent réellement sur place.
     */
    public function store(StoreVersementCaisseRequest $request): RedirectResponse
    {
        $remisPar = User::findOrFail($request->validated('remis_par_id'));

        $versement = VersementCaisse::create($request->safe()->except('remis_par_id') + [
            'remis_par_id' => $remisPar->id,
            'remis_par_nom' => $remisPar->name,
        ]);

        Journal::enregistrer('caisse.versement', __('Versement de :montant FCFA reçu de :nom.', [
            'montant' => number_format($versement->montant, 0, ',', ' '),
            'nom' => $versement->remis_par_nom,
        ]), $versement->boutique_id);

        return redirect()->route('versements.index')->with('status', __('Versement enregistré.'));
    }
}
