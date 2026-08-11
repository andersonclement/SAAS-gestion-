<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRetourRequest;
use App\Models\LigneVente;
use App\Models\Retour;
use App\Models\StockBoutique;
use App\Support\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RetourController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Retour::class);

        $user = Auth::user();

        $retours = Retour::with(['boutique', 'produit', 'lot', 'creePar'])
            ->when($user->boutique_id, fn ($query) => $query->where('boutique_id', $user->boutique_id))
            ->latest()
            ->get();

        return view('retours.index', compact('retours'));
    }

    public function create(LigneVente $ligneVente): View
    {
        $this->authorize('create', Retour::class);

        $ligneVente->load(['produit', 'lot', 'vente.boutique']);

        abort_if($ligneVente->quantiteRetournable() === 0, 403, __('Cette ligne a déjà été intégralement retournée.'));

        return view('retours.create', compact('ligneVente'));
    }

    /**
     * Enregistre un retour de produit et met à jour automatiquement le
     * stock lorsque le produit peut être remis en vente (§4.15).
     */
    public function store(StoreRetourRequest $request, LigneVente $ligneVente): RedirectResponse
    {
        $retour = DB::transaction(function () use ($request, $ligneVente) {
            $ligneVente->load(['vente', 'produit']);
            $quantite = (int) $request->validated('quantite');
            $remisEnStock = $request->boolean('remis_en_stock');

            $retour = Retour::create([
                'boutique_id' => $ligneVente->vente->boutique_id,
                'ligne_vente_id' => $ligneVente->id,
                'produit_id' => $ligneVente->produit_id,
                'lot_id' => $ligneVente->lot_id,
                'quantite' => $quantite,
                'motif' => $request->validated('motif'),
                'remis_en_stock' => $remisEnStock,
                'montant_avoir' => $quantite * $ligneVente->prix_unitaire,
                'mode_remboursement' => $request->validated('mode_remboursement'),
            ]);

            if ($remisEnStock) {
                $stock = StockBoutique::firstOrCreate(
                    [
                        'boutique_id' => $ligneVente->vente->boutique_id,
                        'lot_id' => $ligneVente->lot_id,
                    ],
                    [
                        'tenant_id' => $ligneVente->vente->tenant_id,
                        'produit_id' => $ligneVente->produit_id,
                        'quantite' => 0,
                    ]
                );
                $stock->increment('quantite', $quantite);
            }

            return $retour;
        });

        Journal::enregistrer('vente.retour', __(':quantite x :produit retourné(s) (avoir :montant FCFA).', [
            'quantite' => $retour->quantite,
            'produit' => $ligneVente->produit->nom,
            'montant' => number_format($retour->montant_avoir, 0, ',', ' '),
        ]), $retour->boutique_id);

        return redirect()->route('retours.show', $retour)->with('status', __('Retour enregistré.'));
    }

    public function show(Retour $retour): View
    {
        $this->authorize('view', $retour);

        $retour->load(['boutique', 'produit', 'lot', 'creePar', 'ligneVente.vente']);

        return view('retours.show', compact('retour'));
    }
}
