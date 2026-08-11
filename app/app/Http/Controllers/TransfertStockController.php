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
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TransfertStockController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', TransfertStock::class);

        $user = Auth::user();

        $transferts = TransfertStock::with(['produit', 'lot', 'boutiqueSource', 'boutiqueDestination'])
            ->when($user->effectiveBoutiqueId(), fn ($query) => $query->where(fn ($q) => $q
                ->where('boutique_source_id', $user->effectiveBoutiqueId())
                ->orWhere('boutique_destination_id', $user->effectiveBoutiqueId())))
            ->latest()
            ->get();

        return view('stock.transferts.index', compact('transferts'));
    }

    public function create(): View
    {
        $this->authorize('create', TransfertStock::class);

        $user = Auth::user();

        $stocksDisponibles = StockBoutique::with(['boutique', 'produit', 'lot'])
            ->when($user->effectiveBoutiqueId(), fn ($query) => $query->where('boutique_id', $user->effectiveBoutiqueId()))
            ->where('quantite', '>', 0)
            ->get();

        $boutiques = Boutique::orderBy('nom')->get();

        return view('stock.transferts.create', compact('stocksDisponibles', 'boutiques'));
    }

    public function store(StoreTransfertStockRequest $request): RedirectResponse
    {
        $transfert = DB::transaction(function () use ($request) {
            $quantite = (int) $request->validated('quantite');

            // Verrou + re-vérification à l'intérieur de la transaction : la
            // validation a lieu avant, donc deux transferts simultanés du
            // même lot pourraient sinon rendre le stock source négatif.
            $stockSource = StockBoutique::whereKey($request->stockBoutique()->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($stockSource->quantite < $quantite) {
                throw ValidationException::withMessages([
                    'quantite' => __('Stock insuffisant dans la boutique source (:disponible disponible).', [
                        'disponible' => $stockSource->quantite,
                    ]),
                ]);
            }

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
