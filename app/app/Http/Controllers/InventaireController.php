<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventaireRequest;
use App\Models\Boutique;
use App\Models\Inventaire;
use App\Models\StockBoutique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InventaireController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Inventaire::class);

        $user = Auth::user();

        $inventaires = Inventaire::with(['boutique', 'creePar'])
            ->when($user->boutique_id, fn ($query) => $query->where('boutique_id', $user->boutique_id))
            ->latest()
            ->get();

        return view('stock.inventaires.index', compact('inventaires'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Inventaire::class);

        $user = Auth::user();

        $boutiques = $user->boutique_id
            ? Boutique::whereKey($user->boutique_id)->get()
            : Boutique::orderBy('nom')->get();

        $boutiqueId = (int) $request->query('boutique_id', $boutiques->first()?->id);

        $stocks = StockBoutique::with(['produit', 'lot'])
            ->where('boutique_id', $boutiqueId)
            ->get();

        return view('stock.inventaires.create', compact('boutiques', 'boutiqueId', 'stocks'));
    }

    public function store(StoreInventaireRequest $request): RedirectResponse
    {
        $inventaire = DB::transaction(function () use ($request) {
            $inventaire = Inventaire::create(['boutique_id' => $request->validated('boutique_id')]);

            foreach ($request->validated('lignes') as $stockBoutiqueId => $donnees) {
                $stock = StockBoutique::where('boutique_id', $inventaire->boutique_id)->findOrFail($stockBoutiqueId);

                $inventaire->lignes()->create([
                    'produit_id' => $stock->produit_id,
                    'lot_id' => $stock->lot_id,
                    'quantite_theorique' => $stock->quantite,
                    'quantite_physique' => $donnees['quantite_physique'],
                ]);

                $stock->update(['quantite' => $donnees['quantite_physique']]);
            }

            return $inventaire;
        });

        return redirect()->route('stock.inventaires.show', $inventaire)->with('status', __('Inventaire enregistré, le stock a été ajusté.'));
    }

    public function show(Inventaire $inventaire): View
    {
        $this->authorize('view', $inventaire);

        $inventaire->load(['boutique', 'creePar', 'lignes.produit', 'lignes.lot']);

        return view('stock.inventaires.show', compact('inventaire'));
    }
}
