<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreApprovisionnementRequest;
use App\Models\Boutique;
use App\Models\Lot;
use App\Models\Produit;
use App\Models\StockBoutique;
use App\Support\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Entrée directe de stock, sans passer par un bon de commande : c'est le geste
 * du patron qui garnit une boutique avant de la confier, et celui du gérant
 * muni d'un code d'accès qui reçoit une livraison.
 *
 * L'écran ne propose que l'ajout. Aucune correction n'est possible ensuite :
 * un écart constaté se règle par un inventaire, réservé au patron.
 */
class ApprovisionnementController extends Controller
{
    public function create(): View
    {
        $this->authorize('create', StockBoutique::class);

        $user = Auth::user();

        return view('stock.approvisionnements.create', [
            'boutiques' => $user->boutique_id
                ? Boutique::whereKey($user->boutique_id)->get()
                : Boutique::orderBy('nom')->get(),
            'produits' => Produit::where('actif', true)->orderBy('nom')->get(),
        ]);
    }

    public function store(StoreApprovisionnementRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated) {
            $produit = Produit::findOrFail($validated['produit_id']);

            // Un même numéro de lot réapprovisionné vient s'ajouter au lot
            // existant plutôt que d'en créer un doublon.
            $lot = Lot::firstOrCreate(
                ['produit_id' => $produit->id, 'numero_lot' => $validated['numero_lot']],
                [
                    'date_fabrication' => $validated['date_fabrication'] ?? null,
                    'date_peremption' => $validated['date_peremption'] ?? null,
                ]
            );

            $stock = StockBoutique::firstOrCreate(
                ['boutique_id' => $validated['boutique_id'], 'lot_id' => $lot->id],
                ['produit_id' => $produit->id, 'quantite' => 0]
            );

            $stock->increment('quantite', $validated['quantite']);

            Journal::enregistrer('stock.approvisionne', __(
                ':quantite :produit entrés en stock (lot :lot, péremption :peremption).',
                [
                    'quantite' => $validated['quantite'],
                    'produit' => $produit->nom,
                    'lot' => $lot->numero_lot,
                    'peremption' => $lot->date_peremption?->format('d/m/Y') ?? '—',
                ]
            ), (int) $validated['boutique_id']);
        });

        return redirect()->route('stock.index')->with('status', __('Stock enregistré.'));
    }
}
