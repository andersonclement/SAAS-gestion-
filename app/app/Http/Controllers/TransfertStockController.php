<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransfertStockRequest;
use App\Models\Boutique;
use App\Models\StockBoutique;
use App\Models\TransfertStock;
use App\Support\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TransfertStockController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', TransfertStock::class);

        $user = Auth::user();

        $transferts = TransfertStock::with(['produit', 'lot', 'boutiqueSource', 'boutiqueDestination'])
            ->when($user->boutique_id, fn ($query) => $query->where(fn ($q) => $q
                ->where('boutique_source_id', $user->boutique_id)
                ->orWhere('boutique_destination_id', $user->boutique_id)))
            ->latest()
            ->get();

        return view('stock.transferts.index', compact('transferts'));
    }

    public function create(): View
    {
        $this->authorize('create', TransfertStock::class);

        $user = Auth::user();

        $stocksDisponibles = StockBoutique::with(['boutique', 'produit', 'lot'])
            ->when($user->boutique_id, fn ($query) => $query->where('boutique_id', $user->boutique_id))
            ->where('quantite', '>', 0)
            ->get();

        $boutiques = Boutique::orderBy('nom')->get();

        return view('stock.transferts.create', compact('stocksDisponibles', 'boutiques'));
    }

    public function store(StoreTransfertStockRequest $request): RedirectResponse
    {
        $transfert = DB::transaction(function () use ($request) {
            $stockSource = $request->stockBoutique();
            $quantite = (int) $request->validated('quantite');

            $stockSource->decrement('quantite', $quantite);

            $stockDestination = StockBoutique::firstOrCreate(
                [
                    'boutique_id' => $request->validated('boutique_destination_id'),
                    'lot_id' => $stockSource->lot_id,
                ],
                [
                    'tenant_id' => $stockSource->tenant_id,
                    'produit_id' => $stockSource->produit_id,
                    'quantite' => 0,
                ]
            );
            $stockDestination->increment('quantite', $quantite);

            return TransfertStock::create([
                'produit_id' => $stockSource->produit_id,
                'lot_id' => $stockSource->lot_id,
                'boutique_source_id' => $stockSource->boutique_id,
                'boutique_destination_id' => $request->validated('boutique_destination_id'),
                'quantite' => $quantite,
            ]);
        });

        Journal::enregistrer('stock.transfert', __(':quantite unité(s) transférées vers une autre boutique.', [
            'quantite' => $transfert->quantite,
        ]), $transfert->boutique_source_id);

        return redirect()->route('stock.index')->with('status', __('Transfert de stock enregistré.'));
    }
}
