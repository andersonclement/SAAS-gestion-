<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVenteRequest;
use App\Models\Boutique;
use App\Models\Client;
use App\Models\Paiement;
use App\Models\Produit;
use App\Models\StockBoutique;
use App\Models\Vente;
use App\Support\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class VenteController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Vente::class);

        $user = Auth::user();

        $ventes = Vente::with(['boutique', 'client', 'vendeur', 'lignes'])
            ->when($user->boutique_id, fn ($query) => $query->where('boutique_id', $user->boutique_id))
            ->latest()
            ->get();

        return view('ventes.index', compact('ventes'));
    }

    public function create(): View
    {
        $this->authorize('create', Vente::class);

        $user = Auth::user();

        $boutiques = $user->boutique_id
            ? Boutique::whereKey($user->boutique_id)->get()
            : Boutique::orderBy('nom')->get();

        $clients = Client::orderBy('nom')->get();
        $produits = Produit::where('actif', true)->orderBy('nom')->get();

        return view('ventes.create', compact('boutiques', 'clients', 'produits'));
    }

    public function store(StoreVenteRequest $request): RedirectResponse
    {
        $vente = DB::transaction(function () use ($request) {
            $vente = Vente::create($request->safe()->only(['boutique_id', 'client_id', 'date_echeance']));

            foreach ($request->validated('lignes') as $ligne) {
                $this->allouerStock(
                    $vente,
                    (int) $ligne['produit_id'],
                    (int) $ligne['quantite'],
                    (int) $request->validated('boutique_id')
                );
            }

            $vente->load('lignes');

            $montantPaye = $request->filled('montant_paye')
                ? (int) $request->validated('montant_paye')
                : $vente->montantTotal();

            if ($montantPaye > 0) {
                Paiement::create([
                    'vente_id' => $vente->id,
                    'mode' => $request->validated('mode_paiement'),
                    'montant' => $montantPaye,
                ]);
            }

            return $vente;
        });

        Journal::enregistrer('vente.creee', __('Vente :numero enregistrée (:montant FCFA).', [
            'numero' => $vente->numero,
            'montant' => number_format($vente->montantTotal(), 0, ',', ' '),
        ]), $vente->boutique_id);

        return redirect()->route('ventes.show', $vente)->with('status', __('Vente enregistrée avec succès.'));
    }

    public function show(Vente $vente): View
    {
        $this->authorize('view', $vente);

        $vente->load(['boutique', 'client', 'vendeur', 'lignes.produit', 'lignes.lot', 'paiements']);

        return view('ventes.show', compact('vente'));
    }

    /**
     * Alloue la quantité vendue sur les lots disponibles de la boutique en
     * épuisant en priorité ceux dont la péremption est la plus proche
     * (FEFO — first-expired, first-out), et décrémente le stock en
     * conséquence.
     */
    private function allouerStock(Vente $vente, int $produitId, int $quantiteDemandee, int $boutiqueId): void
    {
        $produit = Produit::findOrFail($produitId);

        $stocks = StockBoutique::query()
            ->join('lots', 'stock_boutiques.lot_id', '=', 'lots.id')
            ->where('stock_boutiques.boutique_id', $boutiqueId)
            ->where('stock_boutiques.produit_id', $produitId)
            ->where('stock_boutiques.quantite', '>', 0)
            ->orderByRaw('lots.date_peremption is null, lots.date_peremption asc')
            ->select('stock_boutiques.*')
            ->get();

        $restant = $quantiteDemandee;

        foreach ($stocks as $stock) {
            if ($restant <= 0) {
                break;
            }

            $prelevement = min($restant, $stock->quantite);
            $stock->decrement('quantite', $prelevement);

            $vente->lignes()->create([
                'produit_id' => $produitId,
                'lot_id' => $stock->lot_id,
                'quantite' => $prelevement,
                'prix_unitaire' => $produit->prix_vente,
            ]);

            $restant -= $prelevement;
        }
    }
}
